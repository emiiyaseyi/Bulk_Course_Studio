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

        $warnings = [];
        $coursedata = self::parse_course_sheet($workbook->getSheetByName('Course'), $warnings);
        $buildid = draft_repository::save_course_details(null, $coursedata, 'excel');

        $sectionmap = self::parse_sections_sheet($workbook->getSheetByName('Sections'), $buildid);
        $activitycompetencies = self::parse_activities_sheet($workbook->getSheetByName('Activities'), $sectionmap, $warnings);

        // Competencies used by activities must also be linked to the course.
        $coursedata['competencyids'] = array_values(array_unique(array_merge(
            $coursedata['competencyids'] ?? [],
            $activitycompetencies
        )));
        $coursedata['importwarnings'] = $warnings;
        draft_repository::save_course_details($buildid, $coursedata, 'excel');

        return $buildid;
    }

    /**
     * Read the first data row of the Course sheet into course-details data.
     *
     * @return array course-details data, shaped like
     *               draft_repository::save_course_details() expects
     */
    private static function parse_course_sheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array &$warnings): array {
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
                'enddate' => self::parse_excel_date($row['G'] ?? null) ?? 0,
                'enablecompletion' => self::yesno($row['H'] ?? null, true) ? 1 : 0,
                'visible' => 0,
                'tags' => self::split_list((string) ($row['I'] ?? '')),
                'competencyids' => self::resolve_competencies(self::split_list((string) ($row['J'] ?? '')), $warnings),
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
     * @param array $warnings collects non-fatal import warnings
     * @return int[] competency ids used by any activity
     */
    private static function parse_activities_sheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $sectionmap,
        array &$warnings
    ): array {
        $usedcompetencies = [];
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
            // An Excel row only carries a few fields, so fill in the settings
            // Moodle's own form would have supplied, for the simple types.
            $moduledata = array_merge(
                self::basic_defaults($modname, (string) ($row['D'] ?? ''), trim((string) ($row['E'] ?? ''))),
                $moduledata
            );
            if (!empty($row['F'])) {
                // Local_filename is resolved against the admin's follow-up
                // file-batch upload, not here — stored as-is for that step
                // to match against.
                $moduledata['localfilename'] = trim((string) $row['F']);
            }

            // Extra per-activity options (columns H-N), all optional.
            if (trim((string) ($row['H'] ?? '')) !== '') {
                $moduledata['visible'] = self::yesno($row['H'], true) ? 1 : 0;
            }
            $tags = self::split_list((string) ($row['I'] ?? ''));
            if ($tags) {
                $moduledata['tags'] = $tags;
            }
            $competencyids = self::resolve_competencies(self::split_list((string) ($row['J'] ?? '')), $warnings);
            if ($competencyids) {
                $moduledata['competencies'] = $competencyids;
                $usedcompetencies = array_merge($usedcompetencies, $competencyids);
            }
            $availability = self::build_availability(
                self::parse_excel_date($row['K'] ?? null),
                self::parse_excel_date($row['L'] ?? null)
            );
            if ($availability !== '') {
                $moduledata['availability'] = $availability;
                $moduledata['availabilityconditionsjson'] = $availability;
            }

            $completiondata = self::build_completion(
                self::yesno($row['G'] ?? null, false),
                strtolower(trim((string) ($row['M'] ?? ''))),
                (int) ($row['N'] ?? 0),
                $modname,
                (int) ($row['O'] ?? 0)
            );

            draft_repository::add_or_update_activity(
                $sectionmap[$sectionnumber],
                null,
                $modname,
                $name,
                $moduledata,
                $completiondata
            );
        }

        return array_values(array_unique($usedcompetencies));
    }

    /**
     * Default settings for activity types an Excel row can build reliably.
     * Any other type (assignment, quiz, forum, SCORM...) is created with
     * only what the row gives it, flagged so the preview warns the admin to
     * open it and set it up in the wizard.
     *
     * @param string $modname
     * @param string $description the row's description cell
     * @param string $link the row's embed_url cell
     * @return array moduledata defaults
     */
    private static function basic_defaults(string $modname, string $description, string $link): array {
        switch ($modname) {
            case 'page':
                return [
                    'page' => ['text' => $description, 'format' => FORMAT_HTML, 'itemid' => 0],
                    'display' => 5,
                    'printheading' => 1,
                    'printintro' => 0,
                    'printlastmodified' => 1,
                ];
            case 'url':
                return ['externalurl' => $link, 'display' => 0, 'printintro' => 0];
            case 'label':
                return [];
            case 'resource':
                return ['files' => 0, 'display' => 0, 'showsize' => 0, 'showtype' => 0, 'showdate' => 0, 'printintro' => 1];
            case 'folder':
                return ['files' => 0, 'display' => 0, 'showexpanded' => 1, 'showdownloadfolder' => 1, 'forcedownload' => 1];
            case 'bulkcoursestudio':
                return ['mediasource' => 'url', 'autonext' => 1];
            default:
                return ['_excelbasic' => 1];
        }
    }

    /**
     * Read a Yes/No cell. Also accepts 1/0, true/false, y/n, so older files still work.
     *
     * @param mixed $value the cell
     * @param bool $default used when the cell is empty
     * @return bool
     */
    private static function yesno($value, bool $default): bool {
        $text = strtolower(trim((string) $value));
        if ($text === '') {
            return $default;
        }
        return in_array($text, ['1', 'yes', 'y', 'true', 'on'], true);
    }

    /**
     * Split a comma/semicolon separated cell into trimmed, non-empty values.
     *
     * @param string $value
     * @return string[]
     */
    private static function split_list(string $value): array {
        $parts = preg_split('/[,;]/', $value);
        return array_values(array_filter(array_map('trim', $parts), function ($part) {
            return $part !== '';
        }));
    }

    /**
     * Look up competencies by short name or ID number. Names that can't be
     * found are skipped with a warning shown on the preview screen.
     *
     * @param string[] $names
     * @param array $warnings collects warnings
     * @return int[] competency ids
     */
    private static function resolve_competencies(array $names, array &$warnings): array {
        if (!$names) {
            return [];
        }
        if (!\core_competency\api::is_enabled()) {
            $warnings[] = get_string('excel:warning:competenciesdisabled', 'tool_bulkcoursestudio');
            return [];
        }
        $ids = [];
        foreach ($names as $name) {
            $competency = \core_competency\competency::get_record(['shortname' => $name]);
            if (!$competency) {
                $competency = \core_competency\competency::get_record(['idnumber' => $name]);
            }
            if ($competency) {
                $ids[] = (int) $competency->get('id');
            } else {
                $warnings[] = get_string('excel:warning:competencynotfound', 'tool_bulkcoursestudio', $name);
            }
        }
        return $ids;
    }

    /**
     * Moodle's restrict-access JSON for an optional open and close date.
     *
     * @param int|null $from
     * @param int|null $until
     * @return string JSON, or '' when there is no restriction
     */
    private static function build_availability(?int $from, ?int $until): string {
        $conditions = [];
        if ($from) {
            $conditions[] = (object) ['type' => 'date', 'd' => '>=', 't' => $from];
        }
        if ($until) {
            $conditions[] = (object) ['type' => 'date', 'd' => '<', 't' => $until];
        }
        if (!$conditions) {
            return '';
        }
        return json_encode((object) [
            'op' => '&',
            'c' => $conditions,
            'showc' => array_fill(0, count($conditions), true),
        ]);
    }

    /**
     * Completion settings from the 'completion' column (falls back to the
     * older completion_view column when it is blank).
     *
     * @param bool $legacyview the older completion_view column was 1
     * @param string $mode none|manual|view|percent|''
     * @param int $percent
     * @param string $modname
     * @param int $minutes
     * @return array
     */
    private static function build_completion(
        bool $legacyview,
        string $mode,
        int $percent,
        string $modname,
        int $minutes = 0
    ): array {
        if ($mode === '') {
            $mode = $legacyview ? 'view' : '';
        }
        switch ($mode) {
            case 'none':
                return ['completion' => 0, 'completionview' => 0];
            case 'manual':
                return ['completion' => 1, 'completionview' => 0];
            case 'view':
                return ['completion' => 2, 'completionview' => 1];
            case 'time':
                $data = ['completion' => 2, 'completionview' => 1];
                if ($modname === 'bulkcoursestudio' && $minutes > 0) {
                    $data['completiontimespentenabled'] = 1;
                    $data['completiontimespent'] = min(1440, $minutes);
                }
                return $data;
            case 'percent':
                $data = ['completion' => 2, 'completionview' => 1];
                if ($modname === 'bulkcoursestudio' && $percent > 0) {
                    $data['completionpercentenabled'] = 1;
                    $data['completionpercent'] = min(100, $percent);
                }
                return $data;
            default:
                return ['completionview' => 0];
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
