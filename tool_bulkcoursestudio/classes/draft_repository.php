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

namespace tool_bulkcoursestudio;

/**
 * Thin CRUD layer over the three draft tables
 * (tool_bulkcoursestudio_build/_section/_activity), used by the wizard step
 * controllers. Kept separate from build_manager deliberately:
 * draft_repository only ever touches the plugin's own draft tables, while
 * build_manager is the only class that calls core course/module APIs — so
 * it's always obvious, reading a stack trace, which side of that line a
 * bug is on.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_repository {
    /**
     * Fetch a single draft build record by id.
     *
     * @param int $buildid
     * @return \stdClass
     */
    public static function get_build(int $buildid): \stdClass {
        global $DB;
        return $DB->get_record('tool_bulkcoursestudio_build', ['id' => $buildid], '*', MUST_EXIST);
    }

    /**
     * All draft builds created by a given user, most recently modified first.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function get_my_builds(int $userid): array {
        global $DB;
        return $DB->get_records('tool_bulkcoursestudio_build', ['usermodified' => $userid], 'timemodified DESC');
    }

    /**
     * Create a new draft build from the course-details step, or update an
     * existing one if $buildid is given (editing step 1 after already
     * moving on to later steps).
     */
    public static function save_course_details(?int $buildid, array $coursedata, string $source = 'wizard'): int {
        global $DB, $USER;

        $now = time();
        if ($buildid) {
            $build = self::get_build($buildid);
            $build->coursedata = json_encode($coursedata);
            $build->timemodified = $now;
            $DB->update_record('tool_bulkcoursestudio_build', $build);
            return $build->id;
        }

        $build = (object) [
            'courseid' => null,
            'status' => 'draft',
            'source' => $source,
            'coursedata' => json_encode($coursedata),
            'formatoptions' => json_encode([]),
            'scheduledtime' => null,
            'usermodified' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return $DB->insert_record('tool_bulkcoursestudio_build', $build);
    }

    /**
     * Store the chosen course format and its format-specific options on a
     * draft build.
     *
     * @param int $buildid
     * @param string $format
     * @param array $formatoptions
     */
    public static function save_format(int $buildid, string $format, array $formatoptions = []): void {
        global $DB;
        $build = self::get_build($buildid);
        $coursedata = json_decode($build->coursedata, true) ?? [];
        $coursedata['format'] = $format;
        $build->coursedata = json_encode($coursedata);
        $build->formatoptions = json_encode($formatoptions);
        $build->timemodified = time();
        $DB->update_record('tool_bulkcoursestudio_build', $build);
    }

    /**
     * Create a new draft section, or update an existing one when $sectionid
     * is given.
     *
     * @param int $buildid
     * @param int|null $sectionid
     * @param string $name
     * @param string $summary
     * @return int the section's id
     */
    public static function add_or_update_section(int $buildid, ?int $sectionid, string $name, string $summary): int {
        global $DB;
        $now = time();

        if ($sectionid) {
            $section = $DB->get_record('tool_bulkcoursestudio_section', ['id' => $sectionid], '*', MUST_EXIST);
            $section->name = $name;
            $section->summary = $summary;
            $section->timemodified = $now;
            $DB->update_record('tool_bulkcoursestudio_section', $section);
            return $section->id;
        }

        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {tool_bulkcoursestudio_section} WHERE buildid = ?',
            [$buildid]
        );

        return $DB->insert_record('tool_bulkcoursestudio_section', (object) [
            'buildid' => $buildid,
            'sortorder' => ((int) $maxsort) + 1,
            'name' => $name,
            'summary' => $summary,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * All draft sections belonging to a build, in sort order.
     *
     * @param int $buildid
     * @return \stdClass[]
     */
    public static function get_sections(int $buildid): array {
        global $DB;
        return $DB->get_records('tool_bulkcoursestudio_section', ['buildid' => $buildid], 'sortorder ASC');
    }

    /**
     * All draft activities belonging to a section, in sort order.
     *
     * @param int $sectionid
     * @return \stdClass[]
     */
    public static function get_activities(int $sectionid): array {
        global $DB;
        return $DB->get_records('tool_bulkcoursestudio_activity', ['sectionid' => $sectionid], 'sortorder ASC');
    }

    /**
     * The real course's 1-based section number for a draft section, as
     * build_manager::sync() assigns it — i.e. its position (by sortorder)
     * among every section in the same build. Used by
     * step_activity_settings.php to construct the real $cw/$section value
     * a native mod_form expects.
     *
     * @throws \coding_exception if $sectionid isn't one of this build's sections
     */
    public static function get_section_number(int $buildid, int $sectionid): int {
        $position = 1;
        foreach (self::get_sections($buildid) as $section) {
            if ((int) $section->id === $sectionid) {
                return $position;
            }
            $position++;
        }
        throw new \coding_exception('Section ' . $sectionid . ' does not belong to build ' . $buildid);
    }

    /**
     * Create a new draft activity, or update an existing one when
     * $activityid is given.
     *
     * @param int $sectionid
     * @param int|null $activityid
     * @param string $modname
     * @param string $name
     * @param array $moduledata
     * @param array $completiondata
     * @return int the activity's id
     */
    public static function add_or_update_activity(
        int $sectionid,
        ?int $activityid,
        string $modname,
        string $name,
        array $moduledata,
        array $completiondata = []
    ): int {
        global $DB;
        $now = time();

        if ($activityid) {
            $activity = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $activityid], '*', MUST_EXIST);
            $activity->modname = $modname;
            $activity->name = $name;
            $activity->moduledata = json_encode($moduledata);
            $activity->completiondata = json_encode($completiondata);
            $activity->timemodified = $now;
            $DB->update_record('tool_bulkcoursestudio_activity', $activity);
            return $activity->id;
        }

        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {tool_bulkcoursestudio_activity} WHERE sectionid = ?',
            [$sectionid]
        );

        return $DB->insert_record('tool_bulkcoursestudio_activity', (object) [
            'sectionid' => $sectionid,
            'sortorder' => ((int) $maxsort) + 1,
            'modname' => $modname,
            'name' => $name,
            'moduledata' => json_encode($moduledata),
            'completiondata' => json_encode($completiondata),
            'committedcmid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * All resource/folder activities across a build that carry a
     * "localfilename" expectation from an Excel upload (Flow B) — i.e. the
     * admin typed a filename in the template but hasn't necessarily
     * attached the matching file yet. Powers step_matchfiles.php.
     *
     * @return \stdClass[] each with ->id, ->name, ->modname,
     *         ->localfilename, ->sectionname, ->hasfile
     */
    public static function get_activities_needing_files(int $buildid): array {
        global $DB;

        $fs = get_file_storage();
        $systemcontextid = \context_system::instance()->id;
        $needing = [];

        foreach (self::get_sections($buildid) as $section) {
            foreach (self::get_activities($section->id) as $activity) {
                if (!in_array($activity->modname, ['resource', 'folder'], true)) {
                    continue;
                }
                $moduledata = json_decode($activity->moduledata, true) ?? [];
                $localfilename = trim((string) ($moduledata['localfilename'] ?? ''));
                if ($localfilename === '') {
                    continue;
                }
                $storedfiles = $fs->get_area_files(
                    $systemcontextid,
                    'tool_bulkcoursestudio',
                    'activityfiles',
                    $activity->id,
                    'filename',
                    false
                );
                $needing[] = (object) [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'modname' => $activity->modname,
                    'localfilename' => $localfilename,
                    'sectionname' => $section->name,
                    'hasfile' => !empty($storedfiles),
                ];
            }
        }

        return $needing;
    }

    /**
     * The build row that produced a given live course, if the course was
     * built (or has since been edited) through this tool. Powers the
     * "Edit this course" entry point from a course's own admin menu as
     * well as edit_course.php's course picker.
     */
    public static function get_build_by_courseid(int $courseid): ?\stdClass {
        global $DB;
        $build = $DB->get_record('tool_bulkcoursestudio_build', ['courseid' => $courseid]);
        return $build ?: null;
    }

    /**
     * Every committed build (courseid set — i.e. has actually been built,
     * not just started), across *all* admins, optionally filtered by a
     * fullname/shortname search term — so one admin can find and edit a
     * course another admin built with this tool, not only their own.
     * Used by edit_course.php.
     *
     * @return \stdClass[] each decorated with ->coursefullname and
     *         ->courseshortname pulled live from the course table (never
     *         trusted from the possibly-stale coursedata JSON snapshot).
     */
    public static function search_committed_builds(string $search = ''): array {
        global $DB;

        $params = [];
        $wheresql = 'b.courseid IS NOT NULL';
        if ($search !== '') {
            $wheresql .= ' AND (' . $DB->sql_like('c.fullname', ':search1', false) .
                ' OR ' . $DB->sql_like('c.shortname', ':search2', false) . ')';
            $params['search1'] = '%' . $DB->sql_like_escape($search) . '%';
            $params['search2'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        $sql = "SELECT b.*, c.fullname AS coursefullname, c.shortname AS courseshortname
                  FROM {tool_bulkcoursestudio_build} b
                  JOIN {course} c ON c.id = b.courseid
                 WHERE $wheresql
              ORDER BY b.timemodified DESC";

        return $DB->get_records_sql($sql, $params, 0, 50);
    }

    /**
     * Whether a draft activity is set to be hidden from learners.
     *
     * @param \stdClass $activity draft activity row
     * @return bool
     */
    public static function is_activity_hidden(\stdClass $activity): bool {
        $moduledata = json_decode($activity->moduledata ?? '{}', true) ?? [];
        return isset($moduledata['visible']) && (int) $moduledata['visible'] === 0;
    }

    /**
     * Hide or show a draft activity. The choice is saved in the draft (so
     * later saves keep it) and applied at once to the real course module
     * through core's own set_coursemodule_visible().
     *
     * @param int $activityid
     * @param bool $visible
     */
    public static function set_activity_visibility(int $activityid, bool $visible): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $activity = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $activityid], '*', MUST_EXIST);
        $moduledata = json_decode($activity->moduledata ?? '{}', true) ?? [];
        $moduledata['visible'] = $visible ? 1 : 0;
        $DB->set_field('tool_bulkcoursestudio_activity', 'moduledata', json_encode($moduledata), ['id' => $activityid]);
        $DB->set_field('tool_bulkcoursestudio_activity', 'timemodified', time(), ['id' => $activityid]);

        if (!empty($activity->committedcmid) && $DB->record_exists('course_modules', ['id' => $activity->committedcmid])) {
            set_coursemodule_visible($activity->committedcmid, $visible ? 1 : 0);
        }
    }

    /**
     * Remove one draft activity, and the real course module it created (if
     * any) through core's own course_delete_module().
     *
     * @param int $activityid
     */
    public static function delete_activity(int $activityid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $activity = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $activityid], '*', MUST_EXIST);
        if (!empty($activity->committedcmid) && $DB->record_exists('course_modules', ['id' => $activity->committedcmid])) {
            course_delete_module($activity->committedcmid);
        }
        $DB->delete_records('tool_bulkcoursestudio_activity', ['id' => $activityid]);
    }

    /**
     * Permanently remove a draft build and all of its sections and
     * activities. Does not touch any real course — callers that need the
     * course removed too should go through build_manager::discard().
     *
     * @param int $buildid
     */
    public static function delete_build(int $buildid): void {
        global $DB;
        foreach (self::get_sections($buildid) as $section) {
            $DB->delete_records('tool_bulkcoursestudio_activity', ['sectionid' => $section->id]);
        }
        $DB->delete_records('tool_bulkcoursestudio_section', ['buildid' => $buildid]);
        $DB->delete_records('tool_bulkcoursestudio_build', ['id' => $buildid]);
    }
}
