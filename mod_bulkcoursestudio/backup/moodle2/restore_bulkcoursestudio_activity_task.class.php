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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bulkcoursestudio/backup/moodle2/restore_bulkcoursestudio_stepslib.php');

/**
 * Restore task for a single mod_bulkcoursestudio instance.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_bulkcoursestudio_activity_task extends restore_activity_task {
    /**
     * Define this task's settings. None beyond the standard activity-level
     * ones.
     */
    protected function define_my_settings() {
        // No module-specific settings beyond the standard activity-level ones.
    }

    /**
     * Define this task's steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_bulkcoursestudio_activity_structure_step(
            'bulkcoursestudio_structure',
            'bulkcoursestudio.xml'
        ));
    }

    /**
     * File areas whose content needs link decoding after restore.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('bulkcoursestudio', ['intro'], 'bulkcoursestudio'),
        ];
    }

    /**
     * Decoding rules for links pointing at this module.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('BULKCOURSESTUDIOVIEWBYID', '/mod/bulkcoursestudio/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * File areas used by this module.
     *
     * @return array
     */
    public function get_fileareas() {
        return ['intro'];
    }

    /**
     * Config fields in this module that hold base64-encoded data.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }
}
