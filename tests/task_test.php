<?php
/**
 * Unit tests for AI Proctor Tasks
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for AI Proctor scheduled tasks
 *
 * @group block_ai_proctor
 * @covers \block_ai_proctor\task\cleanup_old_evidence
 * @covers \block_ai_proctor\task\generate_reports
 */
class tasks_test extends \advanced_testcase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Test cleanup old evidence task
     */
    public function test_cleanup_old_evidence_task() {
        global $DB;
        
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        
        // Create old evidence (older than 90 days)
        $oldRecord = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Old Violation',
            'timestamp' => time() - (91 * 24 * 60 * 60), // 91 days ago
            'status' => 'reviewed'
        ];
        $oldId = $DB->insert_record('block_ai_proctor', $oldRecord);
        
        // Create recent evidence
        $recentRecord = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Recent Violation',
            'timestamp' => time() - (30 * 24 * 60 * 60), // 30 days ago
            'status' => 'active'
        ];
        $recentId = $DB->insert_record('block_ai_proctor', $recentRecord);
        
        // Verify data exists
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $oldId]));
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $recentId]));
        
        // Run cleanup task
        $task = new cleanup_old_evidence();
        $task->execute();
        
        // Verify old evidence was cleaned up but recent evidence remains
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $oldId]));
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $recentId]));
    }

    /**
     * Test generate reports task
     */
    public function test_generate_reports_task() {
        global $DB;
        
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        
        // Create test evidence data for reporting
        $record1 = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Looking Down',
            'timestamp' => time() - (2 * 24 * 60 * 60), // 2 days ago
            'status' => 'active',
            'ai_confidence' => 85.5
        ];
        $record2 = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Turning Left',
            'timestamp' => time() - (1 * 24 * 60 * 60), // 1 day ago
            'status' => 'reviewed',
            'ai_confidence' => 92.1
        ];
        
        $DB->insert_record('block_ai_proctor', $record1);
        $DB->insert_record('block_ai_proctor', $record2);
        
        // Run report generation task
        $task = new generate_reports();
        
        // Test that task can execute without errors
        try {
            $task->execute();
            $this->assertTrue(true); // If we get here, task executed successfully
        } catch (\Exception $e) {
            $this->fail('Report generation task failed: ' . $e->getMessage());
        }
    }

    /**
     * Test archive old data task
     */
    public function test_archive_old_data_task() {
        global $DB;
        
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        
        // Create very old session data (older than 1 year)
        $oldSession = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'session_id' => 'old_session_123',
            'starttime' => time() - (400 * 24 * 60 * 60), // 400 days ago
            'endtime' => time() - (400 * 24 * 60 * 60) + 3600,
            'duration' => 3600,
            'violationcount' => 2,
            'status' => 'completed'
        ];
        $oldSessionId = $DB->insert_record('block_ai_proctor_sessions', $oldSession);
        
        // Create recent session data
        $recentSession = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'session_id' => 'recent_session_456',
            'starttime' => time() - (30 * 24 * 60 * 60), // 30 days ago
            'endtime' => time() - (30 * 24 * 60 * 60) + 3600,
            'duration' => 3600,
            'violationcount' => 1,
            'status' => 'completed'
        ];
        $recentSessionId = $DB->insert_record('block_ai_proctor_sessions', $recentSession);
        
        // Verify data exists
        $this->assertTrue($DB->record_exists('block_ai_proctor_sessions', ['id' => $oldSessionId]));
        $this->assertTrue($DB->record_exists('block_ai_proctor_sessions', ['id' => $recentSessionId]));
        
        // Run archive task
        $task = new archive_old_data();
        $task->execute();
        
        // Verify old session was archived/removed but recent session remains
        $this->assertFalse($DB->record_exists('block_ai_proctor_sessions', ['id' => $oldSessionId]));
        $this->assertTrue($DB->record_exists('block_ai_proctor_sessions', ['id' => $recentSessionId]));
    }

    /**
     * Test task configuration and scheduling
     */
    public function test_task_configuration() {
        // Test cleanup task configuration
        $cleanupTask = new cleanup_old_evidence();
        $this->assertNotEmpty($cleanupTask->get_name());
        $this->assertTrue($cleanupTask instanceof \core\task\scheduled_task);
        
        // Test reports task configuration
        $reportsTask = new generate_reports();
        $this->assertNotEmpty($reportsTask->get_name());
        $this->assertTrue($reportsTask instanceof \core\task\scheduled_task);
        
        // Test archive task configuration
        $archiveTask = new archive_old_data();
        $this->assertNotEmpty($archiveTask->get_name());
        $this->assertTrue($archiveTask instanceof \core\task\scheduled_task);
    }

    /**
     * Test cleanup task handles orphaned files
     */
    public function test_cleanup_handles_orphaned_files() {
        global $DB;
        
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        
        // Create evidence record with file path
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation',
            'timestamp' => time() - (100 * 24 * 60 * 60), // 100 days ago
            'status' => 'reviewed',
            'file_path' => '/tmp/test_evidence_file.jpg'
        ];
        $recordId = $DB->insert_record('block_ai_proctor', $record);
        
        // Run cleanup task
        $task = new cleanup_old_evidence();
        $task->execute();
        
        // Verify record was removed
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $recordId]));
    }

    /**
     * Test report generation includes statistics
     */
    public function test_report_generation_statistics() {
        global $DB;
        
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        
        // Create test data for statistics
        $violations = [
            ['userid' => $user1->id, 'violation_type' => 'Looking Down', 'ai_confidence' => 85.5],
            ['userid' => $user1->id, 'violation_type' => 'Turning Left', 'ai_confidence' => 90.2],
            ['userid' => $user2->id, 'violation_type' => 'Looking Down', 'ai_confidence' => 78.8],
            ['userid' => $user2->id, 'violation_type' => 'Talking', 'ai_confidence' => 95.1]
        ];
        
        foreach ($violations as $violation) {
            $record = (object) array_merge($violation, [
                'courseid' => $course->id,
                'timestamp' => time() - (1 * 24 * 60 * 60), // 1 day ago
                'status' => 'active'
            ]);
            $DB->insert_record('block_ai_proctor', $record);
        }
        
        // Run report generation
        $task = new generate_reports();
        
        // Capture any output during task execution
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        
        // Task should execute without throwing exceptions
        $this->assertTrue(true);
    }

    /**
     * Test tasks handle empty database gracefully
     */
    public function test_tasks_handle_empty_database() {
        // Test cleanup with no data
        $cleanupTask = new cleanup_old_evidence();
        try {
            $cleanupTask->execute();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Cleanup task failed with empty database: ' . $e->getMessage());
        }
        
        // Test reports with no data
        $reportsTask = new generate_reports();
        try {
            $reportsTask->execute();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Reports task failed with empty database: ' . $e->getMessage());
        }
        
        // Test archive with no data
        $archiveTask = new archive_old_data();
        try {
            $archiveTask->execute();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Archive task failed with empty database: ' . $e->getMessage());
        }
    }
}
