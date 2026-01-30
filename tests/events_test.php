<?php
/**
 * Unit tests for AI Proctor Events
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for AI Proctor events
 *
 * @group block_ai_proctor
 * @covers \block_ai_proctor\event\evidence_captured
 * @covers \block_ai_proctor\event\violation_detected
 * @covers \block_ai_proctor\event\monitoring_started
 */
class events_test extends \advanced_testcase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Test evidence captured event
     */
    public function test_evidence_captured_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = evidence_captured::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'violation_type' => 'Looking Down',
                'evidence_type' => 'image',
                'ai_confidence' => 85.5,
                'session_id' => 'test_session_123'
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals('Looking Down', $event->other['violation_type']);
        $this->assertEquals('image', $event->other['evidence_type']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(evidence_captured::class, $events[0]);
    }

    /**
     * Test violation detected event
     */
    public function test_violation_detected_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = violation_detected::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'violation_type' => 'Turning Left',
                'severity' => 'medium',
                'ai_confidence' => 92.1,
                'session_id' => 'test_session_456'
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals('Turning Left', $event->other['violation_type']);
        $this->assertEquals('medium', $event->other['severity']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(violation_detected::class, $events[0]);
    }

    /**
     * Test monitoring started event
     */
    public function test_monitoring_started_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = monitoring_started::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'session_id' => 'test_session_789',
                'browser_info' => 'Chrome 120',
                'ip_address' => '192.168.1.1'
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals('test_session_789', $event->other['session_id']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(monitoring_started::class, $events[0]);
    }

    /**
     * Test monitoring ended event
     */
    public function test_monitoring_ended_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = monitoring_ended::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'session_id' => 'test_session_end',
                'duration' => 3600,
                'violation_count' => 3,
                'evidence_count' => 2,
                'final_status' => 'completed'
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals(3600, $event->other['duration']);
        $this->assertEquals(3, $event->other['violation_count']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(monitoring_ended::class, $events[0]);
    }

    /**
     * Test block viewed event
     */
    public function test_block_viewed_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = block_viewed::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'user_role' => 'student',
                'block_instance_id' => 123
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals('student', $event->other['user_role']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(block_viewed::class, $events[0]);
    }

    /**
     * Test configuration updated event
     */
    public function test_configuration_updated_event() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Create and trigger event
        $event = configuration_updated::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => [
                'setting_name' => 'sensitivity_level',
                'old_value' => 'medium',
                'new_value' => 'high'
            ]
        ]);
        
        // Test event properties
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals('sensitivity_level', $event->other['setting_name']);
        
        // Test event can be triggered
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(configuration_updated::class, $events[0]);
    }

    /**
     * Test event descriptions are proper
     */
    public function test_event_descriptions() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Test evidence captured description
        $event = evidence_captured::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => ['violation_type' => 'Looking Down', 'evidence_type' => 'image']
        ]);
        $description = $event->get_description();
        $this->assertStringContainsString('evidence', $description);
        $this->assertStringContainsString('captured', $description);
        
        // Test violation detected description  
        $event = violation_detected::create([
            'context' => $context,
            'userid' => $user->id,
            'other' => ['violation_type' => 'Turning Left']
        ]);
        $description = $event->get_description();
        $this->assertStringContainsString('violation', $description);
        $this->assertStringContainsString('detected', $description);
    }

    /**
     * Test event validation
     */
    public function test_event_validation() {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        
        // Test that events require proper context
        $this->expectException(\coding_exception::class);
        
        evidence_captured::create([
            'userid' => $user->id,
            // Missing required context
            'other' => ['violation_type' => 'Test']
        ]);
    }
}
