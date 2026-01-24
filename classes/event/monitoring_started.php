<?php
/**
 * AI Proctor Monitoring Started Event
 * 
 * Event triggered when AI monitoring begins for a user.
 * Tracks the start of proctoring sessions with system configuration.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class monitoring_started extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_ai_proctor_sessions';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_monitoring_started', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        $camera = $this->other['camera_info'] ?? 'Unknown camera';
        $browser = $this->other['browser_info'] ?? 'Unknown browser';
        
        return "The user with id '$this->userid' started AI monitoring in course '$this->courseid' using $camera on $browser. Session ID: $this->objectid";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
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
     * @param array $systeminfo System and camera information
     * @return monitoring_started
     */
    public static function create_from_session($courseid, $sessionid, $context, $systeminfo = []) {
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $sessionid,
            'other' => [
                'camera_info' => $systeminfo['camera'] ?? 'Unknown camera',
                'browser_info' => $systeminfo['browser'] ?? 'Unknown browser',
                'screen_resolution' => $systeminfo['resolution'] ?? 'Unknown',
                'ai_model_version' => 'MediaPipe FaceLandmarker v0.10.3',
                'session_start_time' => time(),
                'strict_mode' => $systeminfo['strict_mode'] ?? false
            ]
        ]);
        
        return $event;
    }
}
