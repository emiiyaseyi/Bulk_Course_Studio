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
 * Activity view page for mod_bulkcoursestudio.
 *
 * Deliberately renders as a normal activity page with a launcher (card/
 * button + thumbnail where available) — the embed itself opens in a pop-up
 * modal via amd/src/launcher.js, never inline on this page. See spec §4 for
 * why: Page/URL have no modal-launch mode, so a dedicated view page was
 * needed to control the whole render path.
 *
 * @package    mod_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('bulkcoursestudio', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record('bulkcoursestudio', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/bulkcoursestudio:view', $context);

$PAGE->set_url('/mod/bulkcoursestudio/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// View-based completion: fires immediately on load. The separate
// "minimum seconds watched" rule (if the teacher enabled it) is only
// marked complete from report_progress::execute() once the threshold is
// actually reached — that one needs the modal to have been opened, so it
// can't be satisfied just by loading this page.
$completioninfo = new completion_info($course);
$completioninfo->set_module_viewed($cm);

$fs = get_file_storage();
$mediatype = 'iframe';
if (($instance->mediasource ?? 'url') === 'upload') {
    // Uploaded video: play it straight from this activity's own file area.
    $videofiles = $fs->get_area_files($context->id, 'mod_bulkcoursestudio', 'mediafile', 0, 'sortorder, id', false);
    $videofile = reset($videofiles);
    $embedsrc = $videofile ? moodle_url::make_pluginfile_url(
        $context->id,
        'mod_bulkcoursestudio',
        'mediafile',
        0,
        $videofile->get_filepath(),
        $videofile->get_filename()
    )->out(false) : '';
    $mediatype = 'video';
    $sharingnotice = '';
} else {
    $embedsrc = \mod_bulkcoursestudio\embed_resolver::to_embed_src($instance->embedurl, $instance->provider);
    $sharingnotice = \mod_bulkcoursestudio\embed_resolver::get_sharing_notice($instance->provider);
}

$PAGE->requires->js_call_amd('mod_bulkcoursestudio/launcher', 'init', [
    (int) $cm->id,
    $embedsrc,
    $mediatype,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if ($instance->intro) {
    echo $OUTPUT->box(format_module_intro('bulkcoursestudio', $instance, $cm->id), 'generalbox', 'intro');
}

if ($embedsrc === '') {
    // The stored URL no longer resolves (e.g. a provider changed its link
    // format since this was saved) — tell a teacher plainly rather than
    // silently showing a dead button to students.
    echo $OUTPUT->notification(get_string('embedunresolvable', 'mod_bulkcoursestudio'), 'error');
} else {
    if ($sharingnotice !== '' && has_capability('mod/bulkcoursestudio:addinstance', $context)) {
        echo $OUTPUT->notification($sharingnotice, 'info');
    }

    // TODO MDL-0: provider thumbnail where available (YouTube/Vimeo both
    // expose one via a predictable thumbnail URL; Drive/OneDrive do not).
    echo html_writer::tag('button', get_string('openembed', 'mod_bulkcoursestudio'), [
        'class' => 'btn btn-primary',
        'id' => 'bulkcoursestudio-launch-' . $cm->id,
        'data-bulkcoursestudio-launch' => $cm->id,
    ]);
}

// Supporting files attached to the activity.
$resources = $fs->get_area_files($context->id, 'mod_bulkcoursestudio', 'resources', 0, 'filename', false);
if ($resources) {
    echo $OUTPUT->heading(get_string('resourcesview', 'mod_bulkcoursestudio'), 4, 'mt-4');
    echo html_writer::start_tag('ul', ['class' => 'list-unstyled']);
    foreach ($resources as $resource) {
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_bulkcoursestudio',
            'resources',
            0,
            $resource->get_filepath(),
            $resource->get_filename(),
            true
        );
        echo html_writer::tag('li', html_writer::link($url, s($resource->get_filename())), ['class' => 'mb-1']);
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
