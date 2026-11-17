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
 * Activity page: a launch button that opens the video in a pop-up player,
 * the learner's progress, and Overview / Resources / Transcript tabs.
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

// Log the view and apply view-based completion. The "watched at least N%"
// rule is separate: it is marked complete from report_progress once the
// learner's progress reaches the percentage.
$event = \mod_bulkcoursestudio\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('bulkcoursestudio', $instance);
$event->trigger();

$completioninfo = new completion_info($course);
$completioninfo->set_module_viewed($cm);

// Work out what to play.
$fs = get_file_storage();
$source = $instance->mediasource ?? 'url';
$provider = $instance->provider;
$mediatype = 'iframe';
$sharingnotice = '';
if ($source === 'upload') {
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
    $provider = 'upload';
} else if ($source === 'direct') {
    $embedsrc = \mod_bulkcoursestudio\embed_resolver::is_direct_video_url($instance->embedurl) ? $instance->embedurl : '';
    $mediatype = 'video';
    $provider = 'direct';
} else {
    $embedsrc = \mod_bulkcoursestudio\embed_resolver::to_embed_src($instance->embedurl, $provider);
    $sharingnotice = \mod_bulkcoursestudio\embed_resolver::get_sharing_notice($provider);

    // Google Drive with an API key: play the file in the browser's own
    // player so watch percentage can be tracked. If Google refuses to stream
    // it (quota, sharing), the player falls back to the normal Drive preview.
    if ($provider === \mod_bulkcoursestudio\embed_resolver::PROVIDER_GDRIVE) {
        $streamsrc = \mod_bulkcoursestudio\embed_resolver::drive_stream_src($instance->embedurl);
        if ($streamsrc !== '') {
            $fallbacksrc = $embedsrc;
            $embedsrc = $streamsrc;
            $mediatype = 'video';
        }
    }
}
$fallbacksrc = $fallbacksrc ?? '';

// The learner's saved progress, for "resume where you left off".
$progress = $DB->get_record('bulkcoursestudio_progress', [
    'bulkcoursestudioid' => $instance->id,
    'userid' => $USER->id,
]);

// The next visible activity in the course, for auto-advance.
$nexturl = '';
$nextname = '';
if (!empty($instance->autonext)) {
    $modinfo = get_fast_modinfo($course);
    $ordered = [];
    foreach ($modinfo->sections as $sectioncmids) {
        $ordered = array_merge($ordered, $sectioncmids);
    }
    $position = array_search($cm->id, $ordered);
    if ($position !== false) {
        foreach (array_slice($ordered, $position + 1) as $nextcmid) {
            $nextcm = $modinfo->get_cm($nextcmid);
            if ($nextcm->uservisible && $nextcm->url) {
                $nexturl = $nextcm->url->out(false);
                $nextname = $nextcm->get_formatted_name();
                break;
            }
        }
    }
}

$PAGE->requires->js_call_amd('mod_bulkcoursestudio/launcher', 'init', [[
    'cmid' => (int) $cm->id,
    'src' => $embedsrc,
    'type' => $mediatype,
    'provider' => $provider,
    'timed' => (int) $instance->completiontimespent > 0,
    'tracked' => $mediatype === 'video' || in_array($provider, ['youtube', 'vimeo'], true),
    'fallbacksrc' => $fallbacksrc,
    'resume' => $progress ? (int) $progress->currenttime : 0,
    'autonext' => !empty($instance->autonext),
    'nexturl' => $nexturl,
    'nextname' => $nextname,
    'strings' => [
        'next' => get_string('comingupnext', 'mod_bulkcoursestudio'),
        'cancel' => get_string('cancel'),
        'unresolvable' => get_string('embedunresolvable', 'mod_bulkcoursestudio'),
    ],
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if ($embedsrc === '') {
    // The stored link no longer resolves, or the file is missing: say so
    // plainly rather than showing a dead button.
    echo $OUTPUT->notification(get_string('embedunresolvable', 'mod_bulkcoursestudio'), 'error');
} else {
    if ($sharingnotice !== '' && has_capability('mod/bulkcoursestudio:addinstance', $context)) {
        echo $OUTPUT->notification($sharingnotice, 'info');
    }

    echo html_writer::start_div('mb-3');
    echo html_writer::tag(
        'button',
        get_string($progress && $progress->currenttime > 3 ? 'resumevideo' : 'openembed', 'mod_bulkcoursestudio'),
        [
            'class' => 'btn btn-primary btn-lg',
            'id' => 'bulkcoursestudio-launch-' . $cm->id,
            'data-bulkcoursestudio-launch' => $cm->id,
        ]
    );
    if ($progress && $progress->watchpercent > 0) {
        echo html_writer::tag(
            'span',
            get_string('watchedpercent', 'mod_bulkcoursestudio', (int) $progress->watchpercent),
            ['class' => 'ms-3 ml-3 text-muted']
        );
    }
    echo html_writer::end_div();

    if ($completioninfo->is_enabled($cm)) {
        $requirements = [];
        if ((int) $instance->completionpercent > 0) {
            $requirements[] = get_string('completiondetail:percent', 'mod_bulkcoursestudio', (int) $instance->completionpercent);
        }
        if ((int) $instance->completiontimespent > 0) {
            $requirements[] = get_string('completiondetail:timespent', 'mod_bulkcoursestudio', (int) $instance->completiontimespent);
        }
        if ($requirements) {
            echo html_writer::div(implode(' · ', $requirements), 'text-muted small mb-3');
        }
    }
}

// Tabs: Overview, Resources, Transcript.
$resources = $fs->get_area_files($context->id, 'mod_bulkcoursestudio', 'resources', 0, 'filename', false);
$transcriptfiles = $fs->get_area_files($context->id, 'mod_bulkcoursestudio', 'transcript', 0, 'filename', false);
$transcriptfile = reset($transcriptfiles);

$tabs = [];
$tabs['overview'] = [
    get_string('overview', 'mod_bulkcoursestudio'),
    $instance->intro
        ? format_module_intro('bulkcoursestudio', $instance, $cm->id)
        : html_writer::div(get_string('nooverview', 'mod_bulkcoursestudio'), 'text-muted'),
];
if ($resources) {
    $items = '';
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
        $items .= html_writer::tag(
            'li',
            html_writer::link($url, s($resource->get_filename()))
            . html_writer::span(' (' . display_size($resource->get_filesize()) . ')', 'text-muted small'),
            ['class' => 'mb-2']
        );
    }
    $tabs['resources'] = [get_string('resourcesview', 'mod_bulkcoursestudio'), html_writer::tag('ul', $items, ['class' => 'list-unstyled'])];
}
if ($transcriptfile) {
    $tabs['transcript'] = [
        get_string('transcriptview', 'mod_bulkcoursestudio'),
        html_writer::tag('pre', s($transcriptfile->get_content()), ['class' => 'bcs-transcript p-3 bg-light border rounded']),
    ];
}

$navitems = '';
$panes = '';
$first = true;
foreach ($tabs as $key => [$label, $html]) {
    $navitems .= html_writer::tag('li', html_writer::tag('a', s($label), [
        'class' => 'nav-link' . ($first ? ' active' : ''),
        'href' => '#bcs-tab-' . $key,
        'data-toggle' => 'tab',
        'data-bs-toggle' => 'tab',
        'role' => 'tab',
    ]), ['class' => 'nav-item', 'role' => 'presentation']);
    $panes .= html_writer::div($html, 'tab-pane fade p-3' . ($first ? ' show active' : ''), [
        'id' => 'bcs-tab-' . $key,
        'role' => 'tabpanel',
    ]);
    $first = false;
}
echo html_writer::tag('ul', $navitems, ['class' => 'nav nav-tabs mt-2', 'role' => 'tablist']);
echo html_writer::div($panes, 'tab-content border border-top-0 rounded-bottom');

echo $OUTPUT->footer();
