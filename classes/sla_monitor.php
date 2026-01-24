<?php
/**
 * SLA Monitoring System
 * Uptime monitoring, health checks, and status page
 * 
 * @package block_ai_proctor
 * @copyright 2026 AI Proctor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

class sla_monitor {
    
    /**
     * Record system health check
     */
    public static function health_check() {
        global $DB;
        
        $health = new stdClass();
        $health->timestamp = time();
        $health->status = 'operational';
        $health->response_time = self::measure_response_time();
        $health->database_status = self::check_database();
        $health->websocket_status = self::check_websocket();
        $health->api_status = self::check_api();
        $health->storage_status = self::check_storage();
        $health->cpu_usage = self::get_cpu_usage();
        $health->memory_usage = self::get_memory_usage();
        
        // Determine overall status
        if ($health->database_status === false || $health->response_time > 2000) {
            $health->status = 'degraded';
        }
        
        if ($health->database_status === false && $health->websocket_status === false) {
            $health->status = 'outage';
        }
        
        $DB->insert_record('block_ai_proctor_health', $health);
        
        // Send alerts if needed
        if ($health->status !== 'operational') {
            self::send_alert($health);
        }
        
        return $health;
    }
    
    /**
     * Get uptime percentage for period
     */
    public static function get_uptime($days = 30) {
        global $DB;
        
        $since = time() - ($days * 86400);
        
        $total_checks = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {block_ai_proctor_health} WHERE timestamp > ?",
            [$since]
        );
        
        $operational_checks = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {block_ai_proctor_health} 
             WHERE timestamp > ? AND status = 'operational'",
            [$since]
        );
        
        return $total_checks > 0 ? ($operational_checks / $total_checks) * 100 : 0;
    }
    
    /**
     * Get current system status
     */
    public static function get_current_status() {
        global $DB;
        
        $latest = $DB->get_record_sql(
            "SELECT * FROM {block_ai_proctor_health} 
             ORDER BY timestamp DESC LIMIT 1"
        );
        
        return $latest ?? (object)[
            'status' => 'unknown',
            'response_time' => 0,
            'timestamp' => time()
        ];
    }
    
    /**
     * Get incidents
     */
    public static function get_incidents($days = 30) {
        global $DB;
        
        $since = time() - ($days * 86400);
        
        return $DB->get_records_sql(
            "SELECT * FROM {block_ai_proctor_incidents}
             WHERE start_time > ?
             ORDER BY start_time DESC",
            [$since]
        );
    }
    
    /**
     * Record incident
     */
    public static function record_incident($title, $description, $severity = 'medium') {
        global $DB;
        
        $incident = new stdClass();
        $incident->title = $title;
        $incident->description = $description;
        $incident->severity = $severity;
        $incident->status = 'investigating';
        $incident->start_time = time();
        $incident->end_time = null;
        
        $incident->id = $DB->insert_record('block_ai_proctor_incidents', $incident);
        
        // Send notifications
        self::notify_incident($incident);
        
        return $incident;
    }
    
    /**
     * Resolve incident
     */
    public static function resolve_incident($incidentid, $resolution) {
        global $DB;
        
        $incident = $DB->get_record('block_ai_proctor_incidents', ['id' => $incidentid]);
        
        if ($incident) {
            $incident->status = 'resolved';
            $incident->end_time = time();
            $incident->resolution = $resolution;
            $incident->duration = $incident->end_time - $incident->start_time;
            
            $DB->update_record('block_ai_proctor_incidents', $incident);
            
            self::notify_resolution($incident);
        }
    }
    
    /**
     * Check database connectivity
     */
    private static function check_database() {
        global $DB;
        
        try {
            $DB->get_record_sql("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check WebSocket server
     */
    private static function check_websocket() {
        $host = 'localhost';
        $port = 8080;
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        
        if ($socket) {
            fclose($socket);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check API availability
     */
    private static function check_api() {
        $url = 'http://localhost/blocks/ai_proctor/api/v1/health';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    /**
     * Check storage availability
     */
    private static function check_storage() {
        global $CFG;
        
        $dataroot = $CFG->dataroot;
        $free_space = disk_free_space($dataroot);
        $total_space = disk_total_space($dataroot);
        
        $usage_percent = (($total_space - $free_space) / $total_space) * 100;
        
        return $usage_percent < 90;
    }
    
    /**
     * Measure response time
     */
    private static function measure_response_time() {
        $start = microtime(true);
        
        // Perform simple query
        global $DB;
        $DB->get_record_sql("SELECT 1");
        
        $end = microtime(true);
        
        return round(($end - $start) * 1000); // milliseconds
    }
    
    /**
     * Get CPU usage
     */
    private static function get_cpu_usage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0];
        }
        return 0;
    }
    
    /**
     * Get memory usage
     */
    private static function get_memory_usage() {
        return round(memory_get_usage(true) / 1024 / 1024, 2); // MB
    }
    
    /**
     * Send alert
     */
    private static function send_alert($health) {
        // Send email alert
        $admins = get_admins();
        
        foreach ($admins as $admin) {
            $subject = "[AI Proctor] System Status: {$health->status}";
            $message = "System health check detected issues:\n\n";
            $message .= "Status: {$health->status}\n";
            $message .= "Response Time: {$health->response_time}ms\n";
            $message .= "Database: " . ($health->database_status ? 'OK' : 'FAILED') . "\n";
            $message .= "WebSocket: " . ($health->websocket_status ? 'OK' : 'FAILED') . "\n";
            
            email_to_user($admin, get_admin(), $subject, $message);
        }
    }
    
    /**
     * Notify incident
     */
    private static function notify_incident($incident) {
        // Notify all institution admins
        // Implementation depends on notification system
    }
    
    /**
     * Notify resolution
     */
    private static function notify_resolution($incident) {
        // Notify resolution
        // Implementation depends on notification system
    }
}
