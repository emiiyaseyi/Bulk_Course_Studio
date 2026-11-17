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
 * Web service / AJAX external function declarations for mod_bulkcoursestudio.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_bulkcoursestudio_report_progress' => [
        'classname' => 'mod_bulkcoursestudio\external\report_progress',
        'methodname' => 'execute',
        'description' => 'Report how many seconds of an embedded video the current user has watched, and mark '
            . 'completion when the modal is closed or the required watch time is reached.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
