<?php
/**
 * AI Proctor Monitoring Ended Event
 * 
 * Event triggered when AI monitoring session ends.
 * Captures session duration, violation summary, and termination reason.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class monitoring_ended extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_ai_proctor_sessions';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_monitoring_ended', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        $duration = $this->other['duration'] ?? 0;
        $violations = $this->other['total_violations'] ?? 0;
        $reason = $this->other['end_reason'] ?? 'Unknown';
        
        $duration_str = gmdate('H:i:s', $duration);
        
        return "The user with id '$this->userid' ended AI monitoring session in course '$this->courseid'. " .
               "Duration: $duration_str, Violations: $violations, Reason: $reason";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/blocks/ai_proctor/report_detail.php', [
            'courseid' => $this->courseid,
            'sessionid' => $this->objectid
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
        
        if (!isset($this->objectid)) {
            throw new \coding_exception('objectid (session_id) must be set');
        }
    }
    
    /**
     * Create event instance
     * @param int $courseid Course ID
     * @param int $sessionid Session ID
     * @param \context $context Context
     * @param array $sessiondata Session summary data
     * @return monitoring_ended
     */
    public static function create_from_session($courseid, $sessionid, $context, $sessiondata = []) {
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $sessionid,
            'other' => [
                'duration' => $sessiondata['duration'] ?? 0,
                'total_violations' => $sessiondata['violations'] ?? 0,
                'evidence_captured' => $sessiondata['evidence_count'] ?? 0,
                'end_reason' => $sessiondata['end_reason'] ?? 'User navigation',
                'final_suspicion_level' => $sessiondata['suspicion_level'] ?? 0,
                'session_end_time' => time(),
                'strict_mode_triggered' => $sessiondata['strict_mode'] ?? false
            ]
        ]);
        
        return $event;
    }
}
