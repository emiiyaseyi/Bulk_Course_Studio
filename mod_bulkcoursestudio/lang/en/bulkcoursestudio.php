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

$string['modulename'] = 'Bulk Course Studio';
$string['modulenameplural'] = 'Bulk Course Studio';
$string['reset:progress'] = 'Delete all watch-progress data';
$string['modulename_help'] = 'Adds a video to your course: a YouTube, Vimeo, Loom, Google Drive or OneDrive link, a direct video link, or an uploaded file. Learners watch it in a pop-up player, and you can require a percentage watched or time spent for completion.';
$string['pluginname'] = 'Bulk Course Studio';
$string['pluginadministration'] = 'Bulk Course Studio administration';

$string['activityname'] = 'Activity name';
$string['embedsection'] = 'Video / media link';
$string['embedurl'] = 'Video URL';
$string['embedurl_help'] = 'Paste a Vimeo, YouTube, Google Drive, or OneDrive share link. Students see a launch button on the activity page and the video opens in a pop-up window — it is never shown inline on the page.';
$string['openembed'] = 'Open video';

$string['embednotice:sharingrequired'] = 'Google Drive links must be shared as "Anyone with the link can view". OneDrive/SharePoint: use Share, then Embed, and paste the embed link (a normal share link is blocked by Microsoft).';
$string['embedunresolvable'] = 'This video link could not be resolved into a playable embed. Edit this activity and check the Video URL field.';


$string['bulkcoursestudio:addinstance'] = 'Add a new Bulk Course Studio activity';
$string['bulkcoursestudio:view'] = 'View a Bulk Course Studio activity';

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

// Added in v1.4: time-spent completion, OneDrive guidance.
$string['completiontimespent'] = 'Learner must spend at least';
$string['completiontimespentgroup'] = 'Time on the activity';
$string['completiontimespentgroup_help'] = 'The learner must keep the video open (with this browser tab visible) for at least this many minutes. It works for every video source, including Google Drive, OneDrive and Loom.';
$string['completiontimespent_error'] = 'Enter a number of minutes from 1 to 1440.';
$string['completiondetail:timespent'] = 'Spend at least {$a} minutes on the activity';
$string['privacy:metadata:progress:timespent'] = 'How many seconds the user kept the video open.';
$string['onedriveneedsembed'] = 'This OneDrive/SharePoint link cannot be shown inside Moodle, because Microsoft blocks normal share links from being embedded. In OneDrive or SharePoint choose Share, then Embed, and paste the link from the iframe (it contains embed.aspx or onedrive.live.com/embed). Or upload the video here instead.';

// Added in v1.6: Google Drive percentage tracking, copy a video in from a link.
$string['googleapikey'] = 'Google API key (optional)';
$string['googleapikey_desc'] = 'Lets Google Drive videos play in the browser\'s own player, so "watch percentage" completion works for Drive links. Create a free key in Google Cloud Console (enable the Google Drive API, then Credentials > API key) and restrict it to your Moodle site address. Leave empty to keep the normal Drive preview, which cannot report how much was watched. The file must be shared as "Anyone with the link can view".';
$string['mediasource:import'] = 'Copy a video into Moodle from a link (Drive, OneDrive/SharePoint "Anyone" link, direct link)';
$string['mediasource:importinfo'] = 'Paste a public link. Moodle downloads the video (up to 100 MB) and keeps its own copy, so it plays for everyone and watch percentage works. The link must work without signing in: use "Anyone with the link can view".';
$string['importfailed:notalink'] = 'Enter a link starting with https://.';
$string['importfailed:unreachable'] = 'Moodle could not download from this link. Check that it opens without signing in and that the server is allowed to reach it.';
$string['importfailed:notpublic'] = 'This link returned a web page instead of a video, usually because it asks you to sign in. Share the file as "Anyone with the link can view", or upload the video instead.';
$string['importfailed:toolarge'] = 'This video is larger than the {$a} limit. Use a link instead, or a shorter video.';
