<?php
/**
 * Unit tests for AI Proctor Block
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/ai_proctor/block_ai_proctor.php');

/**
 * Unit tests for the AI Proctor block
 *
 * @group block_ai_proctor
 * @covers \block_ai_proctor
 */
class block_ai_proctor_test extends \advanced_testcase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Test block initialization
     */
    public function test_block_init() {
        $block = new \block_ai_proctor();
        $block->init();
        
        $this->assertNotEmpty($block->title);
        $this->assertEquals(get_string('pluginname', 'block_ai_proctor'), $block->title);
    }

    /**
     * Test block specialization with custom title
     */
    public function test_block_specialization_with_title() {
        $block = new \block_ai_proctor();
        $block->init();
        
        // Mock config with custom title
        $block->config = (object) ['title' => 'Custom AI Monitor'];
        $block->context = \context_system::instance();
        
        $block->specialization();
        $this->assertEquals('Custom AI Monitor', $block->title);
    }

    /**
     * Test block specialization without custom title
     */
    public function test_block_specialization_without_title() {
        $block = new \block_ai_proctor();
        $block->init();
        
        $block->config = (object) [];
        $block->specialization();
        
        $this->assertEquals(get_string('pluginname', 'block_ai_proctor'), $block->title);
    }

    /**
     * Test block does not allow multiple instances
     */
    public function test_instance_allow_multiple() {
        $block = new \block_ai_proctor();
        $this->assertFalse($block->instance_allow_multiple());
    }

    /**
     * Test block applicable formats
     */
    public function test_applicable_formats() {
        $block = new \block_ai_proctor();
        $formats = $block->applicable_formats();
        
        $this->assertArrayHasKey('course-view', $formats);
        $this->assertTrue($formats['course-view']);
        $this->assertFalse($formats['site']);
        $this->assertFalse($formats['mod']);
        $this->assertFalse($formats['my']);
    }

    /**
     * Test block has configuration
     */
    public function test_has_config() {
        $block = new \block_ai_proctor();
        $this->assertTrue($block->has_config());
    }

    /**
     * Test content generation for site course
     */
    public function test_get_content_site_course() {
        global $COURSE;
        
        // Mock site course
        $originalcourse = $COURSE;
        $COURSE = (object) ['id' => 1]; // Site course ID
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        $this->assertNotNull($content);
        $this->assertEmpty($content->text);
        
        // Restore original course
        $COURSE = $originalcourse;
    }

    /**
     * Test content generation for teacher
     */
    public function test_get_content_for_teacher() {
        global $USER, $COURSE;
        
        // Create test course and user
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        
        $this->setUser($teacher);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        $this->assertNotNull($content);
        $this->assertStringContainsString('Command Center', $content->text);
        $this->assertStringContainsString('report.php', $content->text);
    }

    /**
     * Test content generation for student
     */
    public function test_get_content_for_student() {
        global $USER, $COURSE;
        
        // Create test course and user
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        $this->assertNotNull($content);
        $this->assertStringContainsString('ai-shield', $content->text);
        $this->assertStringContainsString('AI Proctor System', $content->text);
        $this->assertStringContainsString('MediaPipe', $content->text);
    }

    /**
     * Test content caching
     */
    public function test_content_caching() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        
        // First call
        $content1 = $block->get_content();
        
        // Second call should return cached content
        $content2 = $block->get_content();
        
        $this->assertSame($content1, $content2);
    }

    /**
     * Test JavaScript configuration generation
     */
    public function test_javascript_config_generation() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        $this->assertStringContainsString('course_id: ' . $course->id, $content->text);
        $this->assertStringContainsString('upload_url:', $content->text);
        $this->assertStringContainsString('sess_key:', $content->text);
    }

    /**
     * Test security measures in content
     */
    public function test_security_measures() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        // Check for security features
        $this->assertStringContainsString('sesskey()', $content->text);
        $this->assertStringContainsString('click-blocker', $content->text);
        $this->assertStringContainsString('warning-overlay', $content->text);
    }

    /**
     * Test AI monitoring configuration
     */
    public function test_ai_monitoring_config() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        // Check for AI configuration
        $this->assertStringContainsString('FaceLandmarker', $content->text);
        $this->assertStringContainsString('MediaPipe', $content->text);
        $this->assertStringContainsString('LIMITS', $content->text);
        $this->assertStringContainsString('HEAD_LEFT', $content->text);
        $this->assertStringContainsString('HEAD_RIGHT', $content->text);
    }

    /**
     * Test violation detection thresholds
     */
    public function test_violation_thresholds() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        // Check for reasonable thresholds
        $this->assertStringContainsString('0.40', $content->text); // HEAD_LEFT
        $this->assertStringContainsString('0.60', $content->text); // HEAD_RIGHT
        $this->assertStringContainsString('0.05', $content->text); // MOUTH_OPEN
    }

    /**
     * Test evidence capture configuration
     */
    public function test_evidence_capture_config() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        // Check for evidence capture features
        $this->assertStringContainsString('evidenceCanvas', $content->text);
        $this->assertStringContainsString('captureEvidence', $content->text);
        $this->assertStringContainsString('uploadVideoEvidence', $content->text);
    }

    /**
     * Test warning system configuration
     */
    public function test_warning_system_config() {
        global $COURSE;
        
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        
        $this->setUser($student);
        $COURSE = $course;
        
        $block = new \block_ai_proctor();
        $content = $block->get_content();
        
        // Check for warning system
        $this->assertStringContainsString('showIntelligentWarning', $content->text);
        $this->assertStringContainsString('warningGracePeriod', $content->text);
        $this->assertStringContainsString('8000', $content->text); // 8 second grace period
    }
}
