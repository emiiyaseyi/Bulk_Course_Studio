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
 * Restore structure step for mod_bulkcoursestudio, mirroring the backup
 * step's element tree exactly.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_bulkcoursestudio_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the XML paths this step restores.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('bulkcoursestudio', '/activity/bulkcoursestudio');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('bulkcoursestudio_progress', '/activity/bulkcoursestudio/progresses/progress');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the single bulkcoursestudio instance row.
     *
     * @param array $data
     */
    protected function process_bulkcoursestudio($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        if (empty($data->mediasource)) {
            $data->mediasource = 'url'; // Backups made before v0.2.0.
        }

        $newitemid = $DB->insert_record('bulkcoursestudio', $data);
        // Maps the backup-file id to the new live id so completion/grade
        // restore steps and the progress rows below can resolve it.
        $this->set_mapping('bulkcoursestudio', $oldid, $newitemid, true);

        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one bulkcoursestudio_progress row, when user data is
     * included in this restore.
     *
     * @param array $data
     */
    protected function process_bulkcoursestudio_progress($data) {
        global $DB;

        $data = (object) $data;
        $data->bulkcoursestudioid = $this->get_new_parentid('bulkcoursestudio');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if ($data->userid) {
            $DB->insert_record('bulkcoursestudio_progress', $data);
        }
    }

    /**
     * Rewrite intro-field @@PLUGINFILE@@ links against the restored file
     * area.
     */
    protected function after_execute() {
        // Rewrite intro-field @@PLUGINFILE@@ links against the restored
        // file area, same as every other activity module's intro field.
        $this->add_related_files('mod_bulkcoursestudio', 'intro', null);
        $this->add_related_files('mod_bulkcoursestudio', 'mediafile', null);
        $this->add_related_files('mod_bulkcoursestudio', 'resources', null);
        $this->add_related_files('mod_bulkcoursestudio', 'transcript', null);
    }
}
