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

namespace tool_bulkcoursestudio\excel;

use tool_bulkcoursestudio\draft_repository;

/**
 * Unit tests for template_parser::parse() — Flow B's entry point that turns
 * an uploaded .xlsx into the same draft rows the on-screen wizard produces.
 *
 * Builds each test workbook in memory with PhpSpreadsheet directly (rather
 * than depending on template_generator::build(), so a change to the
 * generator's example-row content can't silently change what these tests
 * parse), writes it to a temp file, and asserts on the resulting draft rows
 * read back via draft_repository — the same contract step_matchfiles.php
 * and step_preview.php rely on after a Flow B upload.
 *
 * HONEST CAVEAT: written and syntax-checked (php -l) in an environment
 * with no live Moodle codebase (and so no bundled PhpSpreadsheet) to run
 * PHPUnit against — these have not actually been executed. Run them on a
 * real install before relying on them.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_bulkcoursestudio\excel\template_parser
 */
final class template_parser_test extends \advanced_testcase {
    /** @var string[] temp files created by build_workbook_file(), cleaned up in tearDown(). */
    private $tempfiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        foreach ($this->tempfiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempfiles = [];
        parent::tearDown();
    }

    /**
     * Build a 3-sheet workbook (Course / Sections / Activities) from
     * explicit row data and save it to a temp .xlsx file.
     *
     * @param array $courserows each a flat row matching the Course sheet's columns
     * @param array $sectionrows each a flat row matching the Sections sheet's columns
     * @param array $activityrows each a flat row matching the Activities sheet's columns
     * @param string[] $sheetnames override to omit/rename a sheet, for the missing-sheet test
     * @return string path to the saved .xlsx file
     */
    private function build_workbook_file(
        array $courserows,
        array $sectionrows = [],
        array $activityrows = [],
        array $sheetnames = ['Course', 'Sections', 'Activities']
    ): string {
        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheets = [
            $sheetnames[0] ?? null => array_merge(
                [['fullname', 'shortname', 'category_path', 'summary', 'format', 'startdate', 'enddate', 'enablecompletion']],
                $courserows
            ),
            $sheetnames[1] ?? null => array_merge(
                [['section_number', 'name', 'summary']],
                $sectionrows
            ),
            $sheetnames[2] ?? null => array_merge(
                [['section_number', 'activity_type', 'name', 'description', 'embed_url', 'local_filename', 'completion_view']],
                $activityrows
            ),
        ];

        $first = true;
        foreach ($sheets as $name => $rows) {
            if ($name === null) {
                continue;
            }
            $sheet = $first ? $workbook->getActiveSheet() : $workbook->createSheet();
            $first = false;
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1');
        }

        $path = tempnam(sys_get_temp_dir(), 'bcs_test_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook);
        $writer->save($path);
        $this->tempfiles[] = $path;

        return $path;
    }

    public function test_parse_creates_a_build_from_the_course_row(): void {
        $path = $this->build_workbook_file([
            ['Parsed course', 'parsedcourse1', '', 'A summary', 'topics', '2027-01-06', '', '1'],
        ]);

        $buildid = template_parser::parse($path);

        $build = draft_repository::get_build($buildid);
        $coursedata = json_decode($build->coursedata, true);
        $this->assertSame('Parsed course', $coursedata['fullname']);
        $this->assertSame('parsedcourse1', $coursedata['shortname']);
        $this->assertSame('topics', $coursedata['format']);
        $this->assertSame('excel', $build->source);
    }

    public function test_parse_skips_the_commented_out_example_row(): void {
        $path = $this->build_workbook_file([
            ['# Example Course', 'EX101', '', 'Replace this row.', 'topics', '2027-01-06', '', '1'],
            ['Real course', 'realcourse1', '', 'A summary', 'topics', '2027-01-06', '', '1'],
        ]);

        $buildid = template_parser::parse($path);

        $coursedata = json_decode(draft_repository::get_build($buildid)->coursedata, true);
        $this->assertSame('Real course', $coursedata['fullname'], 'The "#"-prefixed example row must be skipped.');
    }

    public function test_parse_throws_when_no_real_course_row_exists(): void {
        $path = $this->build_workbook_file([
            ['# Example Course', 'EX101', '', 'Replace this row.', 'topics', '2027-01-06', '', '1'],
        ]);

        $this->expectException(\moodle_exception::class);
        template_parser::parse($path);
    }

    public function test_parse_throws_when_a_required_sheet_is_missing(): void {
        $path = $this->build_workbook_file(
            [['Course', 'c1', '', '', 'topics', '2027-01-06', '', '1']],
            [],
            [],
            ['Course', null, 'Activities'] // Sections sheet omitted.
        );

        $this->expectException(\moodle_exception::class);
        template_parser::parse($path);
    }

    public function test_parse_creates_sections_mapped_by_section_number(): void {
        $path = $this->build_workbook_file(
            [['Course with sections', 'cws1', '', '', 'topics', '2027-01-06', '', '1']],
            [
                ['1', 'Introduction', 'Welcome'],
                ['2', 'Week 1', ''],
            ]
        );

        $buildid = template_parser::parse($path);

        $sections = draft_repository::get_sections($buildid);
        $names = array_map(static fn($s) => $s->name, array_values($sections));
        $this->assertSame(['Introduction', 'Week 1'], $names);
    }

    public function test_parse_attaches_activities_to_the_right_section(): void {
        $path = $this->build_workbook_file(
            [['Course with activities', 'cwa1', '', '', 'topics', '2027-01-06', '', '1']],
            [
                ['1', 'Introduction', ''],
                ['2', 'Week 1', ''],
            ],
            [
                ['2', 'page', 'Week 1 reading', 'Read this first.', '', '', '0'],
            ]
        );

        $buildid = template_parser::parse($path);

        $sections = array_values(draft_repository::get_sections($buildid));
        $week1 = $sections[1];
        $activities = draft_repository::get_activities($week1->id);

        $this->assertCount(1, $activities);
        $this->assertSame('Week 1 reading', reset($activities)->name);
    }

    public function test_parse_drops_an_activity_whose_section_number_is_unknown(): void {
        $path = $this->build_workbook_file(
            [['Course', 'conly1', '', '', 'topics', '2027-01-06', '', '1']],
            [
                ['1', 'Introduction', ''],
            ],
            [
                // Section 9 doesn't exist in the Sections sheet above.
                ['9', 'page', 'Orphan activity', '', '', '', '0'],
            ]
        );

        $buildid = template_parser::parse($path);

        $sections = array_values(draft_repository::get_sections($buildid));
        $this->assertCount(0, draft_repository::get_activities($sections[0]->id));
    }

    public function test_parse_sets_embedurl_for_bulkcoursestudio_activities(): void {
        $path = $this->build_workbook_file(
            [['Course', 'cembed1', '', '', 'topics', '2027-01-06', '', '1']],
            [['1', 'Introduction', '']],
            [['1', 'bulkcoursestudio', 'Welcome video', 'Watch first.', 'https://www.youtube.com/watch?v=abc', '', '1']]
        );

        $buildid = template_parser::parse($path);

        $sections = array_values(draft_repository::get_sections($buildid));
        $activities = array_values(draft_repository::get_activities($sections[0]->id));
        $moduledata = json_decode($activities[0]->moduledata, true);

        $this->assertSame('https://www.youtube.com/watch?v=abc', $moduledata['embedurl']);
        $completiondata = json_decode($activities[0]->completiondata, true);
        $this->assertSame(1, $completiondata['completionview']);
    }

    public function test_parse_sets_localfilename_for_resource_activities(): void {
        $path = $this->build_workbook_file(
            [['Course', 'cfile1', '', '', 'topics', '2027-01-06', '', '1']],
            [['1', 'Introduction', '']],
            [['1', 'resource', 'Handout', '', '', 'handout.pdf', '0']]
        );

        $buildid = template_parser::parse($path);

        $sections = array_values(draft_repository::get_sections($buildid));
        $activities = array_values(draft_repository::get_activities($sections[0]->id));
        $moduledata = json_decode($activities[0]->moduledata, true);

        $this->assertSame('handout.pdf', $moduledata['localfilename']);
    }
}
