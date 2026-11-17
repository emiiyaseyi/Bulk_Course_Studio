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

namespace tool_bulkcoursestudio\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Privacy provider for tool_bulkcoursestudio.
 *
 * Only draft/build metadata (tool_bulkcoursestudio_build/_section/_activity)
 * is personal data here — it's keyed to usermodified (the admin who built the
 * course), at CONTEXT_SYSTEM since builds aren't tied to a specific course
 * until commit. No student data lives in this component at all; any student
 * completion/view data belongs to mod_bulkcoursestudio instead.
 *
 * STATUS: skeleton — export/delete bodies are stubs pending full build.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'tool_bulkcoursestudio_build',
            [
                'usermodified' => 'privacy:metadata:build:usermodified',
                'coursedata' => 'privacy:metadata:build:coursedata',
                'timecreated' => 'privacy:metadata:build:timecreated',
            ],
            'privacy:metadata:build'
        );
        return $collection;
    }

    /**
     * All contexts that contain personal data for a given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Builds live at CONTEXT_SYSTEM; include it if this user authored any.
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export personal data for an approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // TODO MDL-0: export tool_bulkcoursestudio_build rows where
        // usermodified equals the contextlist's user id.
    }

    /**
     * Delete all personal data for all users in a given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // TODO MDL-0: only meaningful at CONTEXT_SYSTEM for this plugin.
    }

    /**
     * Delete all personal data for one user, for an approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // TODO MDL-0: delete or anonymise tool_bulkcoursestudio_build rows
        // for this user. Published courses themselves are not personal data
        // of the builder and are out of scope here; only the draft/build
        // bookkeeping is.
    }
}
