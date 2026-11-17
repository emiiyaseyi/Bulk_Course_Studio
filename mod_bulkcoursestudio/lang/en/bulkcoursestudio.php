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


$string['bulkcoursestudio:addinstance'] = 'Add a new Bulk Course Studio embed';
$string['bulkcoursestudio:view'] = 'View a Bulk Course Studio embed';

$string['privacy:metadata:progress'] = 'Your watch progress on a Bulk Course Studio embedded video, used for completion tracking.';
$string['privacy:metadata:progress:userid'] = 'The ID of the user whose progress this is.';
$string['privacy:metadata:progress:secondswatched'] = 'How many seconds of the video this user has watched.';
$string['privacy:metadata:progress:timemodified'] = 'When this progress record was last updated.';

// Added in v0.2.0: upload a video file, attach resources.
$string['mediasource'] = 'Video source';
$string['mediasource:url'] = 'Add a link (YouTube, Vimeo, Google Drive, OneDrive)';
$string['mediasource:upload'] = 'Upload a video file';
$string['mediafile'] = 'Video file';
$string['mediafilerequired'] = 'Please upload a video file.';
$string['overview'] = 'Overview';
$string['resourcesheader'] = 'Resources';
$string['resourcesdesc'] = 'Attach supporting files (slides, worksheets, notes) that learners can download alongside the video.';
$string['resourcesfiles'] = 'Resource files';
$string['resourcesview'] = 'Resources';
$string['opencomputerfile'] = 'Open video';
$string['mediasource:uploadinfo'] = 'Upload an MP4, WebM, M4V, MOV or 3GP file, up to {$a}.';
$string['mediasource:uploadwarning'] = 'Not recommended for large files: big videos quickly fill server storage and slow the site down. The limit is {$a}. For longer recordings, add a link instead.';
$string['mediafiletoolarge'] = 'This video is too large. The maximum upload size is {$a}. Use a link for longer videos.';

// Added in v1.3: percentage completion, more sources, tabs, auto-advance.
$string['mediasource:direct'] = 'Direct link to a video file (.mp4, .webm...)';
$string['mediasource:urlinfo'] = 'Paste a YouTube, Vimeo, Loom, Google Drive or OneDrive/SharePoint link.';
$string['mediasource:directinfo'] = 'Paste a link that goes straight to a video file, for example https://example.com/lesson.mp4.';
$string['directurlinvalid'] = 'This is not a direct link to a video file. It must start with https:// and end in .mp4, .webm, .m4v, .mov, .3gp or .ogv.';
$string['autonext'] = 'Go to the next activity when the video ends';
$string['autonext_help'] = 'When the video finishes, a short "coming up next" countdown appears and the learner is taken to the next activity in the course. They can cancel it.';
$string['transcriptheader'] = 'Transcript';
$string['transcriptdesc'] = 'Optional text version of the video, for accessibility. Shown in the Transcript tab.';
$string['transcriptfile'] = 'Transcript file';
$string['transcriptview'] = 'Transcript';
$string['nooverview'] = 'No description has been added for this video.';
$string['comingupnext'] = 'Coming up next';
$string['resumevideo'] = 'Resume video';
$string['watchedpercent'] = '{$a}% watched';
$string['completionpercentgroup'] = 'Watch percentage';
$string['completionpercentgroup_help'] = 'Learners must watch at least this percentage of the video for the activity to be marked complete. It works for uploaded videos, direct video links, YouTube and Vimeo. Google Drive, OneDrive and Loom do not share playback progress, so use "view" completion for those.';
$string['completionpercent'] = 'Learner must watch at least';
$string['completionpercent_error'] = 'Enter a percentage from 1 to 100.';
$string['completionpercent_unsupported'] = 'Percentage completion needs an uploaded video, a direct video link, YouTube or Vimeo. This link type cannot report how much was watched.';
$string['completiondetail:percent'] = 'Watch at least {$a}% of the video';
$string['privacy:metadata:progress:currenttime'] = 'The last playback position, used to resume the video.';
$string['privacy:metadata:progress:duration'] = 'The length of the video in seconds.';
$string['privacy:metadata:progress:percent'] = 'The furthest point of the video the user has watched, as a percentage.';
$string['privacy:metadata:progress:provider'] = 'Which player the video was watched in (for example YouTube).';
