<?php
/**
 * AI Proctor Evidence Cleanup Scheduled Task
 * 
 * Automated task to clean up old evidence files, expired sessions,
 * and maintain database integrity. Runs daily to ensure optimal
 * performance and comply with data retention policies.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/task/classes/scheduled_task.php');

/**
 * Scheduled task for cleaning up old AI Proctor evidence
 */
class cleanup_old_evidence extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown in admin screens).
     * 
     * @return string Task name
     */
    public function get_name() {
        return get_string('cleanup_old_evidence', 'block_ai_proctor');
    }

    /**
     * Execute the cleanup task
     */
    public function execute() {
        global $DB, $CFG;
        
        $starttime = time();
        $this->log_info('Starting AI Proctor evidence cleanup task');
        
        // Get configuration settings
        $retention_days = get_config('block_ai_proctor', 'evidence_retention_days') ?: 90;
        $session_cleanup_days = get_config('block_ai_proctor', 'session_cleanup_days') ?: 30;
        $max_file_age_days = get_config('block_ai_proctor', 'max_file_age_days') ?: $retention_days;
        
        $cutoff_time = $starttime - ($retention_days * 24 * 60 * 60);
        $session_cutoff = $starttime - ($session_cleanup_days * 24 * 60 * 60);
        $file_cutoff = $starttime - ($max_file_age_days * 24 * 60 * 60);
        
        $stats = array(
            'evidence_deleted' => 0,
            'files_deleted' => 0,
            'sessions_cleaned' => 0,
            'configs_cleaned' => 0,
            'space_freed' => 0,
            'errors' => array()
        );
        
        try {
            // 1. Clean up old evidence records and files
            $this->cleanup_evidence_files($cutoff_time, $file_cutoff, $stats);
            
            // 2. Clean up old session records
            $this->cleanup_sessions($session_cutoff, $stats);
            
            // 3. Clean up orphaned configuration records
            $this->cleanup_orphaned_configs($stats);
            
            // 4. Optimize database tables
            $this->optimize_database_tables($stats);
            
            // 5. Generate cleanup report
            $this->generate_cleanup_report($starttime, $stats);
            
        } catch (Exception $e) {
            $stats['errors'][] = 'Critical error: ' . $e->getMessage();
            $this->log_error('Cleanup task failed: ' . $e->getMessage());
        }
        
        $duration = time() - $starttime;
        $this->log_info(sprintf('AI Proctor cleanup completed in %d seconds. Deleted: %d evidence records, %d files, %d sessions. Space freed: %s',
            $duration, $stats['evidence_deleted'], $stats['files_deleted'], $stats['sessions_cleaned'], 
            $this->format_bytes($stats['space_freed'])));
        
        // Log any errors
        if (!empty($stats['errors'])) {
            foreach ($stats['errors'] as $error) {
                $this->log_error($error);
            }
        }
        
        return true;
    }
    
    /**
     * Clean up old evidence files and database records
     * 
     * @param int $cutoff_time Database record cutoff timestamp
     * @param int $file_cutoff File deletion cutoff timestamp
     * @param array &$stats Statistics array to update
     */
    private function cleanup_evidence_files($cutoff_time, $file_cutoff, &$stats) {
        global $DB, $CFG;
        
        $this->log_info('Starting evidence file cleanup');
        
        // Get evidence folder path
        $evidence_folder = $CFG->dataroot . '/ai_proctor_evidence/';
        
        // Find old evidence records
        $old_evidence = $DB->get_records_select('block_ai_proctor', 
            'timecreated < ?', array($cutoff_time), 'timecreated ASC');
        
        foreach ($old_evidence as $evidence) {
            try {
                // Delete associated file if it exists
                if (!empty($evidence->evidence_path)) {
                    $filepath = $evidence_folder . $evidence->evidence_path;
                    if (file_exists($filepath)) {
                        $filesize = filesize($filepath);
                        if (unlink($filepath)) {
                            $stats['files_deleted']++;
                            $stats['space_freed'] += $filesize;
                        } else {
                            $stats['errors'][] = "Failed to delete file: {$evidence->evidence_path}";
                        }
                    }
                }
                
                // Delete legacy imagedata if it exists (base64 data)
                if (!empty($evidence->imagedata) && strlen($evidence->imagedata) > 1000) {
                    // Estimate space freed from base64 data
                    $stats['space_freed'] += strlen($evidence->imagedata);
                }
                
                // Delete database record
                if ($DB->delete_records('block_ai_proctor', array('id' => $evidence->id))) {
                    $stats['evidence_deleted']++;
                } else {
                    $stats['errors'][] = "Failed to delete evidence record ID: {$evidence->id}";
                }
                
            } catch (Exception $e) {
                $stats['errors'][] = "Error processing evidence ID {$evidence->id}: {$e->getMessage()}";
            }
        }
        
        // Clean up orphaned files (files without database records)
        $this->cleanup_orphaned_files($file_cutoff, $evidence_folder, $stats);
        
        $this->log_info("Evidence cleanup completed: {$stats['evidence_deleted']} records, {$stats['files_deleted']} files");
    }
    
    /**
     * Clean up orphaned evidence files
     * 
     * @param int $file_cutoff File age cutoff timestamp
     * @param string $evidence_folder Evidence folder path
     * @param array &$stats Statistics array to update
     */
    private function cleanup_orphaned_files($file_cutoff, $evidence_folder, &$stats) {
        global $DB;
        
        if (!is_dir($evidence_folder)) {
            return;
        }
        
        $this->log_info('Checking for orphaned evidence files');
        
        $files = scandir($evidence_folder);
        $orphaned_count = 0;
        
        foreach ($files as $filename) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            
            $filepath = $evidence_folder . $filename;
            
            // Check if file is old enough
            if (filemtime($filepath) > $file_cutoff) {
                continue;
            }
            
            // Check if file has corresponding database record
            $exists = $DB->record_exists('block_ai_proctor', array('evidence_path' => $filename));
            
            if (!$exists) {
                // Orphaned file - delete it
                $filesize = filesize($filepath);
                if (unlink($filepath)) {
                    $stats['files_deleted']++;
                    $stats['space_freed'] += $filesize;
                    $orphaned_count++;
                } else {
                    $stats['errors'][] = "Failed to delete orphaned file: {$filename}";
                }
            }
        }
        
        if ($orphaned_count > 0) {
            $this->log_info("Cleaned up {$orphaned_count} orphaned evidence files");
        }
    }
    
    /**
     * Clean up old session records
     * 
     * @param int $cutoff_time Session age cutoff timestamp
     * @param array &$stats Statistics array to update
     */
    private function cleanup_sessions($cutoff_time, &$stats) {
        global $DB;
        
        $this->log_info('Starting session cleanup');
        
        // Delete old ended sessions
        $deleted = $DB->delete_records_select('block_ai_proctor_sessions', 
            '(timeended IS NOT NULL AND timeended < ?) OR (last_activity < ? AND status != ?)', 
            array($cutoff_time, $cutoff_time, 'active'));
        
        $stats['sessions_cleaned'] = $deleted;
        
        // Mark stale active sessions as ended
        $stale_threshold = time() - (24 * 60 * 60); // 24 hours
        $stale_sessions = $DB->get_records_select('block_ai_proctor_sessions',
            'status = ? AND last_activity < ?', array('active', $stale_threshold));
        
        $stale_count = 0;
        foreach ($stale_sessions as $session) {
            $session->status = 'ended';
            $session->timeended = time();
            if ($DB->update_record('block_ai_proctor_sessions', $session)) {
                $stale_count++;
            }
        }
        
        if ($stale_count > 0) {
            $this->log_info("Marked {$stale_count} stale sessions as ended");
        }
        
        $this->log_info("Session cleanup completed: {$deleted} old sessions deleted");
    }
    
    /**
     * Clean up orphaned configuration records
     * 
     * @param array &$stats Statistics array to update
     */
    private function cleanup_orphaned_configs($stats) {
        global $DB;
        
        $this->log_info('Cleaning up orphaned configuration records');
        
        // Find config records for courses that no longer exist
        $orphaned_configs = $DB->get_records_sql(
            "SELECT c.id, c.courseid FROM {block_ai_proctor_config} c 
             LEFT JOIN {course} co ON c.courseid = co.id 
             WHERE co.id IS NULL");
        
        $deleted_configs = 0;
        foreach ($orphaned_configs as $config) {
            if ($DB->delete_records('block_ai_proctor_config', array('id' => $config->id))) {
                $deleted_configs++;
            }
        }
        
        $stats['configs_cleaned'] = $deleted_configs;
        
        if ($deleted_configs > 0) {
            $this->log_info("Cleaned up {$deleted_configs} orphaned configuration records");
        }
    }
    
    /**
     * Optimize database tables for better performance
     * 
     * @param array &$stats Statistics array to update
     */
    private function optimize_database_tables(&$stats) {
        global $DB, $CFG;
        
        $this->log_info('Optimizing database tables');
        
        try {
            // Update statistics for AI Proctor tables (MySQL/MariaDB specific)
            if ($DB->get_dbfamily() == 'mysql') {
                $tables = array(
                    'block_ai_proctor',
                    'block_ai_proctor_config', 
                    'block_ai_proctor_sessions'
                );
                
                foreach ($tables as $table) {
                    $DB->execute("ANALYZE TABLE {{$table}}");
                }
                
                $this->log_info('Database table statistics updated');
            }
            
        } catch (Exception $e) {
            $stats['errors'][] = "Database optimization error: {$e->getMessage()}";
        }
    }
    
    /**
     * Generate and store cleanup report
     * 
     * @param int $starttime Task start timestamp
     * @param array $stats Cleanup statistics
     */
    private function generate_cleanup_report($starttime, $stats) {
        global $DB;
        
        $report = array(
            'timestamp' => time(),
            'duration' => time() - $starttime,
            'evidence_deleted' => $stats['evidence_deleted'],
            'files_deleted' => $stats['files_deleted'],
            'sessions_cleaned' => $stats['sessions_cleaned'],
            'configs_cleaned' => $stats['configs_cleaned'],
            'space_freed_bytes' => $stats['space_freed'],
            'space_freed_human' => $this->format_bytes($stats['space_freed']),
            'error_count' => count($stats['errors']),
            'errors' => $stats['errors']
        );
        
        // Store report as configuration record
        $config = new stdClass();
        $config->courseid = 0; // Global configuration
        $config->setting_name = 'cleanup_report_' . date('Y-m-d', $starttime);
        $config->setting_value = json_encode($report);
        $config->timecreated = time();
        $config->timemodified = time();
        
        try {
            $DB->insert_record('block_ai_proctor_config', $config);
            
            // Keep only last 30 cleanup reports
            $old_reports = $DB->get_records_select('block_ai_proctor_config',
                'courseid = 0 AND setting_name LIKE ? ORDER BY timecreated DESC',
                array('cleanup_report_%'));
                
            if (count($old_reports) > 30) {
                $to_delete = array_slice($old_reports, 30);
                foreach ($to_delete as $old_report) {
                    $DB->delete_records('block_ai_proctor_config', array('id' => $old_report->id));
                }
            }
            
        } catch (Exception $e) {
            $this->log_error('Failed to save cleanup report: ' . $e->getMessage());
        }
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
        mtrace('[AI Proctor Cleanup] ' . $message);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message to log
     */
    private function log_error($message) {
        mtrace('[AI Proctor Cleanup ERROR] ' . $message);
        debugging($message, DEBUG_NORMAL);
    }
}
?>
