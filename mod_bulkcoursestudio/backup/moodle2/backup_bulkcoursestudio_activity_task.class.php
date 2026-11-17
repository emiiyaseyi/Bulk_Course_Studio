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

require_once($CFG->dirroot . '/mod/bulkcoursestudio/backup/moodle2/backup_bulkcoursestudio_stepslib.php');

/**
 * Backup task for a single mod_bulkcoursestudio instance.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_bulkcoursestudio_activity_task extends backup_activity_task {
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
        $this->add_step(new backup_bulkcoursestudio_activity_structure_step(
            'bulkcoursestudio_structure',
            'bulkcoursestudio.xml'
        ));
    }

    /**
     * Rewrite this module's own view-by-id links into backup's portable
     * link-decoding placeholder form.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/') . '/mod/bulkcoursestudio';

        $content = preg_replace(
            '/(' . $base . '\/view\.php\?id=)([0-9]+)/',
            '$@BULKCOURSESTUDIOVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
