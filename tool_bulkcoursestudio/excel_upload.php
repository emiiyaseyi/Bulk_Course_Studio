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
 * Upload screen for a filled-in Excel template (spec §3.2 step 3). On
 * successful parse, redirects into step_matchfiles.php (spec §3.2 step 4)
 * if the build has any resource/folder activity expecting an attached
 * file, or straight to step_preview.php — the exact same preview screen
 * Flow A's wizard uses — if it doesn't.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_bulkcoursestudio\form\excel_upload_form;
use tool_bulkcoursestudio\excel\template_parser;

admin_externalpage_setup('tool_bulkcoursestudio', '', null, '', ['pagelayout' => 'admin']);
require_capability('tool/bulkcoursestudio:bulkupload', context_system::instance());

$PAGE->set_url('/admin/tool/bulkcoursestudio/excel_upload.php');
$PAGE->set_title(get_string('uploadtemplate', 'tool_bulkcoursestudio'));

$form = new excel_upload_form($PAGE->url);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/bulkcoursestudio/index.php'));
} else if ($form->get_data()) {
    $tempdir = make_request_directory();
    $filepath = $tempdir . '/template.xlsx';
    $form->save_file('templatefile', $filepath);

    try {
        $buildid = template_parser::parse($filepath);
    } catch (\moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    redirect(new moodle_url('/admin/tool/bulkcoursestudio/step_matchfiles.php', ['buildid' => $buildid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadtemplate', 'tool_bulkcoursestudio'));
echo $OUTPUT->notification(get_string('excel:filematchinfo', 'tool_bulkcoursestudio'), 'info');
$form->display();
echo $OUTPUT->footer();
