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

/**
 * Library functions for tool_bulkcoursestudio.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend the site administration tree with a "Bulk Course Studio" entry
 * under Courses, per Moodle's standard tool_ admin-page convention.
 *
 * @param \settings_navigation $settingsnav
 * @param \context $context
 */
function tool_bulkcoursestudio_extend_settings_navigation($settingsnav, $context) {
    // Full nav wiring is added once the dashboard page (index.php) exists;
    // left as a documented stub for now so the hook point is visible to
    // reviewers from the first commit.
}
