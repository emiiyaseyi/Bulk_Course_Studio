<?php
// This file is part of Moodle - http://moodle.org/
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
 * Decides which activity types the wizard can offer and how.
 *
 * Every enabled activity type that ships a mod_form.php is built with its
 * own real settings form (embedded by step_activity_settings.php through
 * core's course/modlib.php contract). That is what gives every activity
 * its own fields (URL, file upload, embed code, grading...) plus Moodle's
 * standard sections: restrict access, activity completion, tags and
 * competencies.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class native_form_registry {
    /**
     * Activity types shown first in the chooser, in this order, when installed.
     *
     * @var string[]
     */
    const FEATURED = ['bulkcoursestudio', 'edwiservideoactivity', 'url', 'resource', 'page', 'folder',
        'assign', 'quiz', 'forum', 'h5pactivity'];

    /**
     * Whether an activity type can be built with its real settings form.
     *
     * @param string $modname
     * @return bool
     */
    public static function uses_native_form(string $modname): bool {
        return clean_param($modname, PARAM_PLUGIN) === $modname
            && file_exists(self::mod_form_path($modname));
    }

    /**
     * Path of a module's own mod_form.php.
     *
     * @param string $modname
     * @return string
     */
    public static function mod_form_path(string $modname): string {
        global $CFG;
        return $CFG->dirroot . '/mod/' . $modname . '/mod_form.php';
    }

    /**
     * Activity types the current admin can add to this course, featured
     * ones first and the rest alphabetically.
     *
     * @param int $courseid the build's real (hidden) course
     * @return string[] modname => display name
     */
    public static function get_addable_types(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $context = \context_course::instance($courseid);
        $all = [];
        foreach (get_module_types_names() as $modname => $displayname) {
            if (self::uses_native_form($modname) && has_capability('mod/' . $modname . ':addinstance', $context)) {
                $all[$modname] = $displayname;
            }
        }
        asort($all, SORT_NATURAL | SORT_FLAG_CASE);

        $featured = [];
        foreach (self::FEATURED as $modname) {
            if (isset($all[$modname])) {
                $featured[$modname] = $all[$modname];
                unset($all[$modname]);
            }
        }
        return $featured + $all;
    }
}
