<?php
/**
 * Unit tests for AI Proctor Privacy Provider
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Unit tests for the AI Proctor privacy provider
 *
 * @group block_ai_proctor
 * @covers \block_ai_proctor\privacy\provider
 */
class provider_test extends provider_testcase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test metadata collection
     */
    public function test_get_metadata() {
        $collection = new collection('block_ai_proctor');
        $metadata = provider::get_metadata($collection);
        
        $this->assertInstanceOf(collection::class, $metadata);
        $this->assertNotEmpty($metadata->get_collection());
    }

    /**
     * Test metadata includes required tables
     */
    public function test_metadata_includes_tables() {
        $collection = new collection('block_ai_proctor');
        $metadata = provider::get_metadata($collection);
        
        $items = $metadata->get_collection();
        $tableNames = array_map(function($item) {
            return $item->get_name();
        }, $items);
        
        $this->assertContains('block_ai_proctor', $tableNames);
        $this->assertContains('block_ai_proctor_config', $tableNames);
        $this->assertContains('block_ai_proctor_sessions', $tableNames);
    }

    /**
     * Test metadata includes external services
     */
    public function test_metadata_includes_external_services() {
        $collection = new collection('block_ai_proctor');
        $metadata = provider::get_metadata($collection);
        
        $items = $metadata->get_collection();
        $serviceNames = array_map(function($item) {
            return $item->get_name();
        }, $items);
        
        $this->assertContains('mediapipe_ai', $serviceNames);
        $this->assertContains('core_files', $serviceNames);
    }

    /**
     * Test getting contexts for user with data
     */
    public function test_get_contexts_for_userid_with_data() {
        global $DB;
        
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        
        // Create test data
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation',
            'timestamp' => time()
        ];
        $DB->insert_record('block_ai_proctor', $record);
        
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertNotEmpty($contextlist->get_contextids());
    }

    /**
     * Test getting contexts for user with no data
     */
    public function test_get_contexts_for_userid_no_data() {
        $user = $this->getDataGenerator()->create_user();
        
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test getting users in context with data
     */
    public function test_get_users_in_context_with_data() {
        global $DB;
        
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        
        // Create test data
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation',
            'timestamp' => time()
        ];
        $DB->insert_record('block_ai_proctor', $record);
        
        $userlist = new userlist($context, 'block_ai_proctor');
        provider::get_users_in_context($userlist);
        
        $this->assertContains($user->id, $userlist->get_userids());
    }

    /**
     * Test exporting user data
     */
    public function test_export_user_data() {
        global $DB;
        
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        
        // Create test evidence data
        $evidenceRecord = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Looking Down',
            'evidence_type' => 'image',
            'ai_confidence' => 85.5,
            'timestamp' => time(),
            'session_id' => 'test_session_123',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 Test Browser'
        ];
        $DB->insert_record('block_ai_proctor', $evidenceRecord);
        
        // Create test session data
        $sessionRecord = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'session_id' => 'test_session_123',
            'starttime' => time() - 3600,
            'endtime' => time(),
            'duration' => 3600,
            'violationcount' => 2,
            'evidencecount' => 1,
            'status' => 'completed'
        ];
        $DB->insert_record('block_ai_proctor_sessions', $sessionRecord);
        
        // Create approved context list
        $contextlist = new approved_contextlist($user, 'block_ai_proctor', [$context->id]);
        
        // Export data
        provider::export_user_data($contextlist);
        
        // Verify data was exported
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test deleting user data
     */
    public function test_delete_data_for_user() {
        global $DB;
        
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        
        // Create test data
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation',
            'timestamp' => time()
        ];
        $evidenceId = $DB->insert_record('block_ai_proctor', $record);
        
        // Verify data exists
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $evidenceId]));
        
        // Delete user data
        $contextlist = new approved_contextlist($user, 'block_ai_proctor', [$context->id]);
        provider::delete_data_for_user($contextlist);
        
        // Verify data was deleted
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $evidenceId]));
    }

    /**
     * Test deleting all users data in context
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        
        // Create test data for multiple users
        $record1 = (object) [
            'userid' => $user1->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation 1',
            'timestamp' => time()
        ];
        $record2 = (object) [
            'userid' => $user2->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation 2',
            'timestamp' => time()
        ];
        
        $evidenceId1 = $DB->insert_record('block_ai_proctor', $record1);
        $evidenceId2 = $DB->insert_record('block_ai_proctor', $record2);
        
        // Verify data exists
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $evidenceId1]));
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $evidenceId2]));
        
        // Delete all data in context
        provider::delete_data_for_all_users_in_context($context);
        
        // Verify all data was deleted
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $evidenceId1]));
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $evidenceId2]));
    }

    /**
     * Test deleting data for multiple specific users
     */
    public function test_delete_data_for_users() {
        global $DB;
        
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        
        // Create test data for multiple users
        $record1 = (object) [
            'userid' => $user1->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation 1',
            'timestamp' => time()
        ];
        $record2 = (object) [
            'userid' => $user2->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation 2',
            'timestamp' => time()
        ];
        $record3 = (object) [
            'userid' => $user3->id,
            'courseid' => $course->id,
            'violation_type' => 'Test Violation 3',
            'timestamp' => time()
        ];
        
        $evidenceId1 = $DB->insert_record('block_ai_proctor', $record1);
        $evidenceId2 = $DB->insert_record('block_ai_proctor', $record2);
        $evidenceId3 = $DB->insert_record('block_ai_proctor', $record3);
        
        // Delete data for specific users (user1 and user2, but not user3)
        $userlist = new approved_userlist($context, 'block_ai_proctor', [$user1->id, $user2->id]);
        provider::delete_data_for_users($userlist);
        
        // Verify correct data was deleted
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $evidenceId1]));
        $this->assertFalse($DB->record_exists('block_ai_proctor', ['id' => $evidenceId2]));
        $this->assertTrue($DB->record_exists('block_ai_proctor', ['id' => $evidenceId3])); // Should still exist
    }

    /**
     * Test privacy strings exist
     */
    public function test_privacy_strings_exist() {
        // Test that required privacy strings are defined
        $this->assertNotEmpty(get_string('privacy:metadata:block_ai_proctor', 'block_ai_proctor'));
        $this->assertNotEmpty(get_string('privacy:metadata:block_ai_proctor:userid', 'block_ai_proctor'));
        $this->assertNotEmpty(get_string('privacy:metadata:block_ai_proctor_sessions', 'block_ai_proctor'));
        $this->assertNotEmpty(get_string('privacy:metadata:mediapipe_ai', 'block_ai_proctor'));
    }
}
