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
 * Entry point for editing a course (or just one of its sections/
 * activities) that was already built — and may already be published —
 * through Bulk Course Studio (spec §3.3 "edit or update already published
 * course or just a section or activity").
 *
 * Re-opening the wizard against an existing build's buildid is always
 * safe, published or not: build_manager::commit() only ever touches the
 * course's metadata/sections/activities via core's own update APIs, never
 * its visibility — so editing content here never silently unpublishes a
 * live course. Only an explicit "Publish now" on step_preview.php changes
 * visibility.
 *
 * The admin's own in-progress drafts already have a "Continue" link on
 * index.php; this screen is for finding *any* committed build — including
 * ones built by a different admin, or one of the admin's own long-finished
 * builds that has scrolled off their personal drafts view — by searching
 * the course's name.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\draft_repository;

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$search = optional_param('search', '', PARAM_TEXT);

$PAGE->set_url('/admin/tool/bulkcoursestudio/edit_course.php', $search !== '' ? ['search' => $search] : []);
$PAGE->set_title(get_string('editexisting', 'tool_bulkcoursestudio'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editexisting', 'tool_bulkcoursestudio'));
echo $OUTPUT->notification(get_string('editexisting:info', 'tool_bulkcoursestudio'), 'info');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'value' => s($search),
    'placeholder' => get_string('editexisting:searchplaceholder', 'tool_bulkcoursestudio'),
    'class' => 'form-control mr-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('search'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

$builds = draft_repository::search_committed_builds($search);

if (empty($builds)) {
    echo $OUTPUT->notification(
        $search !== ''
            ? get_string('editexisting:nomatches', 'tool_bulkcoursestudio', s($search))
            : get_string('editexisting:nocourses', 'tool_bulkcoursestudio'),
        'info'
    );
} else {
    $table = new html_table();
    $table->head = [
        get_string('fullnamecourse'),
        get_string('shortnamecourse'),
        get_string('status'),
        get_string('lastmodified'),
        '',
    ];

    foreach ($builds as $build) {
        $statuskey = 'status' . $build->status; // Statusdraft, statusscheduled, or statuspublished.
        $status = get_string_manager()->string_exists($statuskey, 'tool_bulkcoursestudio')
            ? ($build->status === 'scheduled'
                ? get_string('statusscheduled', 'tool_bulkcoursestudio', userdate($build->scheduledtime))
                : get_string($statuskey, 'tool_bulkcoursestudio'))
            : $build->status;

        $table->data[] = [
            format_string($build->coursefullname),
            format_string($build->courseshortname),
            $status,
            userdate($build->timemodified),
            html_writer::link(
                new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php', ['buildid' => $build->id]),
                get_string('editexisting:editbutton', 'tool_bulkcoursestudio'),
                ['class' => 'btn btn-sm btn-primary mr-1']
            ) . ' ' . html_writer::link(
                new moodle_url('/course/view.php', ['id' => $build->courseid]),
                get_string('view'),
                ['class' => 'btn btn-sm btn-secondary']
            ),
        ];
    }

    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/admin/tool/bulkcoursestudio/index.php'),
    get_string('back'),
    ['class' => 'btn btn-link']
);

echo $OUTPUT->footer();
