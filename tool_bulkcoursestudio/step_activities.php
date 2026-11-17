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
 * Wizard step 4: activities. Lists every section in the build with its
 * activities, and lets the admin add one. For a v1-scope type (spec §8:
 * assign, quiz, forum, page, url, resource, folder), picking it here hands
 * straight off to step_activity_settings.php, which embeds that module's
 * real native mod_form.php. For mod_bulkcoursestudio and anything outside
 * that scope, the fields stay on activity_form (name/description/
 * completion plus an "Advanced settings (JSON)" fallback).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\native_form_registry;
use tool_bulkcoursestudio\form\activity_form;

$buildid = required_param('buildid', PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

draft_repository::get_build($buildid); // Existence/ownership check.
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:activities', 'tool_bulkcoursestudio'));

if ($action === 'next') {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_preview.php', ['buildid' => $buildid]));
}

$sections = draft_repository::get_sections($buildid);

// The add/edit activity form only renders once a section is selected.
if ($sectionid) {
    global $DB;
    $section = $DB->get_record('tool_bulkcoursestudio_section', ['id' => $sectionid, 'buildid' => $buildid], '*', MUST_EXIST);

    $form = new activity_form(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', [
        'buildid' => $buildid, 'sectionid' => $sectionid, 'activityid' => $activityid,
    ]));

    // Files are stored in this plugin's own draft area under component
    // tool_bulkcoursestudio, filearea activityfiles, itemid = the draft
    // activity's id (spec §3.1 step 1a's filemanager pattern). A
    // not-yet-created activity has no id yet, so the filemanager field is
    // keyed to a plain submitted-draft itemid on first display and only
    // re-keyed to the real activity id once add_or_update_activity() below
    // returns one — the same two-step dance step_coursedetails.php uses for
    // the course image, which is the standard way Moodle handles a
    // filemanager field on an entity that doesn't exist until the form
    // that creates it is submitted.
    $filesdraftitemid = file_get_submitted_draft_itemid('activityfiles');

    if ($activityid) {
        $existing = $DB->get_record(
            'tool_bulkcoursestudio_activity',
            ['id' => $activityid, 'sectionid' => $sectionid],
            '*',
            MUST_EXIST
        );
        $moduledata = json_decode($existing->moduledata, true) ?? [];
        $completiondata = json_decode($existing->completiondata, true) ?? [];
        file_prepare_draft_area(
            $filesdraftitemid,
            context_system::instance()->id,
            'tool_bulkcoursestudio',
            'activityfiles',
            $activityid
        );
        $form->set_data((object) array_merge([
            'sectionid' => $sectionid,
            'activityid' => $activityid,
            'modname' => $existing->modname,
            'name' => $existing->name,
            'intro_editor' => ['text' => $moduledata['intro'] ?? '', 'format' => FORMAT_HTML],
            'embedurl' => $moduledata['embedurl'] ?? '',
            'activityfiles' => $filesdraftitemid,
            'advancedsettingsjson' => json_encode(
                array_diff_key($moduledata, array_flip(['intro', 'embedurl'])),
                JSON_PRETTY_PRINT
            ),
        ], $completiondata));
    } else {
        $form->set_data((object) ['sectionid' => $sectionid, 'activityid' => 0, 'activityfiles' => $filesdraftitemid]);
    }

    if ($data = $form->get_data()) {
        if (native_form_registry::uses_native_form($data->modname)) {
            // Just enough of a draft row to hand off to
            // step_activity_settings.php, which collects this module's own
            // real settings (including its own 'name' field) — the name
            // typed here is kept only as a placeholder until that happens.
            $newactivityid = draft_repository::add_or_update_activity(
                $sectionid,
                $data->activityid ?: null,
                $data->modname,
                $data->name,
                [],
                []
            );
            redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activity_settings.php', [
                'buildid' => $buildid, 'sectionid' => $sectionid, 'modname' => $data->modname, 'activityid' => $newactivityid,
            ]));
        }

        $moduledata = ['intro' => $data->intro_editor['text'] ?? ''];
        if ($data->modname === 'bulkcoursestudio') {
            $moduledata['embedurl'] = $data->embedurl;
        }
        if (!empty($data->advancedsettingsjson)) {
            $extra = json_decode($data->advancedsettingsjson, true) ?? [];
            $moduledata = array_merge($moduledata, $extra);
        }

        $completiondata = ['completionview' => $data->completionview ?? 0];
        if ($data->modname === 'bulkcoursestudio' && !empty($data->completionminseconds)) {
            $completiondata['completionminsecondsenabled'] = 1;
            $completiondata['completionminseconds'] = $data->completionminseconds;
        }

        $savedactivityid = draft_repository::add_or_update_activity(
            $sectionid,
            $data->activityid ?: null,
            $data->modname,
            $data->name,
            $moduledata,
            $completiondata
        );

        // Now that the activity definitely has an id, move its files out
        // of the submitted draft area into this plugin's permanent draft
        // storage, keyed by that id. build_manager::commit_activity() is
        // what copies them onward into the real module's own file area —
        // this plugin never writes into mod_resource/mod_folder's file
        // area directly.
        file_save_draft_area_files(
            $data->activityfiles,
            context_system::instance()->id,
            'tool_bulkcoursestudio',
            'activityfiles',
            $savedactivityid
        );

        redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('step:activities', 'tool_bulkcoursestudio'));

foreach ($sections as $section) {
    echo $OUTPUT->heading(format_string($section->name), 4);
    $activities = draft_repository::get_activities($section->id);

    echo html_writer::start_tag('ul');
    foreach ($activities as $activity) {
        $editurl = native_form_registry::uses_native_form($activity->modname)
            ? new moodle_url('/admin/tool/bulkcoursestudio/step_activity_settings.php', [
                'buildid' => $buildid, 'sectionid' => $section->id, 'modname' => $activity->modname, 'activityid' => $activity->id,
            ])
            : new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', [
                'buildid' => $buildid, 'sectionid' => $section->id, 'activityid' => $activity->id,
            ]);
        echo html_writer::tag('li', format_string($activity->name) . ' (' . s($activity->modname) . ') '
            . html_writer::link($editurl, get_string('edit')));
    }
    echo html_writer::end_tag('ul');

    $addurl = new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', [
        'buildid' => $buildid, 'sectionid' => $section->id,
    ]);
    echo html_writer::link($addurl, get_string('addactivity', 'tool_bulkcoursestudio'), ['class' => 'btn btn-secondary mb-3']);

    if ($sectionid == $section->id) {
        $form->display();
    }
}

$nexturl = new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid, 'action' => 'next']);
echo html_writer::link($nexturl, get_string('step:preview', 'tool_bulkcoursestudio'), ['class' => 'btn btn-primary']);

echo $OUTPUT->footer();
