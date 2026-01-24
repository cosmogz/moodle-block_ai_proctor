<?php
/**
 * AI Proctor Reports Generation Scheduled Task
 * 
 * Weekly task to generate usage statistics, performance reports,
 * and system health checks for the AI Proctor system.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/task/classes/scheduled_task.php');

/**
 * Scheduled task for generating AI Proctor reports
 */
class generate_reports extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task
     * 
     * @return string Task name
     */
    public function get_name() {
        return get_string('generate_reports', 'block_ai_proctor');
    }

    /**
     * Execute the report generation task
     */
    public function execute() {
        global $DB;
        
        $starttime = time();
        $this->log_info('Starting AI Proctor report generation');
        
        try {
            // Generate weekly usage statistics
            $usage_stats = $this->generate_usage_statistics();
            
            // Generate system health report
            $health_report = $this->generate_health_report();
            
            // Generate course compliance report
            $compliance_report = $this->generate_compliance_report();
            
            // Save consolidated report
            $this->save_weekly_report($usage_stats, $health_report, $compliance_report);
            
            $duration = time() - $starttime;
            $this->log_info("Report generation completed in {$duration} seconds");
            
        } catch (Exception $e) {
            $this->log_error('Report generation failed: ' . $e->getMessage());
        }
        
        return true;
    }
    
    /**
     * Generate usage statistics for the past week
     * 
     * @return array Usage statistics
     */
    private function generate_usage_statistics() {
        global $DB;
        
        $week_ago = time() - (7 * 24 * 60 * 60);
        
        $stats = array();
        
        // Total evidence captured this week
        $stats['total_evidence'] = $DB->count_records_select('block_ai_proctor', 
            'timecreated >= ?', array($week_ago));
        
        // Evidence by type
        $stats['evidence_by_type'] = $DB->get_records_sql(
            "SELECT evidence_type, COUNT(*) as count 
             FROM {block_ai_proctor} 
             WHERE timecreated >= ? 
             GROUP BY evidence_type",
            array($week_ago)
        );
        
        // Violations by severity
        $stats['violations_by_severity'] = $DB->get_records_sql(
            "SELECT severity, COUNT(*) as count 
             FROM {block_ai_proctor} 
             WHERE timecreated >= ? 
             GROUP BY severity",
            array($week_ago)
        );
        
        // Active courses with monitoring
        $stats['active_courses'] = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT courseid) 
             FROM {block_ai_proctor} 
             WHERE timecreated >= ?",
            array($week_ago)
        );
        
        // Active sessions this week
        $stats['total_sessions'] = $DB->count_records_select('block_ai_proctor_sessions',
            'timestarted >= ?', array($week_ago));
        
        $this->log_info("Generated usage statistics: {$stats['total_evidence']} evidence items, {$stats['active_courses']} courses");
        
        return $stats;
    }
    
    /**
     * Generate system health report
     * 
     * @return array Health report data
     */
    private function generate_health_report() {
        global $DB, $CFG;
        
        $health = array();
        
        // Database health
        $health['db_evidence_count'] = $DB->count_records('block_ai_proctor');
        $health['db_session_count'] = $DB->count_records('block_ai_proctor_sessions');
        $health['db_config_count'] = $DB->count_records('block_ai_proctor_config');
        
        // File system health
        $evidence_folder = $CFG->dataroot . '/ai_proctor_evidence/';
        if (is_dir($evidence_folder)) {
            $files = glob($evidence_folder . '*');
            $health['file_count'] = count($files);
            
            $total_size = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    $total_size += filesize($file);
                }
            }
            $health['total_file_size'] = $total_size;
            $health['total_file_size_human'] = $this->format_bytes($total_size);
        } else {
            $health['file_count'] = 0;
            $health['total_file_size'] = 0;
            $health['total_file_size_human'] = '0 bytes';
        }
        
        // Orphaned records check
        $health['orphaned_files'] = $this->count_orphaned_files();
        $health['orphaned_configs'] = $this->count_orphaned_configs();
        
        // Performance metrics
        $health['avg_evidence_per_day'] = $this->calculate_daily_average();
        
        $this->log_info("Generated health report: {$health['db_evidence_count']} DB records, {$health['file_count']} files");
        
        return $health;
    }
    
    /**
     * Generate course compliance report
     * 
     * @return array Compliance report data
     */
    private function generate_compliance_report() {
        global $DB;
        
        $compliance = array();
        
        // Courses with AI Proctor enabled
        $enabled_courses = $DB->get_records_sql(
            "SELECT DISTINCT courseid, COUNT(*) as evidence_count
             FROM {block_ai_proctor} 
             GROUP BY courseid"
        );
        
        $compliance['total_monitored_courses'] = count($enabled_courses);
        
        // Courses with recent activity (last 30 days)
        $month_ago = time() - (30 * 24 * 60 * 60);
        $active_courses = $DB->get_records_sql(
            "SELECT DISTINCT courseid, MAX(timecreated) as last_activity
             FROM {block_ai_proctor} 
             WHERE timecreated >= ?
             GROUP BY courseid",
            array($month_ago)
        );
        
        $compliance['active_courses'] = count($active_courses);
        
        // Data retention compliance
        $retention_days = get_config('block_ai_proctor', 'evidence_retention_days') ?: 90;
        $retention_cutoff = time() - ($retention_days * 24 * 60 * 60);
        
        $old_records = $DB->count_records_select('block_ai_proctor',
            'timecreated < ?', array($retention_cutoff));
        
        $compliance['records_past_retention'] = $old_records;
        $compliance['retention_compliance'] = ($old_records == 0);
        
        // Review status compliance
        $unreviewed_high = $DB->count_records_select('block_ai_proctor',
            'severity = ? AND status = ?', array('high', 'active'));
        
        $compliance['unreviewed_high_severity'] = $unreviewed_high;
        
        $this->log_info("Generated compliance report: {$compliance['total_monitored_courses']} courses monitored");
        
        return $compliance;
    }
    
    /**
     * Save the consolidated weekly report
     * 
     * @param array $usage_stats Usage statistics
     * @param array $health_report Health report
     * @param array $compliance_report Compliance report
     */
    private function save_weekly_report($usage_stats, $health_report, $compliance_report) {
        global $DB;
        
        $report = array(
            'report_type' => 'weekly',
            'generated_at' => time(),
            'period_start' => time() - (7 * 24 * 60 * 60),
            'period_end' => time(),
            'usage_statistics' => $usage_stats,
            'health_report' => $health_report,
            'compliance_report' => $compliance_report
        );
        
        $config = new stdClass();
        $config->courseid = 0;
        $config->setting_name = 'weekly_report_' . date('Y-W');
        $config->setting_value = json_encode($report);
        $config->timecreated = time();
        $config->timemodified = time();
        
        try {
            $DB->insert_record('block_ai_proctor_config', $config);
            $this->log_info('Weekly report saved successfully');
            
            // Clean up old reports (keep last 12 weeks)
            $old_reports = $DB->get_records_select('block_ai_proctor_config',
                'courseid = 0 AND setting_name LIKE ? ORDER BY timecreated DESC',
                array('weekly_report_%'));
                
            if (count($old_reports) > 12) {
                $to_delete = array_slice($old_reports, 12);
                foreach ($to_delete as $old_report) {
                    $DB->delete_records('block_ai_proctor_config', array('id' => $old_report->id));
                }
                $this->log_info('Cleaned up ' . count($to_delete) . ' old weekly reports');
            }
            
        } catch (Exception $e) {
            $this->log_error('Failed to save weekly report: ' . $e->getMessage());
        }
    }
    
    /**
     * Count orphaned files (files without database records)
     * 
     * @return int Number of orphaned files
     */
    private function count_orphaned_files() {
        global $DB, $CFG;
        
        $evidence_folder = $CFG->dataroot . '/ai_proctor_evidence/';
        if (!is_dir($evidence_folder)) {
            return 0;
        }
        
        $files = scandir($evidence_folder);
        $orphaned = 0;
        
        foreach ($files as $filename) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            
            $exists = $DB->record_exists('block_ai_proctor', array('evidence_path' => $filename));
            if (!$exists) {
                $orphaned++;
            }
        }
        
        return $orphaned;
    }
    
    /**
     * Count orphaned configuration records
     * 
     * @return int Number of orphaned configs
     */
    private function count_orphaned_configs() {
        global $DB;
        
        $count = $DB->count_records_sql(
            "SELECT COUNT(c.id) FROM {block_ai_proctor_config} c 
             LEFT JOIN {course} co ON c.courseid = co.id 
             WHERE co.id IS NULL AND c.courseid > 0"
        );
        
        return $count;
    }
    
    /**
     * Calculate daily average evidence count
     * 
     * @return float Average evidence per day
     */
    private function calculate_daily_average() {
        global $DB;
        
        $month_ago = time() - (30 * 24 * 60 * 60);
        $count = $DB->count_records_select('block_ai_proctor', 
            'timecreated >= ?', array($month_ago));
        
        return round($count / 30, 2);
    }
    
    /**
     * Format bytes into human readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted size string
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Log informational message
     * 
     * @param string $message Message to log
     */
    private function log_info($message) {
        mtrace('[AI Proctor Reports] ' . $message);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message to log
     */
    private function log_error($message) {
        mtrace('[AI Proctor Reports ERROR] ' . $message);
        debugging($message, DEBUG_NORMAL);
    }
}
?>