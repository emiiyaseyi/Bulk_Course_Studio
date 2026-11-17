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

namespace tool_bulkcoursestudio\task;

/**
 * Scheduled task: promote builds whose scheduled go-live time has passed.
 *
 * Deliberately thin: all the real work (flipping course.visible, any
 * start-date adjustment) goes through core course APIs, never direct SQL,
 * so the result is identical to an admin doing it manually in Moodle.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_scheduled extends \core\task\scheduled_task {
    /**
     * Get the task's human-readable name, shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:publishscheduled', 'tool_bulkcoursestudio');
    }

    /**
     * Publish every build whose scheduled go-live time has passed.
     */
    public function execute() {
        global $DB;

        $now = time();
        $due = $DB->get_records_select(
            'tool_bulkcoursestudio_build',
            'status = :status AND scheduledtime IS NOT NULL AND scheduledtime <= :now',
            ['status' => 'scheduled', 'now' => $now]
        );

        foreach ($due as $build) {
            \tool_bulkcoursestudio\build_manager::publish($build->id);
        }
    }
}
