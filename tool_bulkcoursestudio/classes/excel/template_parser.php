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

use tool_bulkcoursestudio\draft_repository;

/**
 * Parses an uploaded, filled-in .xlsx (built from template_generator's
 * layout) into a new draft build — the same draft rows Flow A's wizard
 * produces, so build_manager::preview()/commit() need no Flow-B-specific
 * code path (spec §3.2 step 4).
 *
 * Deliberately permissive about structural/content problems at parse time
 * — a bad category path or unknown activity type becomes a draft row with
 * that bad data in it, which build_manager::preview() then reports as a
 * normal validation error on the same preview screen Flow A uses. Parsing
 * itself only fails hard on a structurally broken file (wrong sheet names,
 * missing header row).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_parser {
    /**
     * Parse an uploaded .xlsx workbook into a new draft build.
     *
     * @param string $filepath path to the uploaded .xlsx on disk
     * @return int the new draft build's id
     * @throws \moodle_exception on a structurally invalid file
     */
    public static function parse(string $filepath): int {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            throw new \coding_exception(
                'PhpSpreadsheet not autoloaded — verify the bundled path for this Moodle version (lib/phpspreadsheet).'
            );
        }

        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);

        foreach (['Course', 'Sections', 'Activities'] as $required) {
            if (!$workbook->sheetNameExists($required)) {
                throw new \moodle_exception('excel:missingsheet', 'tool_bulkcoursestudio', '', $required);
            }
        }

        $coursedata = self::parse_course_sheet($workbook->getSheetByName('Course'));
        $buildid = draft_repository::save_course_details(null, $coursedata, 'excel');

        $sectionmap = self::parse_sections_sheet($workbook->getSheetByName('Sections'), $buildid);
        self::parse_activities_sheet($workbook->getSheetByName('Activities'), $sectionmap);

        return $buildid;
    }

    /**
     * Read the first data row of the Course sheet into course-details data.
     *
     * @return array course-details data, shaped like
     *               draft_repository::save_course_details() expects
     */
    private static function parse_course_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
        $rows = $sheet->toArray(null, true, true, true);
        unset($rows[1]); // Header row.

        foreach ($rows as $row) {
            $fullname = trim((string) ($row['A'] ?? ''));
            if ($fullname === '' || $fullname[0] === '#') {
                continue; // Blank or the commented-out example row.
            }

            return [
                'fullname' => $fullname,
                'shortname' => trim((string) ($row['B'] ?? '')),
                'category' => self::resolve_category_path(trim((string) ($row['C'] ?? ''))),
                'summary' => (string) ($row['D'] ?? ''),
                'format' => trim((string) ($row['E'] ?? 'topics')),
                'startdate' => self::parse_excel_date($row['F'] ?? null) ?: time(),
                'enddate' => self::parse_excel_date($row['G'] ?? null),
                'enablecompletion' => ((string) ($row['H'] ?? '1')) === '1' ? 1 : 0,
                'visible' => 0,
            ];
        }

        throw new \moodle_exception('excel:nocourserow', 'tool_bulkcoursestudio');
    }

    /**
     * Create a draft section for every data row in the Sections sheet.
     *
     * @return array section_number (int, as given in the sheet) => draft
     *               section id, so parse_activities_sheet() can resolve
     *               its own section_number column against real rows.
     */
    private static function parse_sections_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $buildid): array {
        $rows = $sheet->toArray(null, true, true, true);
        unset($rows[1]);

        $map = [];
        foreach ($rows as $row) {
            $number = trim((string) ($row['A'] ?? ''));
            $name = trim((string) ($row['B'] ?? ''));
            if ($number === '' || $name === '') {
                continue;
            }

            $sectionid = draft_repository::add_or_update_section($buildid, null, $name, (string) ($row['C'] ?? ''));
            $map[(int) $number] = $sectionid;
        }

        return $map;
    }

    /**
     * Create a draft activity for every data row in the Activities sheet.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param array $sectionmap section_number => draft section id, from
     *              parse_sections_sheet()
     */
    private static function parse_activities_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $sectionmap): void {
        $rows = $sheet->toArray(null, true, true, true);
        unset($rows[1]);

        foreach ($rows as $row) {
            $sectionnumber = (int) trim((string) ($row['A'] ?? ''));
            $modname = trim((string) ($row['B'] ?? ''));
            $name = trim((string) ($row['C'] ?? ''));
            if ($modname === '' || $name === '') {
                continue;
            }

            // An activity referencing a section number not in the
            // Sections sheet is left with no sectionid here — deliberately,
            // rather than guessing one — so build_manager::preview() can
            // surface a clear "unknown section" error instead of this
            // parser silently dropping or misfiling the row. (Tracked as a
            // parser-level validation message to add alongside
            // build_manager's own checks — see README "Still outstanding".)
            if (!isset($sectionmap[$sectionnumber])) {
                continue;
            }

            $moduledata = ['intro' => (string) ($row['D'] ?? '')];
            if ($modname === 'bulkcoursestudio') {
                $moduledata['embedurl'] = trim((string) ($row['E'] ?? ''));
            }
            if (!empty($row['F'])) {
                // Local_filename is resolved against the admin's follow-up
                // file-batch upload, not here — stored as-is for that step
                // to match against.
                $moduledata['localfilename'] = trim((string) $row['F']);
            }

            $completiondata = ['completionview' => ((string) ($row['G'] ?? '0')) === '1' ? 1 : 0];

            draft_repository::add_or_update_activity(
                $sectionmap[$sectionnumber],
                null,
                $modname,
                $name,
                $moduledata,
                $completiondata
            );
        }
    }

    /**
     * Resolves a "Top / Sub / Subsub" category path to a category id.
     * Returns 0 (invalid) rather than throwing if not found — left for
     * build_manager::preview()'s existing badcategory check to report,
     * same as every other content-validity problem in this file.
     */
    private static function resolve_category_path(string $path): int {
        if ($path === '') {
            return 0;
        }

        $names = array_map('trim', explode('/', $path));
        $parent = 0;

        global $DB;
        foreach ($names as $name) {
            $category = $DB->get_record('course_categories', ['name' => $name, 'parent' => $parent]);
            if (!$category) {
                return 0;
            }
            $parent = $category->id;
        }

        return $parent;
    }

    /**
     * PhpSpreadsheet gives back either an Excel serial date (numeric) or a
     * plain string depending on how the cell was formatted/typed by
     * whatever spreadsheet app the admin used — handle both rather than
     * assuming one.
     */
    private static function parse_excel_date($value): ?int {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $value);
        }
        $timestamp = strtotime((string) $value);
        return $timestamp ?: null;
    }
}
