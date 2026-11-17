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
 * English language strings for tool_bulkcoursestudio.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bulk Course Studio';
$string['dashboardplaceholder'] = 'The build wizard and Excel upload screens are not implemented yet in this scaffold.';

// Capabilities.
$string['bulkcoursestudio:manage'] = 'Use Bulk Course Studio to build courses';
$string['bulkcoursestudio:bulkupload'] = 'Download/upload the Bulk Course Studio Excel template';
$string['bulkcoursestudio:deletewithdata'] = 'Permanently delete a section/activity that has student data via Bulk Course Studio';
$string['bulkcoursestudio:schedule'] = 'Schedule a Bulk Course Studio build to go live later';

// Tasks.
$string['task:publishscheduled'] = 'Publish scheduled Bulk Course Studio courses';

// Wizard steps (used once the forms are built).
$string['step:coursedetails'] = 'Course details';
$string['step:format'] = 'Course format';
$string['step:sections'] = 'Sections';
$string['step:activities'] = 'Activities';
$string['step:matchfiles'] = 'Match files';
$string['step:preview'] = 'Preview';
$string['step:commit'] = 'Save / publish / schedule';

// Deletion-choice dialog (spec §3.3).
$string['removechoice:title'] = 'This {$a} has student data';
$string['removechoice:hide'] = 'Hide — stays in the course, data preserved, no longer visible to students. Reversible.';
$string['removechoice:delete'] = 'Delete — permanently removes this {$a} and all associated student data (grades, attempts, completions). This cannot be undone.';
$string['removechoice:cancel'] = 'Cancel — make no change.';

// Messages from build_manager::preview().
$string['preview:error:nofullname'] = 'The course needs a full name.';
$string['preview:error:badcategory'] = 'The chosen category no longer exists on this site.';
$string['preview:error:badformat'] = 'The course format "{$a}" is not installed on this site.';
$string['preview:error:enddatebeforestart'] = 'The course end date must be after the start date.';
$string['preview:error:unknownmod'] = 'Section "{$a->section}" uses an activity type ("{$a->modname}") that is not installed on this site.';
$string['preview:warning:disabledmod'] = 'Section "{$a->section}" uses the "{$a->modname}" activity type, which is currently disabled on this site.';
$string['preview:error:badembedurl'] = 'The embed activity "{$a->activity}" in section "{$a->section}" does not have a recognisable Vimeo, YouTube, Google Drive, or OneDrive URL.';
$string['preview:warning:nosections'] = 'This course has no sections yet.';
$string['preview:warning:missingfile'] = 'Activity "{$a->activity}" in section "{$a->section}" expects a file called "{$a->filename}" but none has been attached yet. Use "Match files" to upload it, or add it from the activity\'s own edit screen.';
$string['committedwitherrors'] = 'This build could not be committed because the preview reported errors: {$a}';

// Wizard form fields / labels.
$string['next'] = 'Next';
$string['courseimage'] = 'Course image';
$string['sectionname'] = 'Section name';
$string['sectionsummary'] = 'Section description';
$string['savesection'] = 'Save section';
$string['activitytype'] = 'Activity type';
$string['activityname'] = 'Activity name';
$string['activitydescription'] = 'Description';
$string['activityfiles'] = 'Files';
$string['advancedsettingsjson'] = 'Advanced settings (JSON)';
$string['advancedsettingsjson_help'] = 'Optional. Extra settings this activity type\'s own "Add an activity" form would normally ask for (e.g. a quiz\'s grading method, an assignment\'s due date), as JSON key/value pairs matching that module\'s field names. Leave blank to use the activity type\'s defaults.';
$string['invalidjson'] = 'This is not valid JSON.';
$string['saveactivity'] = 'Save activity';
$string['addactivity'] = 'Add activity';
$string['completionview'] = 'Student must view this activity to complete it';

// Preview / commit screen.
$string['savedraft'] = 'Save as draft';
$string['publishnow'] = 'Publish now';
$string['schedulelater'] = 'Schedule';
$string['fixerrorsfirst'] = 'Fix the errors above before this can be saved.';
$string['saveddraft'] = 'Saved as a draft.';
$string['published'] = 'Course published.';
$string['scheduled'] = 'Course scheduled to go live on {$a}.';

// Dashboard.
$string['startnewbuild'] = 'Start a new course';
$string['mydrafts'] = 'My drafts and scheduled builds';
$string['nodrafts'] = 'You have no drafts or scheduled builds yet.';
$string['downloadtemplate'] = 'Download Excel template';
$string['uploadtemplate'] = 'Upload a filled-in Excel template';
$string['continuedraft'] = 'Continue';
$string['editexisting'] = 'Edit an existing course';
$string['editexisting:info'] = 'Find any course previously built (or last edited) through Bulk Course Studio, including ones built by another admin, to change its details, sections, or activities. Editing here is always safe for a published course — it only updates content, never visibility; use "Publish now" on the preview screen if you also want to change whether it\'s visible.';
$string['editexisting:searchplaceholder'] = 'Search by course name...';
$string['editexisting:nomatches'] = 'No Bulk Course Studio-built course matches "{$a}".';
$string['editexisting:nocourses'] = 'No courses have been built through Bulk Course Studio yet.';
$string['editexisting:editbutton'] = 'Edit';
$string['activitysettings:nocourseyet'] = 'This build doesn\'t have a course yet — start from course details first.';
$string['discardbuild'] = 'Discard build';
$string['discardbuild:confirm'] = 'Discard this build? Its hidden course (and everything in it) will be moved to the recycle bin — this cannot be undone from here.';
$string['discardbuild:confirmpublished'] = 'Discard this draft record? This build has already been published — its course will NOT be deleted or hidden, only Bulk Course Studio\'s own tracking of it will be removed. Delete the course itself from course management if that\'s what you want.';
$string['builddiscarded'] = 'Build discarded.';
$string['statusdraft'] = 'Draft';
$string['statusscheduled'] = 'Scheduled for {$a}';
$string['statuspublished'] = 'Published';

// Excel flow.
$string['excel:missingsheet'] = 'This file is missing the required "{$a}" sheet. Download a fresh template and re-fill it rather than editing sheet names.';
$string['excel:nocourserow'] = 'The Course sheet has no filled-in course row (only the commented-out example).';
$string['excel:filematchinfo'] = 'If any activity needs an uploaded file (a File or Folder activity), you\'ll be asked to drag in a matching batch of files, by filename, right after this template is processed.';
$string['matchfiles:batchfiles'] = 'Files to match';
$string['matchfiles:matchandcontinue'] = 'Match & continue to preview';
$string['matchfiles:info'] = 'Drop every file named in your template\'s "local_filename" column here in one go. Each uploaded file is matched to its activity by filename (not case-sensitive) — the name in the sheet and the name of the file you upload must be identical.';
$string['matchfiles:col:section'] = 'Section';
$string['matchfiles:col:activity'] = 'Activity';
$string['matchfiles:col:expectedfile'] = 'Expected filename';
$string['matchfiles:col:status'] = 'Status';
$string['matchfiles:matched'] = 'Matched';
$string['matchfiles:unmatched'] = 'Not matched yet';
$string['matchfiles:skiptopreview'] = 'Skip to preview without matching more files';
$string['matchfiles:matchedsome'] = 'Matched {$a} file(s). Any still unmatched are listed as a warning on the preview screen.';
$string['matchfiles:matchednone'] = 'No files were matched — check that the filenames you uploaded exactly match the "local_filename" column in your template.';

// Privacy API.
$string['privacy:metadata:build'] = 'Information about a course you are building or have built with Bulk Course Studio.';
$string['privacy:metadata:build:usermodified'] = 'The ID of the user who last modified this build.';
$string['privacy:metadata:build:coursedata'] = 'The course details entered for this build (name, dates, settings, etc.).';
$string['privacy:metadata:build:timecreated'] = 'The time this build was started.';

// Feature overview page.
$string['overview:link'] = 'See all features';
$string['overview:title'] = 'Bulk Course Studio: feature overview';
$string['overview:intro'] = 'Every screen this plugin provides, in one place, with a direct link to open it.';
$string['overview:open'] = 'Open';
$string['overview:section:admintool'] = 'Admin tool (tool_bulkcoursestudio)';
$string['overview:section:mod'] = 'Companion activity (mod_bulkcoursestudio)';
$string['overview:section:developer'] = 'Developer';
$string['overview:feature:dashboard'] = 'Dashboard';
$string['overview:feature:dashboard_desc'] = 'Start a new build, resume a draft, or jump into one of the tools below.';
$string['overview:feature:wizard'] = 'Course-building wizard';
$string['overview:feature:wizard_desc'] = 'Step through course details, format, sections, and activities to build a course by hand.';
$string['overview:feature:exceltemplate'] = 'Download Excel template';
$string['overview:feature:exceltemplate_desc'] = 'Download a spreadsheet template for describing a course, its sections, and its activities.';
$string['overview:feature:excelupload'] = 'Upload filled-in Excel template';
$string['overview:feature:excelupload_desc'] = 'Upload a completed template to build a course from a spreadsheet instead of the wizard.';
$string['overview:feature:editexisting'] = 'Edit an existing course built with this tool';
$string['overview:feature:editexisting_desc'] = 'Find and reopen any course this tool previously built, to change it further.';
$string['overview:mod_desc'] = 'An embeddable-video activity (YouTube, Vimeo, Google Drive, or OneDrive) with an optional "minimum seconds watched" completion rule. Add it to any course the normal way, from "Add an activity or resource".';
$string['overview:developer:linkedin'] = 'LinkedIn';
$string['overview:developer:github'] = 'GitHub';
$string['overview:developer:email'] = 'Email';
