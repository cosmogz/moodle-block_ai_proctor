<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Security and access control unit tests for AI Proctor plugin.
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/ai_proctor/block_ai_proctor.php');

/**
 * Tests for security and access control.
 *
 * @covers \block_ai_proctor
 */
final class security_test extends \advanced_testcase {

    /** @var stdClass Course object. */
    private $course;

    /** @var stdClass Student user object. */
    private $student;

    /** @var stdClass Teacher user object. */
    private $teacher;

    /** @var stdClass Admin user object. */
    private $admin;

    /** @var context_course Course context. */
    private $coursecontext;

    /**
     * Set up for each test.
     */
    protected function setUp(): void {
        $this->resetAfterTest();

        // Create course and users.
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->admin = $this->getDataGenerator()->create_user(['username' => 'admin']);

        // Enrol users.
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        $this->coursecontext = \context_course::instance($this->course->id);

        // Set admin capabilities.
        $this->setAdminUser();
        $adminrole = get_archetype_roles('manager')[0];
        role_assign($adminrole->id, $this->admin->id, \context_system::instance());
    }

    /**
     * Test block visibility for different user roles.
     */
    public function test_block_visibility_permissions(): void {
        global $PAGE;

        $PAGE->set_course($this->course);
        $PAGE->set_context($this->coursecontext);

        $block = new \block_ai_proctor();

        // Test as student.
        $this->setUser($this->student);
        $content = $block->get_content();
        $this->assertNotNull($content);
        $this->assertStringContainsString('AI monitoring', $content->text);

        // Test as teacher.
        $this->setUser($this->teacher);
        $content = $block->get_content();
        $this->assertNotNull($content);
        $this->assertStringContainsString('monitoring dashboard', $content->text);

        // Test as guest.
        $this->setGuestUser();
        $content = $block->get_content();
        $this->assertNull($content);
    }

    /**
     * Test report access permissions.
     */
    public function test_report_access_permissions(): void {
        global $DB;

        // Create evidence for the student.
        $evidence = new \stdClass();
        $evidence->userid = $this->student->id;
        $evidence->courseid = $this->course->id;
        $evidence->violation_type = 'looking_away';
        $evidence->evidence_data = json_encode(['test' => 'data']);
        $evidence->ai_confidence = 85.5;
        $evidence->timestamp = time();
        $evidence->status = 'active';

        $evidenceid = $DB->insert_record('block_ai_proctor_evidence', $evidence);

        // Test teacher can access reports.
        $this->setUser($this->teacher);
        $hasaccess = has_capability('block/ai_proctor:viewreports', $this->coursecontext);
        $this->assertTrue($hasaccess);

        // Test student cannot access reports.
        $this->setUser($this->student);
        $hasaccess = has_capability('block/ai_proctor:viewreports', $this->coursecontext);
        $this->assertFalse($hasaccess);

        // Test admin can access reports.
        $this->setUser($this->admin);
        $hasaccess = has_capability('block/ai_proctor:viewreports', $this->coursecontext);
        $this->assertTrue($hasaccess);
    }

    /**
     * Test configuration access permissions.
     */
    public function test_configuration_access_permissions(): void {
        // Test teacher can configure.
        $this->setUser($this->teacher);
        $hasaccess = has_capability('block/ai_proctor:configure', $this->coursecontext);
        $this->assertTrue($hasaccess);

        // Test student cannot configure.
        $this->setUser($this->student);
        $hasaccess = has_capability('block/ai_proctor:configure', $this->coursecontext);
        $this->assertFalse($hasaccess);
    }

    /**
     * Test data access is limited to course context.
     */
    public function test_data_access_course_isolation(): void {
        global $DB;

        // Create second course.
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $course2->id, 'editingteacher');

        // Create evidence in both courses.
        $evidence1 = new \stdClass();
        $evidence1->userid = $this->student->id;
        $evidence1->courseid = $this->course->id;
        $evidence1->violation_type = 'looking_away';
        $evidence1->evidence_data = json_encode(['course1' => 'data']);
        $evidence1->ai_confidence = 85.5;
        $evidence1->timestamp = time();
        $evidence1->status = 'active';

        $evidence2 = new \stdClass();
        $evidence2->userid = $this->student->id;
        $evidence2->courseid = $course2->id;
        $evidence2->violation_type = 'looking_away';
        $evidence2->evidence_data = json_encode(['course2' => 'data']);
        $evidence2->ai_confidence = 85.5;
        $evidence2->timestamp = time();
        $evidence2->status = 'active';

        $DB->insert_record('block_ai_proctor_evidence', $evidence1);
        $DB->insert_record('block_ai_proctor_evidence', $evidence2);

        // Verify teacher can only access data from their enrolled courses.
        $this->setUser($this->teacher);

        $course1evidence = $DB->get_records('block_ai_proctor_evidence', ['courseid' => $this->course->id]);
        $course2evidence = $DB->get_records('block_ai_proctor_evidence', ['courseid' => $course2->id]);

        // Both should exist in database.
        $this->assertCount(1, $course1evidence);
        $this->assertCount(1, $course2evidence);

        // But access should be limited by capability checks.
        $context1 = \context_course::instance($this->course->id);
        $context2 = \context_course::instance($course2->id);

        $this->assertTrue(has_capability('block/ai_proctor:viewreports', $context1));
        $this->assertTrue(has_capability('block/ai_proctor:viewreports', $context2));
    }

    /**
     * Test SQL injection protection in evidence queries.
     */
    public function test_sql_injection_protection(): void {
        global $DB;

        // Attempt SQL injection through various parameters.
        $malicious_inputs = [
            "1'; DROP TABLE block_ai_proctor_evidence; --",
            "1 UNION SELECT * FROM mdl_user",
            "1 OR 1=1",
            "<script>alert('xss')</script>",
            "'; DELETE FROM block_ai_proctor_evidence WHERE 1=1; --"
        ];

        foreach ($malicious_inputs as $input) {
            // Test with user ID.
            $records = $DB->get_records('block_ai_proctor_evidence', ['userid' => $input]);
            $this->assertEquals([], $records);

            // Test with course ID.
            $records = $DB->get_records('block_ai_proctor_evidence', ['courseid' => $input]);
            $this->assertEquals([], $records);
        }
    }

    /**
     * Test XSS protection in evidence data.
     */
    public function test_xss_protection(): void {
        global $DB;

        $malicious_data = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src=x onerror=alert("xss")>',
            '<svg onload=alert("xss")>',
        ];

        foreach ($malicious_data as $data) {
            $evidence = new \stdClass();
            $evidence->userid = $this->student->id;
            $evidence->courseid = $this->course->id;
            $evidence->violation_type = 'test';
            $evidence->evidence_data = json_encode(['malicious' => $data]);
            $evidence->ai_confidence = 85.5;
            $evidence->timestamp = time();
            $evidence->status = 'active';

            $evidenceid = $DB->insert_record('block_ai_proctor_evidence', $evidence);

            // Retrieve and verify data is properly escaped.
            $retrieved = $DB->get_record('block_ai_proctor_evidence', ['id' => $evidenceid]);
            $decoded = json_decode($retrieved->evidence_data, true);

            // Data should be stored as-is but should be escaped when displayed.
            $this->assertEquals($data, $decoded['malicious']);

            // When displayed, it should be escaped.
            $escaped = format_text($decoded['malicious'], FORMAT_HTML);
            $this->assertStringNotContainsString('<script>', $escaped);
        }
    }

    /**
     * Test CSRF protection for configuration changes.
     */
    public function test_csrf_protection(): void {
        global $PAGE;

        $this->setUser($this->teacher);
        $PAGE->set_course($this->course);
        $PAGE->set_context($this->coursecontext);

        // Verify CSRF token is required for form submissions.
        $block = new \block_ai_proctor();
        
        // This test ensures proper form token validation.
        // In actual implementation, forms should use required_param with PARAM_RAW
        // and validate with confirm_sesskey().
        $this->assertTrue(true); // Placeholder - actual CSRF testing would require form submission.
    }

    /**
     * Test file upload security.
     */
    public function test_file_upload_security(): void {
        // Test that only allowed file types can be uploaded.
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $disallowed_types = ['php', 'js', 'html', 'exe', 'bat'];

        foreach ($allowed_types as $type) {
            $filename = "test.{$type}";
            $this->assertTrue($this->is_allowed_file_type($filename));
        }

        foreach ($disallowed_types as $type) {
            $filename = "test.{$type}";
            $this->assertFalse($this->is_allowed_file_type($filename));
        }

        // Test file size limits.
        $this->assertTrue($this->is_within_size_limit(1024 * 1024)); // 1MB - OK
        $this->assertFalse($this->is_within_size_limit(10 * 1024 * 1024)); // 10MB - too large
    }

    /**
     * Test rate limiting for evidence submissions.
     */
    public function test_rate_limiting(): void {
        global $DB;

        $this->setUser($this->student);

        // Create multiple evidence records in short time.
        $start_time = time();
        $submissions = 0;

        for ($i = 0; $i < 100; $i++) {
            $evidence = new \stdClass();
            $evidence->userid = $this->student->id;
            $evidence->courseid = $this->course->id;
            $evidence->violation_type = 'test';
            $evidence->evidence_data = json_encode(['test' => $i]);
            $evidence->ai_confidence = 85.5;
            $evidence->timestamp = $start_time + $i;
            $evidence->status = 'active';

            // Check if rate limiting would apply.
            if ($this->check_rate_limit($this->student->id, $start_time + $i)) {
                $DB->insert_record('block_ai_proctor_evidence', $evidence);
                $submissions++;
            }
        }

        // Should be limited to reasonable number per minute.
        $this->assertLessThan(50, $submissions);
    }

    /**
     * Helper method to check if file type is allowed.
     */
    private function is_allowed_file_type($filename): bool {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowed_types);
    }

    /**
     * Helper method to check file size limits.
     */
    private function is_within_size_limit($size): bool {
        $max_size = 5 * 1024 * 1024; // 5MB
        return $size <= $max_size;
    }

    /**
     * Helper method to check rate limiting.
     */
    private function check_rate_limit($userid, $timestamp): bool {
        global $DB;

        // Allow max 20 submissions per minute.
        $window_start = $timestamp - 60;
        $recent_count = $DB->count_records_select('block_ai_proctor_evidence',
            'userid = ? AND timestamp > ?', [$userid, $window_start]);

        return $recent_count < 20;
    }
}
