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
 * Flow B step 2 (spec §3.2): after an uploaded Excel template is parsed,
 * any resource/folder activity row that named a "local_filename" needs the
 * actual file attached before preview. The admin drops every matching file
 * into one filemanager here; on submit we match each uploaded file's own
 * filename (case-insensitively) against every activity still waiting for
 * one, and copy matches straight into that activity's own
 * tool_bulkcoursestudio/activityfiles draft area — the exact same area
 * step_activities.php's own per-activity filemanager writes to, so
 * build_manager::commit_activity_files() needs no Flow-B-specific code.
 *
 * Skipped automatically (redirects straight to step_preview.php) when the
 * build has no activities expecting a file, so Flow B stays a single extra
 * click for templates that don't use file-based activities at all.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\output\ui;
use tool_bulkcoursestudio\form\matchfiles_form;

$buildid = required_param('buildid', PARAM_INT);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_matchfiles.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:matchfiles', 'tool_bulkcoursestudio'));

$previewurl = new moodle_url('/admin/tool/bulkcoursestudio/step_preview.php', ['buildid' => $buildid]);

$needing = draft_repository::get_activities_needing_files($buildid);
if (empty($needing)) {
    // Nothing in this build expects a matched file — don't make the admin
    // click through an empty screen.
    redirect($previewurl);
}

$form = new matchfiles_form(
    new moodle_url('/admin/tool/bulkcoursestudio/step_matchfiles.php', ['buildid' => $buildid]),
    ['buildid' => $buildid]
);

if ($form->is_cancelled()) {
    redirect($previewurl);
} else if ($data = $form->get_data()) {
    $fs = get_file_storage();
    $systemcontext = context_system::instance();

    // The filemanager field's draft files live in the *user's* draft area
    // under $data->batchfiles (its draftitemid) until we read them here —
    // read-only, since this isn't this field's permanent home.
    $batchfiles = $fs->get_area_files(
        context_user::instance($USER->id)->id,
        'user',
        'draft',
        $data->batchfiles,
        'filename',
        false
    );

    $matchedcount = 0;
    foreach ($needing as $pending) {
        if ($pending->hasfile) {
            continue;
        }
        foreach ($batchfiles as $uploaded) {
            if ($uploaded->is_directory()) {
                continue;
            }
            if (strcasecmp($uploaded->get_filename(), $pending->localfilename) !== 0) {
                continue;
            }
            // Clear any partial/previous attempt for this activity first,
            // so re-running "Match & continue" after fixing a typo'd
            // filename doesn't leave the old file alongside the new one.
            $fs->delete_area_files(
                $systemcontext->id,
                'tool_bulkcoursestudio',
                'activityfiles',
                $pending->id
            );
            $fs->create_file_from_storedfile([
                'contextid' => $systemcontext->id,
                'component' => 'tool_bulkcoursestudio',
                'filearea' => 'activityfiles',
                'itemid' => $pending->id,
                'filepath' => '/',
                'filename' => $uploaded->get_filename(),
            ], $uploaded);
            $matchedcount++;
            break;
        }
    }

    redirect(
        $previewurl,
        $matchedcount > 0
            ? get_string('matchfiles:matchedsome', 'tool_bulkcoursestudio', $matchedcount)
            : get_string('matchfiles:matchednone', 'tool_bulkcoursestudio'),
        null,
        $matchedcount > 0 ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->header();
echo ui::begin('', (int) $buildid, get_string('step:matchfiles', 'tool_bulkcoursestudio'));
echo $OUTPUT->notification(get_string('matchfiles:info', 'tool_bulkcoursestudio'), 'info');

$table = new html_table();
$table->head = [
    get_string('matchfiles:col:section', 'tool_bulkcoursestudio'),
    get_string('matchfiles:col:activity', 'tool_bulkcoursestudio'),
    get_string('matchfiles:col:expectedfile', 'tool_bulkcoursestudio'),
    get_string('matchfiles:col:status', 'tool_bulkcoursestudio'),
];
foreach ($needing as $pending) {
    $status = $pending->hasfile
        ? $OUTPUT->pix_icon('i/valid', get_string('matchfiles:matched', 'tool_bulkcoursestudio'))
            . ' ' . get_string('matchfiles:matched', 'tool_bulkcoursestudio')
        : $OUTPUT->pix_icon('i/invalid', get_string('matchfiles:unmatched', 'tool_bulkcoursestudio'))
            . ' ' . get_string('matchfiles:unmatched', 'tool_bulkcoursestudio');
    $table->data[] = [
        format_string($pending->sectionname),
        format_string($pending->name),
        s($pending->localfilename),
        $status,
    ];
}
echo html_writer::table($table);

$form->display();

echo html_writer::link(
    $previewurl,
    get_string('matchfiles:skiptopreview', 'tool_bulkcoursestudio'),
    ['class' => 'btn btn-link']
);

echo ui::end();
echo $OUTPUT->footer();
