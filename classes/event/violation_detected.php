<?php
/**
 * AI Proctor Violation Detected Event
 * 
 * Event triggered when the AI system detects a potential violation.
 * Captures violation type, confidence level, and response taken.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class violation_detected extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_ai_proctor';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_violation_detected', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        $type = $this->other['violation_type'] ?? 'Unknown';
        $confidence = $this->other['confidence'] ?? 0;
        $action = $this->other['action_taken'] ?? 'Logged';
        $strike = $this->other['strike_number'] ?? '?';
        
        return "AI detected violation '$type' for user '$this->userid' in course '$this->courseid'. " .
               "Confidence: {$confidence}%, Action: $action, Strike: $strike";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/blocks/ai_proctor/report.php', [
            'courseid' => $this->courseid,
            'userid' => $this->userid
        ]);
    }
    
    /**
     * Custom validation
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        
        if (!isset($this->courseid)) {
            throw new \coding_exception('courseid must be set');
        }
        
        if (!isset($this->other['violation_type'])) {
            throw new \coding_exception('violation_type must be set in other data');
        }
    }
    
    /**
     * Create event instance
     * @param int $courseid Course ID
     * @param \context $context Context
     * @param string $violationType Type of violation
     * @param array $details Violation details
     * @return violation_detected
     */
    public static function create_from_violation($courseid, $context, $violationType, $details = []) {
        // Determine confidence based on violation type and measurements
        $confidence = self::calculate_confidence($violationType, $details);
        
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'other' => [
                'violation_type' => $violationType,
                'confidence' => $confidence,
                'action_taken' => $details['action'] ?? 'Warning shown',
                'strike_number' => $details['strike'] ?? 1,
                'suspicion_level' => $details['suspicion'] ?? 0,
                'ai_measurements' => [
                    'nose_x' => $details['nose_x'] ?? null,
                    'nose_y' => $details['nose_y'] ?? null,
                    'eye_avg' => $details['eye_avg'] ?? null,
                    'mouth_score' => $details['mouth_score'] ?? null
                ],
                'system_state' => [
                    'strict_mode' => $details['strict_mode'] ?? false,
                    'grace_period_active' => $details['grace_period'] ?? false,
                    'timestamp' => time()
                ]
            ]
        ]);
        
        return $event;
    }
    
    /**
     * Calculate confidence level based on AI measurements
     * @param string $violationType
     * @param array $details
     * @return int Confidence percentage
     */
    private static function calculate_confidence($violationType, $details) {
        switch ($violationType) {
            case 'No Face':
                return 100; // Always certain when no face detected
                
            case 'Turning Left':
            case 'Turning Right':
                $nose_x = $details['nose_x'] ?? 0.5;
                $deviation = abs($nose_x - 0.5) * 2; // Convert to 0-1 scale
                return min(100, intval($deviation * 200)); // Scale to percentage
                
            case 'Looking Down':
                $eye_avg = $details['eye_avg'] ?? 0;
                $nose_y = $details['nose_y'] ?? 0.5;
                $eye_confidence = min(100, $eye_avg * 250);
                $head_confidence = max(0, ($nose_y - 0.5) * 200);
                return intval(($eye_confidence + $head_confidence) / 2);
                
            case 'Talking':
                $mouth_score = $details['mouth_score'] ?? 0;
                return min(100, intval($mouth_score * 2000)); // Scale mouth opening
                
            default:
                return 75; // Default confidence for unknown violations
        }
    }
}
