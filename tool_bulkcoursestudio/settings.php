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
 * Admin settings link for tool_bulkcoursestudio — points at the plugin's own
 * dashboard page rather than a plain settings form, since this tool is a
 * workflow, not a set of config toggles.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'tool_bulkcoursestudio',
        get_string('pluginname', 'tool_bulkcoursestudio'),
        new moodle_url('/admin/tool/bulkcoursestudio/index.php'),
        'tool/bulkcoursestudio:manage'
    ));
    $ADMIN->add('courses', new admin_externalpage(
        'tool_bulkcoursestudio_overview',
        get_string('overview:title', 'tool_bulkcoursestudio'),
        new moodle_url('/admin/tool/bulkcoursestudio/overview.php'),
        'tool/bulkcoursestudio:manage'
    ));
}
