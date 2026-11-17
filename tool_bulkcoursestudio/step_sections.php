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

$sections = draft_repository::get_sections($buildid);

echo $OUTPUT->header();
echo ui::begin('sections', (int) $buildid, get_string('step:sections', 'tool_bulkcoursestudio'),
    get_string('stepsub:sections', 'tool_bulkcoursestudio'));

echo html_writer::start_div('bcs-section');
echo html_writer::div(html_writer::tag('h3', get_string('sectionslist', 'tool_bulkcoursestudio'), ['class' => 'bcs-section-title']), 'bcs-section-head');
foreach ($sections as $section) {
    $editurl = new moodle_url(
        '/admin/tool/bulkcoursestudio/step_sections.php',
        ['buildid' => $buildid, 'sectionid' => $section->id]
    );
    echo html_writer::div(
        html_writer::span(format_string($section->name), 'bcs-activity-name')
        . html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary btn-sm']),
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
