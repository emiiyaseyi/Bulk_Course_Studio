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

namespace mod_bulkcoursestudio\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/completionlib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use completion_info;

/**
 * AJAX endpoint called from amd/src/launcher.js while the pop-up player is
 * open (throttled, e.g. every ~10s) and once when the modal closes. Persists
 * the user's furthest-watched point and, once the configured
 * completionminseconds threshold is met, marks the activity complete via the
 * core completion API — never by writing to the completion tables directly.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_progress extends external_api {
    /**
     * Describe this external function's parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'secondswatched' => new external_value(PARAM_INT, 'Furthest playback position reached, in seconds'),
        ]);
    }

    /**
     * Record the user's furthest playback position and, once the
     * configured threshold is met, mark the activity complete.
     *
     * @param int $cmid
     * @param int $secondswatched
     * @return array
     */
    public static function execute(int $cmid, int $secondswatched): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'secondswatched' => $secondswatched,
        ]);

        $cm = get_coursemodule_from_id('bulkcoursestudio', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/bulkcoursestudio:view', $context);

        $instance = $DB->get_record('bulkcoursestudio', ['id' => $cm->instance], '*', MUST_EXIST);

        $existing = $DB->get_record('bulkcoursestudio_progress', [
            'bulkcoursestudioid' => $instance->id,
            'userid' => $USER->id,
        ]);

        // Only ever move forward — a user skipping back in the player
        // should not reset their recorded progress.
        $furthest = max($params['secondswatched'], $existing->secondswatched ?? 0);

        if ($existing) {
            $DB->update_record('bulkcoursestudio_progress', (object) [
                'id' => $existing->id,
                'secondswatched' => $furthest,
                'timemodified' => time(),
            ]);
        } else {
            $DB->insert_record('bulkcoursestudio_progress', (object) [
                'bulkcoursestudioid' => $instance->id,
                'userid' => $USER->id,
                'secondswatched' => $furthest,
                'timemodified' => time(),
            ]);
        }

        $completed = false;
        $course = get_course($cm->course);
        $completioninfo = new completion_info($course);

        if ($completioninfo->is_enabled($cm) && !empty($instance->completionminseconds)) {
            if ($furthest >= $instance->completionminseconds) {
                $completioninfo->update_state($cm, COMPLETION_COMPLETE, $USER->id);
                $completed = true;
            }
        }

        return [
            'secondswatched' => $furthest,
            'completiontriggered' => $completed,
        ];
    }

    /**
     * Describe this external function's return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'secondswatched' => new external_value(PARAM_INT, 'Stored furthest playback position, in seconds'),
            'completiontriggered' => new external_value(PARAM_BOOL, 'Whether this call just marked the activity complete'),
        ]);
    }
}
