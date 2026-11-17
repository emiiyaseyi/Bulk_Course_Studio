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
 * Unit tests for build_manager::sync()/commit()/publish()/discard() — the
 * methods that actually write a real course via core APIs, as opposed to
 * preview()'s read-only validation (covered separately in
 * build_manager_preview_test.php).
 *
 * These run against Moodle's real test-database course/module APIs (via
 * advanced_testcase + resetAfterTest()), not mocks, so they exercise the
 * exact code paths a live site would — create_course(), add_moduleinfo(),
 * course_create_sections_if_missing(), delete_course() — the same as the
 * existing preview test file's own stated rationale for why this level is
 * the right one for sync()/commit(), unlike full Behat coverage of the
 * wizard UI itself (still a gap — see the project's own README/spec).
 *
 * HONEST CAVEAT: written and syntax-checked (php -l) in an environment
 * with no live Moodle codebase to run PHPUnit against — these have not
 * actually been executed. Run `vendor/bin/phpunit
 * tool_bulkcoursestudio_build_manager_sync_testcase` (or the equivalent
 * via the Moodle PHPUnit CLI) on a real install before relying on them.
 *
 * @package    tool_bulkcoursestudio
 * @copyright  2026 Bulk Course Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_bulkcoursestudio\build_manager
 */
final class build_manager_sync_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a minimal valid draft build for use in a test.
     *
     * @param array $overrides merged into a minimal valid coursedata array
     * @param array $rowoverrides merged into the build row itself (e.g. status, courseid)
     * @return int the new build's id
     */
    private function create_build(array $overrides = [], array $rowoverrides = []): int {
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

        $row = array_merge([
            'courseid' => null,
            'status' => 'draft',
            'source' => 'wizard',
            'coursedata' => json_encode($coursedata),
            'formatoptions' => json_encode([]),
            'scheduledtime' => null,
            'usermodified' => 2, // Admin.
            'timecreated' => time(),
            'timemodified' => time(),
        ], $rowoverrides);

        return (int) $DB->insert_record('tool_bulkcoursestudio_build', (object) $row);
    }

    /**
     * Create a draft section for use in a test.
     *
     * @param int $buildid
     * @param string $name
     * @param int $sortorder
     * @return int the new section's id
     */
    private function add_section(int $buildid, string $name = 'Section 1', int $sortorder = 1): int {
        global $DB;
        return (int) $DB->insert_record('tool_bulkcoursestudio_section', (object) [
            'buildid' => $buildid,
            'sortorder' => $sortorder,
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

    public function test_sync_creates_a_hidden_course(): void {
        $buildid = $this->create_build();

        $courseid = build_manager::sync($buildid);

        $course = get_course($courseid);
        $this->assertSame('Test course', $course->fullname);
        $this->assertSame(0, (int) $course->visible, 'A newly synced course must stay hidden until publish().');
    }

    public function test_sync_records_the_courseid_on_the_build(): void {
        global $DB;
        $buildid = $this->create_build();

        $courseid = build_manager::sync($buildid);

        $build = $DB->get_record('tool_bulkcoursestudio_build', ['id' => $buildid], '*', MUST_EXIST);
        $this->assertEquals($courseid, $build->courseid);
    }

    public function test_sync_defaults_to_topics_format_when_unset(): void {
        $buildid = $this->create_build(['format' => '']);

        $courseid = build_manager::sync($buildid);

        $course = get_course($courseid);
        $this->assertSame('topics', $course->format);
    }

    public function test_sync_creates_sections_in_order(): void {
        $buildid = $this->create_build();
        $this->add_section($buildid, 'Introduction', 1);
        $this->add_section($buildid, 'Week 1', 2);

        $courseid = build_manager::sync($buildid);

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $this->assertSame('Introduction', $modinfo->get_section_info(1)->name);
        $this->assertSame('Week 1', $modinfo->get_section_info(2)->name);
    }

    public function test_sync_creates_an_activity_and_records_its_cmid(): void {
        global $DB;
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $activityid = $this->add_activity($sectionid, 'page', 'A real page', [
            'content' => 'Hello world',
            'contentformat' => FORMAT_HTML,
        ]);

        $courseid = build_manager::sync($buildid);

        $activity = $DB->get_record('tool_bulkcoursestudio_activity', ['id' => $activityid], '*', MUST_EXIST);
        $this->assertNotEmpty($activity->committedcmid, 'commit_activity() must record the new cmid.');

        $cm = get_coursemodule_from_id('page', $activity->committedcmid, $courseid, false, MUST_EXIST);
        $this->assertSame('A real page', $cm->name);
    }

    public function test_sync_is_idempotent_and_updates_rather_than_duplicates(): void {
        global $DB;
        $buildid = $this->create_build();
        $sectionid = $this->add_section($buildid);
        $activityid = $this->add_activity($sectionid, 'page', 'Original name', [
            'content' => 'v1',
            'contentformat' => FORMAT_HTML,
        ]);

        $courseid = build_manager::sync($buildid);
        $firstcmid = $DB->get_field('tool_bulkcoursestudio_activity', 'committedcmid', ['id' => $activityid]);

        // Simulate the admin renaming the activity in the wizard, then
        // re-syncing (as every wizard step now does).
        $DB->set_field('tool_bulkcoursestudio_activity', 'name', 'Renamed', ['id' => $activityid]);
        $secondcourseid = build_manager::sync($buildid);
        $secondcmid = $DB->get_field('tool_bulkcoursestudio_activity', 'committedcmid', ['id' => $activityid]);

        $this->assertSame($courseid, $secondcourseid, 'Re-syncing must not create a second course.');
        $this->assertEquals($firstcmid, $secondcmid, 'Re-syncing must update the existing module, not create a new one.');

        $cm = get_coursemodule_from_id('page', $secondcmid, $courseid, false, MUST_EXIST);
        $this->assertSame('Renamed', $cm->name);

        $modinfo = get_fast_modinfo($courseid);
        $this->assertCount(1, $modinfo->get_cms(), 'There must still be exactly one course module after a re-sync.');
    }

    public function test_sync_does_not_disturb_visibility_of_an_already_published_course(): void {
        global $DB;
        $buildid = $this->create_build();
        $courseid = build_manager::sync($buildid);
        build_manager::publish($buildid);

        $this->assertEquals(1, get_course($courseid)->visible);

        // A later edit-and-resync of a published build must not re-hide it.
        $DB->set_field('tool_bulkcoursestudio_build', 'coursedata', json_encode([
            'fullname' => 'Renamed course',
            'shortname' => $DB->get_field('course', 'shortname', ['id' => $courseid]),
            'category' => $DB->get_field('course', 'category', ['id' => $courseid]),
            'format' => 'topics',
            'startdate' => time(),
            'enddate' => 0,
        ]), ['id' => $buildid]);

        build_manager::sync($buildid);

        $this->assertEquals(1, get_course($courseid)->visible, 'Re-syncing a published build must not re-hide its course.');
        $this->assertSame('Renamed course', get_course($courseid)->fullname);
    }

    public function test_commit_throws_when_preview_has_errors(): void {
        $buildid = $this->create_build(['fullname' => '']);

        $this->expectException(\moodle_exception::class);
        build_manager::commit($buildid);
    }

    public function test_commit_succeeds_and_returns_courseid_when_preview_is_clean(): void {
        $buildid = $this->create_build();

        $courseid = build_manager::commit($buildid);

        $this->assertGreaterThan(0, $courseid);
        $this->assertSame('Test course', get_course($courseid)->fullname);
    }

    public function test_publish_flips_visibility_and_status(): void {
        global $DB;
        $buildid = $this->create_build();
        $courseid = build_manager::sync($buildid);

        build_manager::publish($buildid);

        $this->assertEquals(1, get_course($courseid)->visible);
        $this->assertSame('published', $DB->get_field('tool_bulkcoursestudio_build', 'status', ['id' => $buildid]));
    }

    public function test_publish_throws_if_build_has_not_been_committed(): void {
        $buildid = $this->create_build();

        $this->expectException(\coding_exception::class);
        build_manager::publish($buildid);
    }

    public function test_discard_soft_deletes_the_course_of_an_unpublished_build(): void {
        $buildid = $this->create_build();
        $courseid = build_manager::sync($buildid);

        build_manager::discard($buildid);

        // Delete_course() moves the course to the recycle bin — it is no
        // longer a normal, loadable course record.
        $this->expectException(\dml_missing_record_exception::class);
        get_course($courseid);
    }

    public function test_discard_does_not_delete_the_course_of_a_published_build(): void {
        $buildid = $this->create_build();
        $courseid = build_manager::sync($buildid);
        build_manager::publish($buildid);

        build_manager::discard($buildid);

        // The course itself must survive — only this tool's own draft
        // tracking of it is removed.
        $course = get_course($courseid);
        $this->assertSame($courseid, $course->id);
    }

    public function test_discard_deletes_the_draft_build_row(): void {
        global $DB;
        $buildid = $this->create_build();
        build_manager::sync($buildid);

        build_manager::discard($buildid);

        $this->assertFalse($DB->record_exists('tool_bulkcoursestudio_build', ['id' => $buildid]));
    }
}
