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
 * Feature overview for Bulk Course Studio — a single page listing every
 * screen the admin tool and its companion activity module provide, each
 * with a direct link to open it, plus who built the plugin.
 *
 * Deliberately just a navigation aid, not a tour or wizard of its own: it
 * reads nothing from the database and writes nothing, so it's safe to
 * leave linked from the admin tree permanently rather than only during
 * onboarding.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_bulkcoursestudio_overview', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:manage', context_system::instance());

$PAGE->set_url('/admin/tool/bulkcoursestudio/overview.php');
$PAGE->set_title(get_string('overview:title', 'tool_bulkcoursestudio'));

/**
 * One feature-overview row: a name, a one-line description, and the URL
 * that opens it.
 *
 * @param string $name
 * @param string $description
 * @param moodle_url $url
 * @return string
 */
function tool_bulkcoursestudio_overview_row(string $name, string $description, moodle_url $url): string {
    global $OUTPUT;

    return html_writer::start_div('d-flex justify-content-between align-items-start border-bottom py-2') .
        html_writer::start_div() .
        html_writer::tag('strong', $name) .
        html_writer::tag('div', $description, ['class' => 'text-muted small']) .
        html_writer::end_div() .
        html_writer::link(
            $url,
            get_string('overview:open', 'tool_bulkcoursestudio'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        ) .
        html_writer::end_div();
}

$cansee = context_system::instance();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overview:title', 'tool_bulkcoursestudio'));
echo html_writer::tag('p', get_string('overview:intro', 'tool_bulkcoursestudio'), ['class' => 'text-muted']);

echo $OUTPUT->heading(get_string('overview:section:admintool', 'tool_bulkcoursestudio'), 3);

echo tool_bulkcoursestudio_overview_row(
    get_string('overview:feature:dashboard', 'tool_bulkcoursestudio'),
    get_string('overview:feature:dashboard_desc', 'tool_bulkcoursestudio'),
    new moodle_url('/admin/tool/bulkcoursestudio/index.php')
);

echo tool_bulkcoursestudio_overview_row(
    get_string('overview:feature:wizard', 'tool_bulkcoursestudio'),
    get_string('overview:feature:wizard_desc', 'tool_bulkcoursestudio'),
    new moodle_url('/admin/tool/bulkcoursestudio/step_coursedetails.php')
);

if (has_capability('tool/bulkcoursestudio:bulkupload', $cansee)) {
    echo tool_bulkcoursestudio_overview_row(
        get_string('overview:feature:exceltemplate', 'tool_bulkcoursestudio'),
        get_string('overview:feature:exceltemplate_desc', 'tool_bulkcoursestudio'),
        new moodle_url('/admin/tool/bulkcoursestudio/excel_download.php')
    );

    echo tool_bulkcoursestudio_overview_row(
        get_string('overview:feature:excelupload', 'tool_bulkcoursestudio'),
        get_string('overview:feature:excelupload_desc', 'tool_bulkcoursestudio'),
        new moodle_url('/admin/tool/bulkcoursestudio/excel_upload.php')
    );
}

echo tool_bulkcoursestudio_overview_row(
    get_string('overview:feature:editexisting', 'tool_bulkcoursestudio'),
    get_string('overview:feature:editexisting_desc', 'tool_bulkcoursestudio'),
    new moodle_url('/admin/tool/bulkcoursestudio/edit_course.php')
);

echo $OUTPUT->heading(get_string('overview:section:mod', 'tool_bulkcoursestudio'), 3);

echo html_writer::tag(
    'p',
    get_string('overview:mod_desc', 'tool_bulkcoursestudio'),
    ['class' => 'text-muted small']
);

echo $OUTPUT->heading(get_string('overview:section:developer', 'tool_bulkcoursestudio'), 3);

$developerlinks = [
    get_string('overview:developer:linkedin', 'tool_bulkcoursestudio') =>
        'https://www.linkedin.com/in/michaeladeniran',
    get_string('overview:developer:github', 'tool_bulkcoursestudio') =>
        'https://github.com/emiiyaseyi',
    get_string('overview:developer:email', 'tool_bulkcoursestudio') =>
        'mailto:michaeladeniransnr@gmail.com',
];

echo html_writer::start_tag('ul', ['class' => 'list-unstyled']);
foreach ($developerlinks as $label => $url) {
    echo html_writer::tag('li', html_writer::link($url, $label));
}
echo html_writer::end_tag('ul');

echo $OUTPUT->footer();
