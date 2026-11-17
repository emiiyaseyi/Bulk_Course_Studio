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
 * Wizard step 1: course details. Entry point for starting a new build
 * (no buildid) or editing an existing draft's details (?buildid=N).
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\build_manager;
use tool_bulkcoursestudio\form\course_details_form;
use tool_bulkcoursestudio\output\ui;

$buildid = optional_param('buildid', 0, PARAM_INT);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$PAGE->set_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:coursedetails', 'tool_bulkcoursestudio'));

$formatoptions = ['maxfiles' => 0, 'noclean' => true, 'context' => context_system::instance()];
$customdata = [];
$draftitemid = file_get_submitted_draft_itemid('courseimage');

if ($buildid) {
    $build = draft_repository::get_build($buildid);
    require_capability('tool/bulkcoursestudio:manage', context_system::instance());
    if ($build->usermodified != $USER->id) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'tool_bulkcoursestudio'));
    }
    $coursedata = json_decode($build->coursedata, true) ?? [];
    $coursedata['summary_editor'] = ['text' => $coursedata['summary'] ?? '', 'format' => FORMAT_HTML];
    file_prepare_draft_area($draftitemid, context_system::instance()->id, 'tool_bulkcoursestudio', 'courseimage', $buildid);
    $coursedata['courseimage'] = $draftitemid;
} else {
    $coursedata = ['courseimage' => $draftitemid];
}

$form = new course_details_form($PAGE->url, $customdata);
$form->set_data((object) $coursedata);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/index.php'));
} else if ($data = $form->get_data()) {
    $tosave = (array) $data;
    $tosave['summary'] = $data->summary_editor['text'] ?? '';
    unset($tosave['summary_editor'], $tosave['submitbutton'], $tosave['courseimage']);

    $buildid = draft_repository::save_course_details($buildid ?: null, $tosave);

    // Files move into the plugin's own draft file area for now
    // (component tool_bulkcoursestudio, filearea courseimage, itemid =
    // buildid) and are only copied into the real course via
    // file_postupdate_standard_filemanager() at commit time inside
    // build_manager — never written straight into a course that doesn't
    // exist yet.
    file_save_draft_area_files(
        $data->courseimage,
        context_system::instance()->id,
        'tool_bulkcoursestudio',
        'courseimage',
        $buildid
    );

    // Materialize a real (hidden) course immediately rather than waiting
    // for the final step_commit.php — step_activity_settings.php needs a
    // real course id to embed each module's own native mod_form against,
    // several steps from now. sync() is the progressive, unvalidated
    // counterpart to commit() — see its docblock.
    build_manager::sync($buildid);

    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_format.php', ['buildid' => $buildid]));
}

echo $OUTPUT->header();
echo ui::begin('coursedetails', (int) $buildid, get_string('step:coursedetails', 'tool_bulkcoursestudio'),
    get_string('stepsub:coursedetails', 'tool_bulkcoursestudio'), new moodle_url('/admin/tool/bulkcoursestudio/index.php'));
$form->display();
echo ui::end();
echo $OUTPUT->footer();
