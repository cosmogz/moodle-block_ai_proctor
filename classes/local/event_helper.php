<?php
/**
 * AI Proctor Event Helper Utility
 * 
 * Provides convenient methods for triggering events throughout the AI Proctor plugin.
 * Centralizes event creation to ensure consistent logging and compliance tracking.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\local;

defined('MOODLE_INTERNAL') || die();

use block_ai_proctor\event\block_viewed;
use block_ai_proctor\event\monitoring_started;
use block_ai_proctor\event\monitoring_ended;
use block_ai_proctor\event\violation_detected;
use block_ai_proctor\event\evidence_captured;
use block_ai_proctor\event\configuration_updated;

/**
 * Event helper utility for AI Proctor plugin
 */
class event_helper {
    
    /**
     * Log block viewed event
     * @param int $courseid Course ID
     * @param int $blockid Block instance ID
     */
    public static function log_block_viewed($courseid, $blockid) {
        $context = \context_course::instance($courseid);
        
        $event = block_viewed::create_from_block($courseid, $blockid, $context);
        $event->trigger();
    }
    
    /**
     * Log monitoring session started
     * @param int $courseid Course ID
     * @param int $sessionid Session ID
     * @param array $systeminfo System information
     */
    public static function log_monitoring_started($courseid, $sessionid, $systeminfo = []) {
        $context = \context_course::instance($courseid);
        
        $event = monitoring_started::create_from_session($courseid, $sessionid, $context, $systeminfo);
        $event->trigger();
    }
    
    /**
     * Log monitoring session ended
     * @param int $courseid Course ID
     * @param int $sessionid Session ID
     * @param array $sessiondata Session summary data
     */
    public static function log_monitoring_ended($courseid, $sessionid, $sessiondata = []) {
        $context = \context_course::instance($courseid);
        
        $event = monitoring_ended::create_from_session($courseid, $sessionid, $context, $sessiondata);
        $event->trigger();
    }
    
    /**
     * Log violation detected
     * @param int $courseid Course ID
     * @param string $violationType Type of violation
     * @param array $details Violation details
     */
    public static function log_violation($courseid, $violationType, $details = []) {
        $context = \context_course::instance($courseid);
        
        $event = violation_detected::create_from_violation($courseid, $context, $violationType, $details);
        $event->trigger();
    }
    
    /**
     * Log evidence captured
     * @param int $courseid Course ID
     * @param int $evidenceid Evidence record ID
     * @param array $evidencedata Evidence details
     */
    public static function log_evidence_captured($courseid, $evidenceid, $evidencedata = []) {
        $context = \context_course::instance($courseid);
        
        $event = evidence_captured::create_from_evidence($courseid, $context, $evidenceid, $evidencedata);
        $event->trigger();
    }
    
    /**
     * Log batch evidence captured
     * @param int $courseid Course ID
     * @param array $evidenceList Array of evidence items
     */
    public static function log_batch_evidence($courseid, $evidenceList) {
        $context = \context_course::instance($courseid);
        
        $event = evidence_captured::create_from_batch($courseid, $context, $evidenceList);
        $event->trigger();
    }
    
    /**
     * Log configuration updated
     * @param int $courseid Course ID
     * @param int $blockinstanceid Block instance ID
     * @param array $oldconfig Previous configuration
     * @param array $newconfig New configuration
     */
    public static function log_configuration_updated($courseid, $blockinstanceid, $oldconfig = [], $newconfig = []) {
        $context = \context_course::instance($courseid);
        
        $event = configuration_updated::create_from_config($courseid, $blockinstanceid, $context, $oldconfig, $newconfig);
        $event->trigger();
    }
    
    /**
     * Get system information for event logging
     * @return array System information
     */
    public static function get_system_info() {
        global $USER;
        
        return [
            'browser' => self::get_browser_info(),
            'camera' => self::get_camera_info(),
            'resolution' => self::get_screen_resolution(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => getremoteaddr(),
            'user_id' => $USER->id,
            'session_id' => session_id(),
            'timestamp' => time()
        ];
    }
    
    /**
     * Get browser information
     * @return string Browser information
     */
    private static function get_browser_info() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } else {
            return 'Unknown browser';
        }
    }
    
    /**
     * Get camera information (placeholder)
     * @return string Camera information
     */
    private static function get_camera_info() {
        // This would typically be populated from JavaScript
        return 'Camera detected (details from JS)';
    }
    
    /**
     * Get screen resolution (placeholder)
     * @return string Screen resolution
     */
    private static function get_screen_resolution() {
        // This would typically be populated from JavaScript
        return 'Resolution detected (details from JS)';
    }
    
    /**
     * Create session tracking record
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return int Session ID
     */
    public static function create_session_record($courseid, $userid) {
        global $DB;
        
        $session = new \stdClass();
        $session->courseid = $courseid;
        $session->userid = $userid;
        $session->starttime = time();
        $session->endtime = 0;
        $session->violationcount = 0;
        $session->evidencecount = 0;
        $session->systeminfo = json_encode(self::get_system_info());
        $session->status = 'active';
        
        $sessionid = $DB->insert_record('block_ai_proctor_sessions', $session);
        
        // Log the start event
        self::log_monitoring_started($courseid, $sessionid, self::get_system_info());
        
        return $sessionid;
    }
    
    /**
     * End session tracking record
     * @param int $sessionid Session ID
     * @param array $sessiondata Final session data
     */
    public static function end_session_record($sessionid, $sessiondata = []) {
        global $DB;
        
        $session = $DB->get_record('block_ai_proctor_sessions', ['id' => $sessionid]);
        if (!$session) {
            return;
        }
        
        $session->endtime = time();
        $session->duration = $session->endtime - $session->starttime;
        $session->violationcount = $sessiondata['violations'] ?? $session->violationcount;
        $session->evidencecount = $sessiondata['evidence_count'] ?? $session->evidencecount;
        $session->status = 'completed';
        $session->finaldata = json_encode($sessiondata);
        
        $DB->update_record('block_ai_proctor_sessions', $session);
        
        // Log the end event
        $endData = array_merge($sessiondata, [
            'duration' => $session->duration,
            'violations' => $session->violationcount,
            'evidence_count' => $session->evidencecount
        ]);
        
        self::log_monitoring_ended($session->courseid, $sessionid, $endData);
    }
    
    /**
     * Increment session violation count
     * @param int $sessionid Session ID
     */
    public static function increment_session_violations($sessionid) {
        global $DB;
        
        $DB->execute("UPDATE {block_ai_proctor_sessions} SET violationcount = violationcount + 1 WHERE id = ?", [$sessionid]);
    }
    
    /**
     * Increment session evidence count
     * @param int $sessionid Session ID
     */
    public static function increment_session_evidence($sessionid) {
        global $DB;
        
        $DB->execute("UPDATE {block_ai_proctor_sessions} SET evidencecount = evidencecount + 1 WHERE id = ?", [$sessionid]);
    }
    
    /**
     * Get session statistics
     * @param int $courseid Course ID
     * @param int $days Number of days to look back
     * @return array Session statistics
     */
    public static function get_session_stats($courseid, $days = 30) {
        global $DB;
        
        $since = time() - ($days * 24 * 60 * 60);
        
        $sql = "SELECT 
                    COUNT(*) as total_sessions,
                    AVG(duration) as avg_duration,
                    SUM(violationcount) as total_violations,
                    SUM(evidencecount) as total_evidence,
                    COUNT(DISTINCT userid) as unique_users
                FROM {block_ai_proctor_sessions}
                WHERE courseid = ? AND starttime >= ?";
        
        $stats = $DB->get_record_sql($sql, [$courseid, $since]);
        
        return [
            'total_sessions' => intval($stats->total_sessions),
            'avg_duration' => round($stats->avg_duration ?? 0),
            'total_violations' => intval($stats->total_violations),
            'total_evidence' => intval($stats->total_evidence),
            'unique_users' => intval($stats->unique_users),
            'period_days' => $days
        ];
    }
}
