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
 * Version details for tool_bulkcoursestudio.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_bulkcoursestudio';
$plugin->version   = 2026111703; // YYYYMMDDXX.
$plugin->requires  = 2024100700; // Moodle 4.5.0 build.
$plugin->maturity  = MATURITY_ALPHA; // Raise to BETA/STABLE as the plugin matures.
$plugin->release   = 'v1.3';

// Mod_bulkcoursestudio is a companion component, not a hard Moodle dependency,
// so it is intentionally NOT declared in $plugin->dependencies: this tool must
// keep working (minus the embed activity type) even if the admin hasn't
// installed the companion module.
