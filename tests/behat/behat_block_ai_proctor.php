<?php
/**
 * Behat context for AI Proctor block
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException as ExpectationException;

/**
 * Behat context class for AI Proctor block
 */
class behat_block_ai_proctor extends behat_base {

    /**
     * Create test evidence data for reports
     *
     * @Given /^the following ai proctor evidence exists:$/
     * @param TableNode $data
     */
    public function the_following_ai_proctor_evidence_exists($data) {
        global $DB;
        
        $required = ['userid', 'courseid', 'violation_type', 'timestamp'];
        
        foreach ($data->getHash() as $elementdata) {
            // Check required fields
            foreach ($required as $field) {
                if (!isset($elementdata[$field])) {
                    throw new ExpectationException("Field '$field' is required for ai proctor evidence", $this->getSession());
                }
            }
            
            // Convert user and course names to IDs
            $user = $DB->get_record('user', ['username' => $elementdata['userid']], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['shortname' => $elementdata['courseid']], '*', MUST_EXIST);
            
            $record = (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'violation_type' => $elementdata['violation_type'],
                'timestamp' => strtotime($elementdata['timestamp']),
                'status' => $elementdata['status'] ?? 'active',
                'ai_confidence' => $elementdata['ai_confidence'] ?? 85.0,
                'evidence_type' => $elementdata['evidence_type'] ?? 'image',
                'session_id' => $elementdata['session_id'] ?? 'test_session_' . random_string(8)
            ];
            
            $DB->insert_record('block_ai_proctor', $record);
        }
    }

    /**
     * Create test session data
     *
     * @Given /^the following ai proctor sessions exist:$/
     * @param TableNode $data
     */
    public function the_following_ai_proctor_sessions_exist($data) {
        global $DB;
        
        foreach ($data->getHash() as $elementdata) {
            $user = $DB->get_record('user', ['username' => $elementdata['userid']], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['shortname' => $elementdata['courseid']], '*', MUST_EXIST);
            
            $record = (object) [
                'userid' => $user->id,
                'courseid' => $course->id,
                'session_id' => $elementdata['session_id'],
                'starttime' => strtotime($elementdata['starttime']),
                'endtime' => isset($elementdata['endtime']) ? strtotime($elementdata['endtime']) : null,
                'duration' => $elementdata['duration'] ?? null,
                'violationcount' => $elementdata['violationcount'] ?? 0,
                'evidencecount' => $elementdata['evidencecount'] ?? 0,
                'status' => $elementdata['status'] ?? 'active'
            ];
            
            $DB->insert_record('block_ai_proctor_sessions', $record);
        }
    }

    /**
     * Check if monitoring interface is active
     *
     * @Then /^the ai proctor monitoring should be active$/
     */
    public function the_ai_proctor_monitoring_should_be_active() {
        $this->execute('behat_general::assert_element_contains_text', [
            'AI Proctor System',
            'css_element',
            '#ai-shield'
        ]);
        
        $this->execute('behat_general::assert_element_contains_text', [
            'Initializing secure monitoring',
            'css_element',
            '#shield-status'
        ]);
    }

    /**
     * Check if HUD elements are present
     *
     * @Then /^the ai proctor hud should be visible$/
     */
    public function the_ai_proctor_hud_should_be_visible() {
        $this->execute('behat_general::assert_element_contains_text', [
            'AI PROCTOR ACTIVE',
            'css_element',
            '#hud-header'
        ]);
        
        $this->execute('behat_general::should_exist', [
            '#heat-bar',
            'css_element'
        ]);
        
        $this->execute('behat_general::should_exist', [
            '#violation-log',
            'css_element'
        ]);
    }

    /**
     * Simulate a violation detection
     *
     * @When /^a "([^"]*)" violation is detected$/
     * @param string $violationType
     */
    public function a_violation_is_detected($violationType) {
        // This would typically require JavaScript execution to simulate AI detection
        // For testing purposes, we can inject the violation via JavaScript
        $script = "
            if (typeof logViolationToHUD === 'function') {
                logViolationToHUD('⚠️ TEST: {$violationType} - Simulated violation');
                updateHeaderStatus('{$violationType}', '#ef4444');
            }
        ";
        
        $this->getSession()->executeScript($script);
    }

    /**
     * Check violation count in header
     *
     * @Then /^the strike count should be "([^"]*)"$/
     * @param string $count
     */
    public function the_strike_count_should_be($count) {
        $this->execute('behat_general::assert_element_contains_text', [
            $count,
            'css_element',
            '#header-strikes'
        ]);
    }

    /**
     * Check if strict mode is activated
     *
     * @Then /^strict mode should be active$/
     */
    public function strict_mode_should_be_active() {
        $this->execute('behat_general::assert_element_contains_text', [
            'STRICT MODE',
            'css_element',
            '#header-status'
        ]);
    }

    /**
     * Check if evidence was captured
     *
     * @Then /^evidence should be captured for "([^"]*)"$/
     * @param string $violationType
     */
    public function evidence_should_be_captured_for($violationType) {
        $logText = "EVIDENCE CAPTURED: {$violationType}";
        
        // Check if the violation log contains the evidence capture message
        $this->execute('behat_general::assert_element_contains_text', [
            $logText,
            'css_element',
            '#violation-log'
        ]);
    }

    /**
     * Wait for AI initialization to complete
     *
     * @Given /^ai initialization is complete$/
     */
    public function ai_initialization_is_complete() {
        // Wait for the shield to hide and HUD to show
        $this->getSession()->wait(5000, "document.getElementById('ai-hud').style.display !== 'none'");
    }

    /**
     * Check warning overlay is displayed
     *
     * @Then /^a warning overlay should be displayed$/
     */
    public function a_warning_overlay_should_be_displayed() {
        $this->execute('behat_general::should_exist', [
            '#warning-overlay[style*="display: block"]',
            'css_element'
        ]);
        
        $this->execute('behat_general::assert_element_contains_text', [
            'Position Adjustment Required',
            'css_element',
            '#warning-overlay'
        ]);
    }

    /**
     * Check if click blocker is active
     *
     * @Then /^the click blocker should be active$/
     */
    public function the_click_blocker_should_be_active() {
        $this->execute('behat_general::should_exist', [
            '#click-blocker[style*="display: block"]',
            'css_element'
        ]);
        
        $this->execute('behat_general::assert_element_contains_text', [
            'EXAM HIDDEN',
            'css_element',
            '#click-blocker-msg'
        ]);
    }

    /**
     * Check reports contain specific data
     *
     * @Then /^the report should contain "([^"]*)" violations$/
     * @param string $count
     */
    public function the_report_should_contain_violations($count) {
        // Check for violation count in the statistics
        $this->execute('behat_general::assert_page_contains_text', [
            $count . ' violation'
        ]);
    }

    /**
     * Check export functionality is available
     *
     * @Then /^export options should be available$/
     */
    public function export_options_should_be_available() {
        $this->execute('behat_general::assert_page_contains_text', ['Export']);
        $this->execute('behat_general::assert_page_contains_text', ['CSV']);
    }

    /**
     * Verify privacy compliance elements
     *
     * @Then /^privacy compliance information should be visible$/
     */
    public function privacy_compliance_information_should_be_visible() {
        $this->execute('behat_general::assert_page_contains_text', ['MediaPipe']);
        $this->execute('behat_general::assert_page_contains_text', ['camera']);
        $this->execute('behat_general::assert_page_contains_text', ['monitoring']);
    }

    /**
     * Check real-time monitoring status
     *
     * @Then /^real-time monitoring should show "([^"]*)" active sessions$/
     * @param string $count
     */
    public function real_time_monitoring_should_show_active_sessions($count) {
        $this->execute('behat_general::assert_page_contains_text', [
            $count . ' active session'
        ]);
    }
}