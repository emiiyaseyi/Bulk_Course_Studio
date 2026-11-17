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
 * Wizard step 2: course format.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\build_manager;
use tool_bulkcoursestudio\output\ui;
use tool_bulkcoursestudio\form\format_form;

$buildid = required_param('buildid', PARAM_INT);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_format.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:format', 'tool_bulkcoursestudio'));

$coursedata = json_decode($build->coursedata, true) ?? [];

$form = new format_form($PAGE->url);
$form->set_data((object) ['format' => $coursedata['format'] ?? 'topics']);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $buildid]));
} else if ($data = $form->get_data()) {
    draft_repository::save_format($buildid, $data->format);
    build_manager::sync($buildid); // Apply the chosen format to the real course immediately.
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_sections.php', ['buildid' => $buildid]));
}

echo $OUTPUT->header();
echo ui::begin('format', (int) $buildid, get_string('step:format', 'tool_bulkcoursestudio'),
    get_string('stepsub:format', 'tool_bulkcoursestudio'), new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $buildid]));
$form->display();
echo ui::end();
echo $OUTPUT->footer();
