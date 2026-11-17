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
 * Wizard step 3: sections. Lists sections already added to this build and
 * lets the admin add/edit one via section_form; "Next" moves on to
 * activities once at least one section exists.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\output\ui;
use tool_bulkcoursestudio\build_manager;
use tool_bulkcoursestudio\form\section_form;

$buildid = required_param('buildid', PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:sections', 'tool_bulkcoursestudio'));

if ($action === 'next') {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]));
}

$formurl = new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid, 'sectionid' => $sectionid]);
$form = new section_form($formurl);

if ($sectionid) {
    global $DB;
    $existing = $DB->get_record('tool_bulkcoursestudio_section', ['id' => $sectionid, 'buildid' => $buildid], '*', MUST_EXIST);
    $form->set_data((object) [
        'buildid' => $buildid,
        'sectionid' => $sectionid,
        'name' => $existing->name,
        'summary_editor' => ['text' => $existing->summary, 'format' => FORMAT_HTML],
    ]);
} else {
    $form->set_data((object) ['buildid' => $buildid, 'sectionid' => 0]);
}

if ($data = $form->get_data()) {
    draft_repository::add_or_update_section(
        $buildid,
        $data->sectionid ?: null,
        $data->name,
        $data->summary_editor['text'] ?? ''
    );
    // Real sections, in real numbering, need to exist before
    // step_activity_settings.php can embed a native mod_form against them.
    build_manager::sync($buildid);
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid]));
}

$thisurl = new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid]);

// Add N sections at once, named "Section 1", "Section 2"...
$addcount = optional_param('addcount', 0, PARAM_INT);
if ($addcount > 0 && confirm_sesskey()) {
    $existingcount = count(draft_repository::get_sections($buildid));
    $addcount = min($addcount, 52 - $existingcount);
    for ($i = 1; $i <= $addcount; $i++) {
        draft_repository::add_or_update_section(
            $buildid,
            null,
            get_string('defaultsectionname', 'tool_bulkcoursestudio', $existingcount + $i),
            ''
        );
    }
    if ($addcount > 0) {
        build_manager::sync($buildid);
    }
    redirect(
        $thisurl,
        get_string('sectionsadded', 'tool_bulkcoursestudio', max(0, $addcount)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Rename one section.
$renameid = optional_param('rename', 0, PARAM_INT);
$newname = optional_param('newname', null, PARAM_TEXT);
if ($renameid) {
    $DB->get_record('tool_bulkcoursestudio_section', ['id' => $renameid, 'buildid' => $buildid], 'id', MUST_EXIST);
    if ($newname !== null && confirm_sesskey()) {
        $newname = trim($newname);
        if ($newname !== '') {
            draft_repository::rename_section($renameid, $newname);
            build_manager::sync($buildid);
        }
        redirect($thisurl, get_string('renamed', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$sections = draft_repository::get_sections($buildid);

echo $OUTPUT->header();
echo ui::begin('sections', (int) $buildid, get_string('step:sections', 'tool_bulkcoursestudio'),
    get_string('stepsub:sections', 'tool_bulkcoursestudio'), new moodle_url('/admin/tool/bulkcoursestudio/step_format.php', ['buildid' => $buildid]));

// How many sections? One box, one button.
echo html_writer::start_div('bcs-section');
echo html_writer::div(html_writer::tag('h3', get_string('howmanysections', 'tool_bulkcoursestudio'), ['class' => 'bcs-section-title']), 'bcs-section-head');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $thisurl->out(false), 'class' => 'bcs-activity']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::span(get_string('howmanysections:help', 'tool_bulkcoursestudio'), 'bcs-activity-name');
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'addcount', 'min' => 1, 'max' => 52,
    'value' => empty($sections) ? 4 : 1, 'class' => 'form-control', 'style' => 'width:90px;',
    'aria-label' => get_string('howmanysections', 'tool_bulkcoursestudio'),
]);
echo html_writer::tag('button', get_string('addsectionsbutton', 'tool_bulkcoursestudio'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::start_div('bcs-section');
echo html_writer::div(html_writer::tag('h3', get_string('sectionslist', 'tool_bulkcoursestudio'), ['class' => 'bcs-section-title']), 'bcs-section-head');
foreach ($sections as $section) {
    $editurl = new moodle_url(
        '/admin/tool/bulkcoursestudio/step_sections.php',
        ['buildid' => $buildid, 'sectionid' => $section->id]
    );
    if ($renameid == $section->id) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $thisurl->out(false), 'class' => 'bcs-activity']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rename', 'value' => $section->id]);
        echo html_writer::empty_tag('input', [
            'type' => 'text', 'name' => 'newname', 'value' => $section->name, 'class' => 'form-control bcs-activity-name',
            'required' => 'required', 'autofocus' => 'autofocus',
        ]);
        echo html_writer::tag('button', get_string('save'), ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']);
        echo html_writer::link($thisurl, get_string('cancel'), ['class' => 'btn btn-secondary btn-sm']);
        echo html_writer::end_tag('form');
        continue;
    }
    echo html_writer::div(
        html_writer::span(format_string($section->name), 'bcs-activity-name')
        . html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary btn-sm'])
        . html_writer::link(
            new moodle_url($thisurl, ['rename' => $section->id]),
            get_string('rename', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-secondary btn-sm']
        ),
        'bcs-activity'
    );
}
if (empty($sections)) {
    echo html_writer::div(get_string('nosectionsyet', 'tool_bulkcoursestudio'), 'bcs-empty');
}
echo html_writer::end_div();

$form->display();

if (!empty($sections)) {
    $nexturl = new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid, 'action' => 'next']);
    echo html_writer::div(
        html_writer::link($nexturl, get_string('step:activities', 'tool_bulkcoursestudio') . ' →', ['class' => 'btn btn-primary']),
        'bcs-footer justify-content-end'
    );
} else {
    echo $OUTPUT->notification(get_string('preview:warning:nosections', 'tool_bulkcoursestudio'), 'info');
}

echo ui::end();
echo $OUTPUT->footer();
