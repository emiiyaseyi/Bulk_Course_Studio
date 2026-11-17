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
 * Wizard step 4: activities.
 *
 * Shows every section as a card with its activities. "Add activity" opens
 * a tile chooser of every activity type installed on the site; picking a
 * tile goes straight to step_activity_settings.php, which shows that
 * activity's own real settings form (URL, file upload, embed code, grading,
 * restrict access, completion, tags, competencies...).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\native_form_registry;
use tool_bulkcoursestudio\output\ui;

$buildid = required_param('buildid', PARAM_INT);
$choosesection = optional_param('choose', 0, PARAM_INT);
$removeid = optional_param('remove', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:activities', 'tool_bulkcoursestudio'));

$thisurl = new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]);
$sections = draft_repository::get_sections($buildid);
$sectionids = array_keys($sections);

// Remove an activity (with confirmation).
if ($removeid) {
    $activity = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $removeid], '*', MUST_EXIST);
    if (!in_array((int) $activity->sectionid, array_map('intval', $sectionids), true)) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    // Deleting something already created in the course also removes learner
    // data, so it needs the same permission core-style deletes use here.
    $candelete = empty($activity->committedcmid)
        || has_capability('tool/bulkcoursestudio:deletewithdata', context_system::instance());

    $choice = optional_param('choice', '', PARAM_ALPHA);
    if ($choice === 'hide') {
        require_sesskey();
        draft_repository::set_activity_visibility($removeid, false);
        redirect($thisurl, get_string('activityhidden', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($choice === 'delete' && $candelete) {
        require_sesskey();
        draft_repository::delete_activity($removeid);
        redirect($thisurl, get_string('activityremoved', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Hide / delete / cancel, as the spec asks.
    echo $OUTPUT->header();
    echo ui::begin(
        'activities',
        $buildid,
        get_string('removeactivity', 'tool_bulkcoursestudio'),
        get_string('removeactivity:choose', 'tool_bulkcoursestudio', format_string($activity->name))
    );
    echo html_writer::div(
        html_writer::tag('strong', get_string('removeactivity:hide', 'tool_bulkcoursestudio'))
        . html_writer::div(get_string('removeactivity:hidehelp', 'tool_bulkcoursestudio'), 'text-muted small mb-2')
        . $OUTPUT->single_button(
            new moodle_url($thisurl, ['remove' => $removeid, 'choice' => 'hide', 'sesskey' => sesskey()]),
            get_string('hideactivity', 'tool_bulkcoursestudio'),
            'post',
            single_button::BUTTON_PRIMARY
        ),
        'mb-4'
    );
    if ($candelete) {
        echo html_writer::div(
            html_writer::tag('strong', get_string('removeactivity:delete', 'tool_bulkcoursestudio'))
            . html_writer::div(get_string('removeactivity:deletehelp', 'tool_bulkcoursestudio'), 'text-muted small mb-2')
            . $OUTPUT->single_button(
                new moodle_url($thisurl, ['remove' => $removeid, 'choice' => 'delete', 'sesskey' => sesskey()]),
                get_string('removeactivity', 'tool_bulkcoursestudio'),
                'post',
                single_button::BUTTON_DANGER
            ),
            'mb-4'
        );
    }
    echo html_writer::div(
        html_writer::link($thisurl, get_string('cancel'), ['class' => 'btn btn-secondary']),
        'bcs-footer'
    );
    echo ui::end();
    echo $OUTPUT->footer();
    exit;
}

// Quick show/hide toggle from the activity list.
$toggleid = optional_param('toggle', 0, PARAM_INT);
if ($toggleid) {
    require_sesskey();
    $toggled = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $toggleid], '*', MUST_EXIST);
    if (!in_array((int) $toggled->sectionid, array_map('intval', $sectionids), true)) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    $nowhidden = draft_repository::is_activity_hidden($toggled);
    draft_repository::set_activity_visibility($toggleid, $nowhidden);
    redirect(
        $thisurl,
        get_string($nowhidden ? 'activityshown' : 'activityhidden', 'tool_bulkcoursestudio'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Rename an activity.
$renameid = optional_param('rename', 0, PARAM_INT);
$newname = optional_param('newname', null, PARAM_TEXT);
if ($renameid) {
    $renaming = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $renameid], '*', MUST_EXIST);
    if (!in_array((int) $renaming->sectionid, array_map('intval', $sectionids), true)) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    if ($newname !== null && confirm_sesskey()) {
        $newname = trim($newname);
        if ($newname !== '') {
            draft_repository::rename_activity($renameid, $newname);
        }
        redirect($thisurl, get_string('renamed', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Activity type chooser for one section.
if ($choosesection) {
    if (!isset($sections[$choosesection])) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    if (empty($build->courseid)) {
        redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $buildid]));
    }
    $section = $sections[$choosesection];
    echo ui::begin(
        'activities',
        $buildid,
        get_string('chooseactivitytype', 'tool_bulkcoursestudio'),
        get_string('chooseactivitytype:sub', 'tool_bulkcoursestudio', format_string($section->name))
    );

    $types = native_form_registry::get_addable_types((int) $build->courseid);
    $featured = array_intersect_key($types, array_flip(native_form_registry::FEATURED));
    $others = array_diff_key($types, $featured);

    $rendertiles = function (array $list) use ($OUTPUT, $buildid, $choosesection) {
        $html = html_writer::start_div('bcs-tilegrid');
        foreach ($list as $modname => $displayname) {
            $url = new moodle_url('/admin/tool/bulkcoursestudio/step_activity_settings.php', [
                'buildid' => $buildid, 'sectionid' => $choosesection, 'modname' => $modname,
            ]);
            $icon = html_writer::empty_tag('img', [
                'src' => $OUTPUT->image_url('monologo', 'mod_' . $modname)->out(false),
                'alt' => '',
                'class' => 'activityicon',
            ]);
            $html .= html_writer::link($url, $icon . html_writer::span(s($displayname)), ['class' => 'bcs-tile']);
        }
        return $html . html_writer::end_div();
    };

    if ($featured) {
        echo html_writer::tag('div', get_string('chooser:popular', 'tool_bulkcoursestudio'), ['class' => 'bcs-subhead']);
        echo $rendertiles($featured);
    }
    if ($others) {
        echo html_writer::tag('div', get_string('chooser:more', 'tool_bulkcoursestudio'), ['class' => 'bcs-subhead']);
        echo $rendertiles($others);
    }
    echo html_writer::div(
        html_writer::link($thisurl, get_string('cancel'), ['class' => 'btn btn-secondary']),
        'bcs-footer'
    );
    echo ui::end();
    echo $OUTPUT->footer();
    exit;
}

// Main view: sections with their activities.
echo ui::begin(
    'activities',
    $buildid,
    get_string('step:activities', 'tool_bulkcoursestudio'),
    get_string('stepsub:activities', 'tool_bulkcoursestudio')
);

if (empty($sections)) {
    echo html_writer::div(get_string('noactivitysections', 'tool_bulkcoursestudio'), 'bcs-note');
}

foreach ($sections as $section) {
    $activities = draft_repository::get_activities($section->id);

    echo html_writer::start_div('bcs-section');
    echo html_writer::start_div('bcs-section-head');
    echo html_writer::tag(
        'h3',
        format_string($section->name) . html_writer::span(
            get_string('activitycount', 'tool_bulkcoursestudio', count($activities)),
            'bcs-section-count'
        ),
        ['class' => 'bcs-section-title']
    );
    echo html_writer::link(
        new moodle_url($thisurl, ['choose' => $section->id]),
        '+ ' . get_string('addactivity', 'tool_bulkcoursestudio'),
        ['class' => 'btn btn-primary btn-sm']
    );
    echo html_writer::end_div();

    if (!$activities) {
        echo html_writer::div(get_string('noactivities', 'tool_bulkcoursestudio'), 'bcs-empty');
    }
    foreach ($activities as $activity) {
        $editurl = new moodle_url('/admin/tool/bulkcoursestudio/step_activity_settings.php', [
            'buildid' => $buildid, 'sectionid' => $section->id, 'modname' => $activity->modname, 'activityid' => $activity->id,
        ]);
        $typename = get_string_manager()->string_exists('pluginname', 'mod_' . $activity->modname)
            ? get_string('pluginname', 'mod_' . $activity->modname) : $activity->modname;
        $icon = html_writer::empty_tag('img', [
            'src' => $OUTPUT->image_url('monologo', 'mod_' . $activity->modname)->out(false),
            'alt' => '',
        ]);
        echo html_writer::start_div('bcs-activity');
        echo html_writer::span($icon, 'bcs-activity-icon');
        $hidden = draft_repository::is_activity_hidden($activity);
        if ($renameid == $activity->id) {
            // The rename form sits inside the row opened above, beside the icon.
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $thisurl->out(false), 'class' => 'd-flex flex-grow-1', 'style' => 'gap:.5rem;']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rename', 'value' => $activity->id]);
            echo html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'newname', 'value' => $activity->name, 'class' => 'form-control bcs-activity-name',
                'required' => 'required', 'autofocus' => 'autofocus',
            ]);
            echo html_writer::tag('button', get_string('save'), ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']);
            echo html_writer::link($thisurl, get_string('cancel'), ['class' => 'btn btn-secondary btn-sm']);
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
            continue;
        }
        echo html_writer::span(
            format_string($activity->name) . html_writer::span(s($typename), 'bcs-badge')
            . ($hidden ? html_writer::span(get_string('hiddenbadge', 'tool_bulkcoursestudio'), 'bcs-badge') : ''),
            'bcs-activity-name' . ($hidden ? ' text-muted' : '')
        );
        echo html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary btn-sm']);
        echo html_writer::link(
            new moodle_url($thisurl, ['rename' => $activity->id]),
            get_string('rename', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-secondary btn-sm']
        );
        echo html_writer::link(
            new moodle_url($thisurl, ['toggle' => $activity->id, 'sesskey' => sesskey()]),
            get_string($hidden ? 'showactivity' : 'hideactivity', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-secondary btn-sm']
        );
        echo html_writer::link(
            new moodle_url($thisurl, ['remove' => $activity->id]),
            get_string('removeactivity', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-outline-danger btn-sm']
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo html_writer::start_div('bcs-footer');
echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid]),
    get_string('back', 'tool_bulkcoursestudio'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/step_preview.php', ['buildid' => $buildid]),
    get_string('step:preview', 'tool_bulkcoursestudio') . ' →',
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();
echo ui::end();

echo $OUTPUT->footer();
