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
 * Upgrade steps for mod_bulkcoursestudio.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_bulkcoursestudio_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111701) {
        // New "mediasource" field: existing activities all use a pasted link.
        $table = new xmldb_table('bulkcoursestudio');
        $field = new xmldb_field('mediasource', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'url', 'provider');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026111701, 'bulkcoursestudio');
    }

    if ($oldversion < 2026111703) {
        // Percentage completion, auto-advance, and richer progress tracking.
        $table = new xmldb_table('bulkcoursestudio');
        $fields = [
            new xmldb_field('completionpercent', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'completionminseconds'),
            new xmldb_field('autonext', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'completionpercent'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('bulkcoursestudio_progress');
        $fields = [
            new xmldb_field('currenttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'secondswatched'),
            new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'currenttime'),
            new xmldb_field('watchpercent', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'duration'),
            new xmldb_field('provider', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'watchpercent'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026111703, 'bulkcoursestudio');
    }

    if ($oldversion < 2026111704) {
        // Time-spent completion rule.
        $table = new xmldb_table('bulkcoursestudio');
        $field = new xmldb_field('completiontimespent', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'autonext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('bulkcoursestudio_progress');
        $field = new xmldb_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'secondswatched');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026111704, 'bulkcoursestudio');
    }

    if ($oldversion < 2026111708) {
        // Show the video on the page (default) or in a pop-up.
        $table = new xmldb_table('bulkcoursestudio');
        $field = new xmldb_field('displaymode', XMLDB_TYPE_CHAR, '6', null, XMLDB_NOTNULL, null, 'inline', 'mediasource');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026111708, 'bulkcoursestudio');
    }

    return true;
}
