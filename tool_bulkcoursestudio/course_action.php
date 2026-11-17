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
 * Hide, show or delete the real course behind a build, from the dashboard.
 *
 * Uses Moodle's own course functions (core_course_category::hide_course(),
 * show_course() and delete_course()), so everything core does for those
 * actions (events, recycle bin, caches) still happens.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');

use tool_bulkcoursestudio\draft_repository;
use tool_bulkcoursestudio\output\ui;

$buildid = required_param('buildid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$build = draft_repository::get_build($buildid);
$indexurl = new moodle_url('/admin/tool/bulkcoursestudio/index.php');
$PAGE->set_url('/admin/tool/bulkcoursestudio/course_action.php', ['buildid' => $buildid, 'action' => $action]);
$PAGE->set_title(get_string('pluginname', 'tool_bulkcoursestudio'));

$course = !empty($build->courseid) ? $DB->get_record('course', ['id' => $build->courseid]) : false;
if (!$course) {
    redirect($indexurl, get_string('nocoursefound', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_WARNING);
}
$coursecontext = context_course::instance($course->id);

if ($action === 'hide' || $action === 'show') {
    require_sesskey();
    require_capability('moodle/course:visibility', $coursecontext);
    $category = core_course_category::get($course->category, MUST_EXIST, true);
    if ($action === 'hide') {
        $category->hide_course($course);
    } else {
        $category->show_course($course);
    }
    redirect(
        $indexurl,
        get_string($action === 'hide' ? 'coursehidden' : 'courseshown', 'tool_bulkcoursestudio'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'delete') {
    require_capability('tool/bulkcoursestudio:deletewithdata', context_system::instance());
    require_capability('moodle/course:delete', $coursecontext);

    if ($confirm) {
        require_sesskey();
        delete_course($course, false);
        draft_repository::delete_build($buildid);
        redirect($indexurl, get_string('coursedeleted', 'tool_bulkcoursestudio'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo ui::begin('', 0, get_string('deletecourse', 'tool_bulkcoursestudio'), '', $indexurl);
    echo $OUTPUT->notification(
        get_string('deletecourse:confirm', 'tool_bulkcoursestudio', format_string($course->fullname)),
        'warning'
    );
    echo html_writer::start_div('d-flex', ['style' => 'gap:.5rem;']);
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]),
        get_string('deletecourse', 'tool_bulkcoursestudio'),
        'post',
        ['type' => single_button::BUTTON_DANGER]
    );
    echo html_writer::link($indexurl, get_string('cancel'), ['class' => 'btn btn-secondary align-self-center']);
    echo html_writer::end_div();
    echo ui::end();
    echo $OUTPUT->footer();
    exit;
}

throw new moodle_exception('invalidaction', 'error');
