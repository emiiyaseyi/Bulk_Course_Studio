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

namespace mod_bulkcoursestudio\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_bulkcoursestudio.
 *
 * Personal data here is the per-user watch-progress table
 * (bulkcoursestudio_progress), used for the optional "minimum seconds
 * watched" completion rule. The instance table itself (bulkcoursestudio)
 * holds admin/teacher-authored settings, not student data.
 *
 * STATUS: skeleton — export/delete bodies are stubs pending full build.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'bulkcoursestudio_progress',
            [
                'userid' => 'privacy:metadata:progress:userid',
                'secondswatched' => 'privacy:metadata:progress:secondswatched',
                'currenttime' => 'privacy:metadata:progress:currenttime',
                'duration' => 'privacy:metadata:progress:duration',
                'watchpercent' => 'privacy:metadata:progress:percent',
                'provider' => 'privacy:metadata:progress:provider',
                'timemodified' => 'privacy:metadata:progress:timemodified',
            ],
            'privacy:metadata:progress'
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
        // TODO MDL-0: join bulkcoursestudio_progress to bulkcoursestudio,
        // course and course_modules to find this user's module contexts.
        return new contextlist();
    }

    /**
     * All users with personal data within a given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        // TODO MDL-0: populate $userlist with userids from
        // bulkcoursestudio_progress for the activity in
        // $userlist->get_context().
    }

    /**
     * Export personal data for an approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // TODO MDL-0: writer::with_context($context)->export_data(...) for
        // each matching bulkcoursestudio_progress row.
    }

    /**
     * Delete all personal data for all users in a given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // TODO MDL-0: delete all bulkcoursestudio_progress rows for this
        // module context.
    }

    /**
     * Delete all personal data for one user, for an approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // TODO MDL-0: delete this user's bulkcoursestudio_progress rows in
        // the given contexts.
    }

    /**
     * Delete personal data for multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // TODO MDL-0: delete the named users' bulkcoursestudio_progress
        // rows for this one context.
    }
}
