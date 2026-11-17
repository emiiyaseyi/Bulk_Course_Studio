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
 * English language strings for mod_bulkcoursestudio.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Bulk Course Studio embed';
$string['modulenameplural'] = 'Bulk Course Studio embeds';
$string['reset:progress'] = 'Delete all watch-progress data';
$string['modulename_help'] = 'Embeds a Vimeo, YouTube, Google Drive, or OneDrive video. Students open it in a pop-up player from the activity page.';
$string['pluginname'] = 'Bulk Course Studio embed';
$string['pluginadministration'] = 'Bulk Course Studio embed administration';

$string['activityname'] = 'Activity name';
$string['embedsection'] = 'Video / media link';
$string['embedurl'] = 'Video URL';
$string['embedurl_help'] = 'Paste a Vimeo, YouTube, Google Drive, or OneDrive share link. Students see a launch button on the activity page and the video opens in a pop-up window — it is never shown inline on the page.';
$string['openembed'] = 'Open video';

$string['embednotice:sharingrequired'] = 'Google Drive and OneDrive links must be set to "Anyone with the link can view" or students will not be able to open the video.';
$string['embedunresolvable'] = 'This video link could not be resolved into a playable embed. Edit this activity and check the Video URL field.';

$string['completionminseconds'] = 'Student must watch for at least';
$string['completiondetail:minseconds'] = 'Watch for at least {$a} seconds';

$string['bulkcoursestudio:addinstance'] = 'Add a new Bulk Course Studio embed';
$string['bulkcoursestudio:view'] = 'View a Bulk Course Studio embed';

$string['privacy:metadata:progress'] = 'Your watch progress on a Bulk Course Studio embedded video, used for completion tracking.';
$string['privacy:metadata:progress:userid'] = 'The ID of the user whose progress this is.';
$string['privacy:metadata:progress:secondswatched'] = 'How many seconds of the video this user has watched.';
$string['privacy:metadata:progress:timemodified'] = 'When this progress record was last updated.';
