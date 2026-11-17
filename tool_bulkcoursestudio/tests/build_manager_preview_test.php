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

namespace tool_bulkcoursestudio;

/**
 * Unit tests for build_manager::preview() — the read-only validation pass
 * both Flow A and Flow B route through before commit(). Builds draft rows
 * directly against the plugin's own tables (not through draft_repository,
 * so each test only depends on the shape preview() actually reads, not on
 * the wizard-step controllers) and asserts on the resulting errors/
 * warnings lists.
 *
 * Deliberately does not test commit() here: commit() writes real courses
 * and modules via core APIs, which is better covered by a higher-level
 * Behat test exercising the actual wizard than a unit test re-mocking half
 * of course/modlib.php. preview() is pure validation against DB state and
 * is the right level for fast unit coverage.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_bulkcoursestudio\build_manager
 */
final class build_manager_preview_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a minimal valid draft build for use in a test.
     *
     * @param array $overrides merged into a minimal valid coursedata array
     * @return int the new build's id
     */
    private function create_build(array $overrides = []): int {
        global $DB;

        $category = $this->getDataGenerator()->create_category();

        $coursedata = array_merge([
            'fullname' => 'Test course',
            'shortname' => 'testcourse' . uniqid(),
            'category' => $category->id,
            'format' => 'topics',
            'startdate' => time(),
            'enddate' => 0,
            'visible' => 0,
        ], $overrides);

        return (int) $DB->insert_record('tool_bulkcoursestudio_build', (object) [
            'courseid' => null,
            'status' => 'draft',
            'source' => 'wizard',
            'coursedata' => json_encode($coursedata),
            'formatoptions' => json_encode([]),
            'scheduledtime' => null,
            'usermodified' => 2, // Admin.
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a draft section for use in a test.
     *
     * @param int $buildid
     * @param string $name
     * @return int the new section's id
     */
    private function add_section(int $buildid, string $name = 'Section 1'): int {
        global $DB;
        return (int) $DB->insert_record('tool_bulkcoursestudio_section', (object) [
            'buildid' => $buildid,
            'sortorder' => 1,
            'name' => $name,
            'summary' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a draft activity for use in a test.
     *
     * @param int $sectionid
     * @param string $modname
     * @param string $name
     * @param array $moduledata
     * @return int the new activity's id
     */
    private function add_activity(int $sectionid, string $modname, string $name, array $moduledata = []): int {
        global $DB;
        return (int) $DB->insert_record('tool_bulkcoursestudio_activity', (object) [
            'sectionid' => $sectionid,
            'sortorder' => 1,
            'modname' => $modname,
            'name' => $name,
            'moduledata' => json_encode($moduledata),
            'completiondata' => json_encode([]),
            'committedcmid' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_minimal_valid_build_has_no_errors(): void {
        $buildid = $this->create_build();

        $preview = build_manager::preview($buildid);

        $this->assertSame([], $preview->errors);
    }

    public function test_missing_fullname_is_an_error(): void {
        $buildid = $this->create_build(['fullname' => '']);

        $preview = build_manager::preview($buildid);

        $this->assertNotEmpty($preview->errors);
        $this->assertStringContainsString(
            get_string('preview:error:nofullname', 'tool_bulkcoursestudio'),
            implode(' ', $preview->errors)
        );
    }

    public function test_nonexistent_category_is_an_error(): void {
        $buildid = $this->create_build(['category' => 999999]);

        $preview = build_manager::preview($buildid);

        $this->assertStringContainsString(
            get_string('preview:error:badcategory', 'tool_bulkcoursestudio'),
            implode(' ', $preview->errors)
        );
    }

    public function test_uninstalled_format_is_an_error(): void {
        $buildid = $this->create_build(['format' => 'definitely_not_a_real_format']);

        $preview = build_manager::preview($buildid);

        $this->assertNotEmpty($preview->errors);
    }

    public function test_enddate_before_startdate_is_an_error(): void {
        $now = time();
        $buildid = $this->create_build(['startdate' => $now, 'enddate' => $now - DAYSECS]);

        $preview = build_manager::preview($buildid);

        $this->assertStringContainsString(
            get_string('preview:error:enddatebeforestart', 'tool_bulkcoursestudio'),
            implode(' ', $preview->errors)
        );
    }

    public function test_unknown_activity_modname_is_an_error(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $this->add_activity($sectionid, 'totally_not_a_real_modname', 'Mystery activity');

        $preview = build_manager::preview($buildid);

        $this->assertStringContainsString(
            get_string('preview:error:unknownmod', 'tool_bulkcoursestudio', (object) [
                'section' => 'Section 1',
                'modname' => 'totally_not_a_real_modname',
            ]),
            implode(' ', $preview->errors)
        );
    }

    public function test_known_activity_modname_has_no_unknownmod_error(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $this->add_activity($sectionid, 'page', 'A real page');

        $preview = build_manager::preview($buildid);

        $this->assertSame([], $preview->errors);
    }

    public function test_bad_embed_url_on_embed_activity_is_an_error(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $this->add_activity($sectionid, 'bulkcoursestudio', 'Intro video', ['embedurl' => 'not a url at all']);

        $preview = build_manager::preview($buildid);

        $this->assertStringContainsString(
            get_string('preview:error:badembedurl', 'tool_bulkcoursestudio', (object) [
                'section' => 'Section 1',
                'activity' => 'Intro video',
            ]),
            implode(' ', $preview->errors)
        );
    }

    public function test_valid_youtube_embed_url_has_no_embed_error(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $this->add_activity($sectionid, 'bulkcoursestudio', 'Intro video', [
            'embedurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $preview = build_manager::preview($buildid);

        $this->assertSame([], $preview->errors);
    }

    public function test_resource_activity_with_unmatched_localfilename_is_a_warning_not_an_error(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $this->add_activity($sectionid, 'resource', 'Handout', ['localfilename' => 'handout.pdf']);

        $preview = build_manager::preview($buildid);

        $this->assertSame([], $preview->errors, 'A missing attached file should warn, not block the build.');
        $this->assertStringContainsString(
            get_string('preview:warning:missingfile', 'tool_bulkcoursestudio', (object) [
                'section' => 'Section 1',
                'activity' => 'Handout',
                'filename' => 'handout.pdf',
            ]),
            implode(' ', $preview->warnings)
        );
    }

    public function test_resource_activity_with_attached_file_has_no_missingfile_warning(): void {
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $activityid = $this->add_activity($sectionid, 'resource', 'Handout', ['localfilename' => 'handout.pdf']);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_bulkcoursestudio',
            'filearea' => 'activityfiles',
            'itemid' => $activityid,
            'filepath' => '/',
            'filename' => 'handout.pdf',
        ], 'fake pdf bytes for testing');

        $preview = build_manager::preview($buildid);

        $this->assertSame([], $preview->errors);
        $this->assertSame([], $preview->warnings);
    }
}
