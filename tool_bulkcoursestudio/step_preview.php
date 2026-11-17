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
 * Wizard step 5: preview (spec §3.1 step 6). Read-only — calls
 * build_manager::preview(), never commit(). Both Flow A (this page) and
 * Flow B (the Excel upload screen) render through the same preview tree
 * structure, so what the admin sees here is literally what Flow B's
 * preview looks like too.
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

$buildid = required_param('buildid', PARAM_INT);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

draft_repository::get_build($buildid);
$PAGE->set_url('/admin/tool/bulkcoursestudio/step_preview.php', ['buildid' => $buildid]);
$PAGE->set_title(get_string('step:preview', 'tool_bulkcoursestudio'));

$preview = build_manager::preview($buildid);

echo $OUTPUT->header();
echo ui::begin('preview', (int) $buildid, get_string('step:preview', 'tool_bulkcoursestudio'),
    get_string('stepsub:preview', 'tool_bulkcoursestudio'));

foreach ($preview->errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}
foreach ($preview->warnings as $warning) {
    echo $OUTPUT->notification($warning, 'warning');
}

echo $OUTPUT->heading(format_string($preview->tree->course['fullname'] ?? ''), 3);

foreach ($preview->tree->sections as $section) {
    echo $OUTPUT->heading(format_string($section->name), 4);
    echo html_writer::start_tag('ul');
    foreach ($section->activities as $activity) {
        echo html_writer::tag('li', format_string($activity->name) . ' (' . $activity->modname . ')');
    }
    echo html_writer::end_tag('ul');
}

if (empty($preview->errors)) {
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/bulkcoursestudio/step_commit.php', ['buildid' => $buildid, 'mode' => 'draft']),
        get_string('savedraft', 'tool_bulkcoursestudio'),
        'post'
    );
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/bulkcoursestudio/step_commit.php', ['buildid' => $buildid, 'mode' => 'publish']),
        get_string('publishnow', 'tool_bulkcoursestudio'),
        'post'
    );
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/bulkcoursestudio/step_commit.php', ['buildid' => $buildid, 'mode' => 'schedule']),
        get_string('schedulelater', 'tool_bulkcoursestudio'),
        'get' // GET here only to collect the schedule date next; the actual commit on that follow-up page is POST.
    );
} else {
    echo $OUTPUT->notification(get_string('fixerrorsfirst', 'tool_bulkcoursestudio'), 'error');
}

echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/step_activities.php', ['buildid' => $buildid]),
    get_string('back'),
    ['class' => 'btn btn-link']
);

echo ui::end();
echo $OUTPUT->footer();
