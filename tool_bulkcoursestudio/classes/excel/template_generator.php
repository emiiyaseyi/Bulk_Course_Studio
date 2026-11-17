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

/**
 * Builds the downloadable .xlsx template for Flow B (spec §3.2) — generated
 * server-side on every download (never a static file) so the Category and
 * Format columns' dropdown/help content always matches what's actually
 * installed/configured on this site at download time.
 *
 * Uses PhpSpreadsheet, which ships bundled with Moodle core (no new
 * dependency, per spec §5) — loaded via Moodle's own autoloader rather than
 * a manual require, since the exact bundled path has moved between Moodle
 * majors (lib/phpspreadsheet in recent versions).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_generator {
    /**
     * Build the downloadable .xlsx template workbook.
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function build(): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            throw new \coding_exception(
                'PhpSpreadsheet not autoloaded — verify the bundled path for this Moodle version (lib/phpspreadsheet).'
            );
        }

        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        self::build_course_sheet($workbook->getActiveSheet());
        self::build_sections_sheet($workbook->createSheet());
        self::build_activities_sheet($workbook->createSheet());

        $workbook->setActiveSheetIndex(0);

        return $workbook;
    }

    /**
     * Build the Course sheet, with its header row and commented-out
     * example row.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private static function build_course_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void {
        $sheet->setTitle('Course');

        $headers = ['fullname', 'shortname', 'category_path', 'summary', 'format', 'startdate', 'enddate', 'enablecompletion',
            'tags', 'competencies'];
        $sheet->fromArray($headers, null, 'A1');

        // One example row, commented out via a leading '#', so re-uploading
        // the untouched template doesn't silently create a junk course —
        // template_parser::parse() skips any Course row whose fullname
        // starts with '#'.
        $examplecategory = \core_course_category::top()->get_children();
        $examplecategoryname = !empty($examplecategory) ? reset($examplecategory)->get_formatted_name() : 'Miscellaneous';
        $examplerow = [
            '# Example Course', 'EX101', $examplecategoryname,
            'Replace this row with your own course.', 'topics', '2027-01-06', '', '1', 'onboarding, induction', '',
        ];
        $sheet->fromArray($examplerow, null, 'A2');

        // A comment, not a dropdown-enforcing data validation, since the
        // category tree and installed formats can be long — a comment is
        // enough guidance without making the cell unusable for an admin
        // who prefers to type.
        $installedformats = implode(', ', array_keys(\core_component::get_plugin_list('format')));
        $sheet->getCell('E1')->getComment()->getText()->createTextRun('Installed formats: ' . $installedformats);
        $sheet->getCell('C1')->getComment()->getText()->createTextRun(
            'Full path, e.g. "Science / Physics". Must already exist — categories are not created from this file.'
        );

        $sheet->getCell('I1')->getComment()->getText()->createTextRun('Course tags, separated by commas.');
        $sheet->getCell('J1')->getComment()->getText()->createTextRun(
            'Competency short names or ID numbers, separated by commas. Needs the competencies feature on.'
        );

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Build the Sections sheet, with its header row and one example row.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private static function build_sections_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void {
        $sheet->setTitle('Sections');

        $headers = ['section_number', 'name', 'summary'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['1', 'Introduction', 'Welcome and course overview.'], null, 'A2');

        $sheet->getCell('A1')->getComment()->getText()->createTextRun(
            'Start at 1. Referenced by the Activities sheet\'s section_number column.'
        );

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Build the Activities sheet, with its header row and one example row.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private static function build_activities_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void {
        $sheet->setTitle('Activities');

        $headers = ['section_number', 'activity_type', 'name', 'description', 'embed_url', 'local_filename', 'completion_view',
            'visible', 'tags', 'competencies', 'available_from', 'available_until', 'completion', 'completion_percent'];
        $sheet->fromArray($headers, null, 'A1');
        $examplerow = [
            '1', 'bulkcoursestudio', 'Welcome video', 'Watch before starting.',
            'https://www.youtube.com/watch?v=example', '', '1',
            '1', 'video', '', '', '', 'view', '',
        ];
        $sheet->fromArray($examplerow, null, 'A2');

        $installedmods = [];
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('mod') as $plugin) {
            if ($plugin->is_enabled()) {
                $installedmods[] = $plugin->name;
            }
        }
        $sheet->getCell('B1')->getComment()->getText()->createTextRun('Installed activity types: ' . implode(', ', $installedmods));
        $filenamehelp = 'For File/Folder activities only. Matched by exact filename against the batch of '
            . 'files uploaded on the screen after this template is re-uploaded — see the plugin README / spec §3.2.';
        $sheet->getCell('F1')->getComment()->getText()->createTextRun($filenamehelp);

        $notes = [
            'H1' => 'Visible to learners: 1 = yes (default), 0 = hidden.',
            'I1' => 'Activity tags, separated by commas.',
            'J1' => 'Competency short names or ID numbers, separated by commas. They are also linked to the course.',
            'K1' => 'Restrict access: the activity opens on this date (YYYY-MM-DD). Leave blank for no restriction.',
            'L1' => 'Restrict access: the activity closes on this date (YYYY-MM-DD). Leave blank for no restriction.',
            'M1' => 'Completion: none, manual (learner ticks it), view (marked when opened), or percent (Bulk Course Studio embed only).',
            'N1' => 'With completion = percent: the percentage of the video (1-100) the learner must watch.',
        ];
        foreach ($notes as $cell => $note) {
            $sheet->getCell($cell)->getComment()->getText()->createTextRun($note);
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
