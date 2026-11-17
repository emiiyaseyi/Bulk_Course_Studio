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
 * Defines the backup structure step for mod_bulkcoursestudio — required for
 * marketplace approval (every activity module must support backup/restore)
 * and so a course built with Bulk Course Studio behaves exactly like any
 * other course under course backup, course import, and course copy.
 *
 * Modeled on core's own single-instance activity modules (e.g. mod_page):
 * one top-level <bulkcoursestudio> element for the instance row, with a
 * nested, userinfo-gated <progresses> collection for the per-user watch
 * data in bulkcoursestudio_progress — excluded unless the backup explicitly
 * includes user data, same as a quiz attempt or a forum post would be.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_bulkcoursestudio_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup XML element tree for this module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $bulkcoursestudio = new backup_nested_element('bulkcoursestudio', ['id'], [
            'name', 'intro', 'introformat', 'embedurl', 'provider', 'mediasource',
            'completionview', 'completionminseconds', 'timecreated', 'timemodified',
        ]);

        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'secondswatched', 'timemodified',
        ]);

        $bulkcoursestudio->add_child($progresses);
        $progresses->add_child($progress);

        $bulkcoursestudio->set_source_table('bulkcoursestudio', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $progress->set_source_table('bulkcoursestudio_progress', ['bulkcoursestudioid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
        }

        $bulkcoursestudio->annotate_files('mod_bulkcoursestudio', 'intro', null);
        $bulkcoursestudio->annotate_files('mod_bulkcoursestudio', 'mediafile', null);
        $bulkcoursestudio->annotate_files('mod_bulkcoursestudio', 'resources', null);

        return $this->prepare_activity_structure($bulkcoursestudio);
    }
}
