<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_bulkcoursestudio\excel;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the downloadable .xlsx template, fresh on every download, so the
 * dropdown lists (categories, formats, activity types) always match what is
 * installed on this site.
 *
 * Cells that take a fixed choice are dropdowns, so nobody has to remember
 * what a value means: Yes/No instead of 1/0, and the completion type picked
 * from a list. An Instructions sheet explains every column.
 *
 * Uses PhpSpreadsheet, which ships bundled with Moodle core.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_generator {
    /** @var int Last row that gets a dropdown on the Course sheet. */
    const COURSE_ROWS = 50;
    /** @var int Last row that gets a dropdown on the Activities sheet. */
    const ACTIVITY_ROWS = 500;

    /**
     * Build the downloadable .xlsx template workbook.
     *
     * @return Spreadsheet
     */
    public static function build(): Spreadsheet {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            throw new \coding_exception(
                'PhpSpreadsheet not autoloaded — verify the bundled path for this Moodle version (lib/phpspreadsheet).'
            );
        }

        $workbook = new Spreadsheet();
        $lists = self::get_lists();

        self::build_course_sheet($workbook->getActiveSheet(), $lists);
        self::build_sections_sheet($workbook->createSheet());
        self::build_activities_sheet($workbook->createSheet(), $lists);
        self::build_instructions_sheet($workbook->createSheet());
        self::build_lists_sheet($workbook->createSheet(), $lists);

        $workbook->setActiveSheetIndex(0);

        return $workbook;
    }

    /**
     * Plain-English guide to every column, shown on the Instructions sheet
     * and on the upload page.
     *
     * @return array[] each [sheet, column, what to enter, allowed values / example]
     */
    public static function column_guide(): array {
        return [
            ['Course', 'fullname', 'The course name', 'Text. Rows starting with # are ignored.'],
            ['Course', 'shortname', 'A short unique name', 'Text, must not already be used by another course'],
            ['Course', 'category_path', 'Where the course lives', 'Pick from the list (it must already exist)'],
            ['Course', 'summary', 'Course description', 'Text'],
            ['Course', 'format', 'How the course page looks', 'Pick from the list, for example topics'],
            ['Course', 'startdate / enddate', 'Course dates', 'YYYY-MM-DD, for example 2027-01-06. End date can be blank'],
            ['Course', 'enablecompletion', 'Track activity completion?', 'Yes or No (pick from the list)'],
            ['Course', 'tags / competencies', 'Tags and competencies for the course', 'Separate several with commas'],
            ['Sections', 'section_number', 'Order of the section', '1, 2, 3...'],
            ['Sections', 'name / summary', 'Section title and description', 'Text'],
            ['Activities', 'section_number', 'Which section it goes in', 'Pick from the list (numbers from the Sections sheet)'],
            ['Activities', 'activity_type', 'Kind of activity', 'Pick from the list. Best for rows: page, url, label, resource, folder, bulkcoursestudio'],
            ['Activities', 'name / description', 'Activity title and text', 'Text'],
            ['Activities', 'embed_url', 'A web link', 'For url and Bulk Course Studio rows, for example a YouTube link'],
            ['Activities', 'local_filename', 'File to attach (resource or folder)', 'The exact file name, for example handout.pdf. You upload the files after this sheet'],
            ['Activities', 'completion_view', 'Mark complete when opened?', 'Yes or No (pick from the list)'],
            ['Activities', 'visible', 'Show to learners?', 'Yes or No. No creates it hidden'],
            ['Activities', 'tags / competencies', 'Tags and competencies for the activity', 'Separate several with commas'],
            ['Activities', 'available_from / available_until', 'Restrict access to these dates', 'YYYY-MM-DD, or leave blank for no restriction'],
            ['Activities', 'completion', 'How it counts as complete', 'none, manual (learner ticks it), view, percent (video watched) or time (minutes on it)'],
            ['Activities', 'completion_percent', 'Used when completion is percent', 'A number from 1 to 100'],
            ['Activities', 'completion_minutes', 'Used when completion is time', 'Minutes, for example 5'],
        ];
    }

    /**
     * The choices that go into the dropdowns.
     *
     * @return array lists keyed formats, categories, mods, yesno, completion
     */
    private static function get_lists(): array {
        $categories = array_values(\core_course_category::make_categories_list());
        if (!$categories) {
            $categories = ['Miscellaneous'];
        }

        $mods = [];
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('mod') as $plugin) {
            if ($plugin->is_enabled()) {
                $mods[] = $plugin->name;
            }
        }
        sort($mods);

        return [
            'formats' => array_keys(\core_component::get_plugin_list('format')),
            'categories' => $categories,
            'mods' => $mods,
            'yesno' => ['Yes', 'No'],
            'completion' => ['none', 'manual', 'view', 'percent', 'time'],
        ];
    }

    /**
     * Add a dropdown to a range of cells.
     *
     * @param Worksheet $sheet
     * @param string $range cells, for example C2:C50
     * @param string $formula where the choices are, for example Lists!$A$2:$A$9
     * @param bool $strict true to refuse anything not in the list
     * @param string $title short heading of the tip
     * @param string $tip text shown when a cell is selected
     */
    private static function add_dropdown(Worksheet $sheet, string $range, string $formula, bool $strict, string $title, string $tip): void {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Not in the list');
        $validation->setError('Please pick a value from the dropdown list.');
        $validation->setPromptTitle($title);
        $validation->setPrompt($tip);
        $validation->setFormula1($formula);
        $sheet->setDataValidation($range, $validation);
    }

    /**
     * Course sheet.
     *
     * @param Worksheet $sheet
     * @param array $lists
     */
    private static function build_course_sheet(Worksheet $sheet, array $lists): void {
        $sheet->setTitle('Course');

        $sheet->fromArray([
            'fullname', 'shortname', 'category_path', 'summary', 'format', 'startdate', 'enddate', 'enablecompletion',
            'tags', 'competencies',
        ], null, 'A1');

        // The example row starts with # so re-uploading the untouched
        // template does not create a junk course.
        $sheet->fromArray([
            '# Example Course', 'EX101', $lists['categories'][0],
            'Replace this row with your own course.', 'topics', '2027-01-06', '', 'Yes', 'onboarding, induction', '',
        ], null, 'A2');

        $last = self::COURSE_ROWS;
        $categoryend = count($lists['categories']) + 1;
        $formatend = count($lists['formats']) + 1;
        self::add_dropdown($sheet, "C2:C$last", 'Lists!$B$2:$B$' . $categoryend, false, 'Category', 'Pick where the course goes.');
        self::add_dropdown($sheet, "E2:E$last", 'Lists!$A$2:$A$' . $formatend, true, 'Format', 'Pick how the course page looks.');
        self::add_dropdown($sheet, "H2:H$last", 'Lists!$D$2:$D$3', true, 'Completion', 'Yes to track activity completion.');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sections sheet.
     *
     * @param Worksheet $sheet
     */
    private static function build_sections_sheet(Worksheet $sheet): void {
        $sheet->setTitle('Sections');

        $sheet->fromArray(['section_number', 'name', 'summary'], null, 'A1');
        $sheet->fromArray([1, 'Introduction', 'Welcome and course overview.'], null, 'A2');

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Activities sheet.
     *
     * @param Worksheet $sheet
     * @param array $lists
     */
    private static function build_activities_sheet(Worksheet $sheet, array $lists): void {
        $sheet->setTitle('Activities');

        $sheet->fromArray([
            'section_number', 'activity_type', 'name', 'description', 'embed_url', 'local_filename', 'completion_view',
            'visible', 'tags', 'competencies', 'available_from', 'available_until', 'completion', 'completion_percent',
            'completion_minutes',
        ], null, 'A1');
        $sheet->fromArray([
            1, 'bulkcoursestudio', 'Welcome video', 'Watch before starting.', 'https://www.youtube.com/watch?v=example', '',
            'Yes', 'Yes', 'video', '', '', '', 'view', '', '',
        ], null, 'A2');

        $last = self::ACTIVITY_ROWS;
        $modend = count($lists['mods']) + 1;
        self::add_dropdown($sheet, "A2:A$last", 'Sections!$A$2:$A$100', false, 'Section', 'Pick a section number from the Sections sheet.');
        self::add_dropdown($sheet, "B2:B$last", 'Lists!$C$2:$C$' . $modend, false, 'Activity type', 'Pick the kind of activity.');
        self::add_dropdown($sheet, "G2:G$last", 'Lists!$D$2:$D$3', true, 'Mark complete when opened', 'Yes or No.');
        self::add_dropdown($sheet, "H2:H$last", 'Lists!$D$2:$D$3', true, 'Visible', 'Yes shows it to learners, No hides it.');
        self::add_dropdown($sheet, "M2:M$last", 'Lists!$E$2:$E$6', true, 'Completion', 'How the activity counts as complete.');

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Instructions sheet: what every column means.
     *
     * @param Worksheet $sheet
     */
    private static function build_instructions_sheet(Worksheet $sheet): void {
        $sheet->setTitle('Instructions');

        $sheet->fromArray(['Sheet', 'Column', 'What to enter', 'Allowed values / example'], null, 'A1');
        $row = 2;
        foreach (self::column_guide() as $line) {
            $sheet->fromArray($line, null, 'A' . $row);
            $row++;
        }
        $row++;
        $sheet->setCellValue('A' . $row, 'Tip: cells with a small arrow are dropdowns, so you never have to remember what a value means.');

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Lists sheet (hidden): the choices behind the dropdowns.
     *
     * @param Worksheet $sheet
     * @param array $lists
     */
    private static function build_lists_sheet(Worksheet $sheet, array $lists): void {
        $sheet->setTitle('Lists');

        $sheet->fromArray(['formats', 'categories', 'activity types', 'yes/no', 'completion'], null, 'A1');
        foreach (['formats' => 'A', 'categories' => 'B', 'mods' => 'C', 'yesno' => 'D', 'completion' => 'E'] as $key => $col) {
            $row = 2;
            foreach ($lists[$key] as $value) {
                $sheet->setCellValue($col . $row, $value);
                $row++;
            }
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }
}
