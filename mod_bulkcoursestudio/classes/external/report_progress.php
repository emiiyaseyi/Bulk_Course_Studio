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
 * AJAX endpoint the video player calls to save how far a learner has
 * watched, and to mark the activity complete once the required percentage
 * is reached.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_progress extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'currenttime' => new external_value(PARAM_INT, 'Current playback position in seconds'),
            'duration' => new external_value(PARAM_INT, 'Video length in seconds'),
            'percent' => new external_value(PARAM_INT, 'Furthest point reached, as a percentage'),
            'provider' => new external_value(PARAM_ALPHANUMEXT, 'html5, youtube or vimeo', VALUE_DEFAULT, ''),
            'timespent' => new external_value(PARAM_INT, 'Seconds spent with the activity open', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save progress for the current user.
     *
     * @param int $cmid
     * @param int $currenttime
     * @param int $duration
     * @param int $percent
     * @param string $provider
     * @param int $timespent
     * @return array
     */
    public static function execute(
        int $cmid,
        int $currenttime,
        int $duration,
        int $percent,
        string $provider = '',
        int $timespent = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'currenttime' => $currenttime,
            'duration' => $duration,
            'percent' => $percent,
            'provider' => $provider,
            'timespent' => $timespent,
        ]);

        $cm = get_coursemodule_from_id('bulkcoursestudio', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/bulkcoursestudio:view', $context);

        $instance = $DB->get_record('bulkcoursestudio', ['id' => $cm->instance], '*', MUST_EXIST);

        $currenttime = max(0, $params['currenttime']);
        $duration = max(0, $params['duration']);
        $percent = min(100, max(0, $params['percent']));

        $existing = $DB->get_record('bulkcoursestudio_progress', [
            'bulkcoursestudioid' => $instance->id,
            'userid' => $USER->id,
        ]);

        // Progress only moves forward: skipping back in the player must not
        // lower what the learner has already earned.
        $furthest = max($percent, (int) ($existing->watchpercent ?? 0));

        $row = (object) [
            'bulkcoursestudioid' => $instance->id,
            'userid' => $USER->id,
            'secondswatched' => max($currenttime, (int) ($existing->secondswatched ?? 0)),
            'timespent' => max(max(0, $params['timespent']), (int) ($existing->timespent ?? 0)),
            'currenttime' => $currenttime,
            'duration' => $duration,
            'watchpercent' => $furthest,
            'provider' => $params['provider'],
            'timemodified' => time(),
        ];
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('bulkcoursestudio_progress', $row);
        } else {
            $DB->insert_record('bulkcoursestudio_progress', $row);
        }

        // Let Moodle re-check every completion rule (view, percent, time)
        // itself; it marks complete only when all enabled rules are met.
        $completed = false;
        $completioninfo = new completion_info(get_course($cm->course));
        if ($completioninfo->is_enabled($cm)) {
            $completioninfo->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
            $state = $completioninfo->get_data($cm, false, $USER->id)->completionstate;
            $completed = in_array((int) $state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
        }

        return ['percent' => $furthest, 'completiontriggered' => $completed];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_INT, 'Stored furthest percentage watched'),
            'completiontriggered' => new external_value(PARAM_BOOL, 'Whether this call just marked the activity complete'),
        ]);
    }
}
