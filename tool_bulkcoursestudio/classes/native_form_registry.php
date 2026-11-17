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
 * Which activity types get their real, native mod_form.php embedded in the
 * wizard (step_activity_settings.php) versus the shared generic form with
 * the "Advanced settings (JSON)" fallback (activity_form.php).
 *
 * Scoped to exactly the v1 activity-type list from the project spec (§8):
 * assign, quiz, forum, page, url, resource, folder. Anything outside that
 * list still works end-to-end through the generic form's JSON fallback —
 * build_manager::commit_activity() passes moduledata through to
 * add_moduleinfo()/update_moduleinfo() unchanged either way, so this list
 * only decides which types get a friendlier settings UI, never which
 * types are functionally supported.
 *
 * mod_bulkcoursestudio is deliberately excluded even though it's v1-scope:
 * it's this plugin's own companion module, already has a first-class
 * "embed URL" field directly in activity_form.php, and its own settings
 * are trivial enough that native-form embedding would add complexity
 * without adding capability.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class native_form_registry {
    /**
     * The v1-scope activity types that get a real, native mod_form.php
     * embedded in the wizard instead of the generic JSON-fallback form.
     *
     * @var string[]
     */
    const NATIVE_FORM_MODNAMES = ['assign', 'quiz', 'forum', 'page', 'url', 'resource', 'folder'];

    /**
     * Whether a given activity type should use its real mod_form.php in the
     * wizard rather than the generic JSON-fallback form.
     *
     * @param string $modname
     * @return bool
     */
    public static function uses_native_form(string $modname): bool {
        return in_array($modname, self::NATIVE_FORM_MODNAMES, true)
            && file_exists(self::mod_form_path($modname));
    }

    /**
     * Guards against a v1-scope type being uninstalled/disabled on this
     * particular site — falls back to the generic form rather than a fatal
     * require_once() on a missing file.
     */
    public static function mod_form_path(string $modname): string {
        global $CFG;
        return $CFG->dirroot . '/mod/' . $modname . '/mod_form.php';
    }
}
