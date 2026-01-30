<?php
/**
 * AI Proctor Data Archiving Scheduled Task
 * 
 * Monthly task to archive old violation data, compress files,
 * and generate long-term compliance reports.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/task/classes/scheduled_task.php');

/**
 * Scheduled task for archiving old AI Proctor data
 */
class archive_old_data extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task
     * 
     * @return string Task name
     */
    public function get_name() {
        return get_string('archive_old_data', 'block_ai_proctor');
    }

    /**
     * Execute the archiving task
     */
    public function execute() {
        global $DB, $CFG;
        
        $starttime = time();
        $this->log_info('Starting AI Proctor data archiving');
        
        // Get archival settings
        $archive_age_months = get_config('block_ai_proctor', 'archive_age_months') ?: 12;
        $compress_files = get_config('block_ai_proctor', 'compress_archived_files') ?: true;
        
        $archive_cutoff = time() - ($archive_age_months * 30 * 24 * 60 * 60);
        
        $stats = array(
            'records_archived' => 0,
            'files_compressed' => 0,
            'space_saved' => 0,
            'reports_generated' => 0,
            'errors' => array()
        );
        
        try {
            // 1. Archive resolved violations
            $this->archive_resolved_violations($archive_cutoff, $stats);
            
            // 2. Compress old evidence files
            if ($compress_files) {
                $this->compress_evidence_files($archive_cutoff, $stats);
            }
            
            // 3. Generate annual compliance report
            $this->generate_annual_compliance_report($stats);
            
            // 4. Create data retention summary
            $this->create_retention_summary($stats);
            
            $duration = time() - $starttime;
            $this->log_info(sprintf('Data archiving completed in %d seconds. Archived: %d records, compressed: %d files',
                $duration, $stats['records_archived'], $stats['files_compressed']));
            
        } catch (Exception $e) {
            $this->log_error('Data archiving failed: ' . $e->getMessage());
            $stats['errors'][] = 'Critical error: ' . $e->getMessage();
        }
        
        return true;
    }
    
    /**
     * Archive resolved violation records
     * 
     * @param int $cutoff_time Archive cutoff timestamp
     * @param array &$stats Statistics array
     */
    private function archive_resolved_violations($cutoff_time, &$stats) {
        global $DB, $CFG;
        
        $this->log_info('Starting violation archival process');
        
        // Find old resolved violations
        $resolved_violations = $DB->get_records_select('block_ai_proctor',
            'timecreated < ? AND (status = ? OR status = ?)',
            array($cutoff_time, 'dismissed', 'reviewed'),
            'timecreated ASC');
        
        if (empty($resolved_violations)) {
            $this->log_info('No violations found for archival');
            return;
        }
        
        // Create archive directory
        $archive_dir = $CFG->dataroot . '/ai_proctor_archive/';
        if (!file_exists($archive_dir)) {
            if (!mkdir($archive_dir, 0755, true)) {
                $stats['errors'][] = 'Failed to create archive directory';
                return;
            }
        }
        
        $archive_file = $archive_dir . 'violations_' . date('Y-m') . '.json';
        $archived_data = array();
        
        // Load existing archive if it exists
        if (file_exists($archive_file)) {
            $existing_data = file_get_contents($archive_file);
            $archived_data = json_decode($existing_data, true) ?: array();
        }
        
        foreach ($resolved_violations as $violation) {
            try {
                // Add to archive data
                $archived_data[] = array(
                    'id' => $violation->id,
                    'courseid' => $violation->courseid,
                    'userid' => $violation->userid,
                    'violation_type' => $violation->violation_type,
                    'evidence_type' => $violation->evidence_type,
                    'evidence_archived' => !empty($violation->evidence_path),
                    'severity' => $violation->severity,
                    'status' => $violation->status,
                    'reviewed_by' => $violation->reviewed_by,
                    'timecreated' => $violation->timecreated,
                    'timereviewed' => $violation->timereviewed,
                    'archived_at' => time()
                );
                
                // Remove from main table
                if ($DB->delete_records('block_ai_proctor', array('id' => $violation->id))) {
                    $stats['records_archived']++;
                }
                
            } catch (Exception $e) {
                $stats['errors'][] = "Failed to archive violation {$violation->id}: {$e->getMessage()}";
            }
        }
        
        // Save updated archive
        if (!empty($archived_data)) {
            file_put_contents($archive_file, json_encode($archived_data, JSON_PRETTY_PRINT));
            $this->log_info("Archived {$stats['records_archived']} violation records to {$archive_file}");
        }
    }
    
    /**
     * Compress old evidence files
     * 
     * @param int $cutoff_time Compression cutoff timestamp
     * @param array &$stats Statistics array
     */
    private function compress_evidence_files($cutoff_time, &$stats) {
        global $CFG;
        
        $this->log_info('Starting file compression process');
        
        $evidence_dir = $CFG->dataroot . '/ai_proctor_evidence/';
        $compressed_dir = $CFG->dataroot . '/ai_proctor_compressed/';
        
        if (!is_dir($evidence_dir)) {
            $this->log_info('No evidence directory found');
            return;
        }
        
        // Create compressed directory
        if (!file_exists($compressed_dir)) {
            if (!mkdir($compressed_dir, 0755, true)) {
                $stats['errors'][] = 'Failed to create compressed directory';
                return;
            }
        }
        
        $files = scandir($evidence_dir);
        
        foreach ($files as $filename) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            
            $filepath = $evidence_dir . $filename;
            
            // Check if file is old enough
            if (filemtime($filepath) > $cutoff_time) {
                continue;
            }
            
            try {
                $original_size = filesize($filepath);
                
                // Compress file using gzip
                $compressed_file = $compressed_dir . $filename . '.gz';
                
                if ($this->compress_file($filepath, $compressed_file)) {
                    $compressed_size = filesize($compressed_file);
                    $space_saved = $original_size - $compressed_size;
                    
                    // Remove original file
                    if (unlink($filepath)) {
                        $stats['files_compressed']++;
                        $stats['space_saved'] += $space_saved;
                    } else {
                        unlink($compressed_file); // Remove compressed file if original deletion failed
                        $stats['errors'][] = "Failed to remove original file: {$filename}";
                    }
                } else {
                    $stats['errors'][] = "Failed to compress file: {$filename}";
                }
                
            } catch (Exception $e) {
                $stats['errors'][] = "Error compressing {$filename}: {$e->getMessage()}";
            }
        }
        
        if ($stats['files_compressed'] > 0) {
            $this->log_info("Compressed {$stats['files_compressed']} files, saved " . 
                           $this->format_bytes($stats['space_saved']) . " of space");
        }
    }
    
    /**
     * Compress a file using gzip
     * 
     * @param string $source_file Source file path
     * @param string $dest_file Destination compressed file path
     * @return bool Success status
     */
    private function compress_file($source_file, $dest_file) {
        $source = fopen($source_file, 'rb');
        $dest = gzopen($dest_file, 'wb9'); // Maximum compression
        
        if (!$source || !$dest) {
            if ($source) fclose($source);
            if ($dest) gzclose($dest);
            return false;
        }
        
        while (!feof($source)) {
            $chunk = fread($source, 4096);
            gzwrite($dest, $chunk);
        }
        
        fclose($source);
        gzclose($dest);
        
        return file_exists($dest_file);
    }
    
    /**
     * Generate annual compliance report
     * 
     * @param array &$stats Statistics array
     */
    private function generate_annual_compliance_report(&$stats) {
        global $DB;
        
        $this->log_info('Generating annual compliance report');
        
        $year_start = mktime(0, 0, 0, 1, 1, date('Y'));
        $year_end = time();
        
        $report = array(
            'report_year' => date('Y'),
            'period_start' => $year_start,
            'period_end' => $year_end,
            'generated_at' => time()
        );
        
        // Total monitoring activity
        $report['total_evidence'] = $DB->count_records_select('block_ai_proctor',
            'timecreated >= ? AND timecreated <= ?', array($year_start, $year_end));
        
        // Courses monitored
        $report['courses_monitored'] = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT courseid) FROM {block_ai_proctor} 
             WHERE timecreated >= ? AND timecreated <= ?",
            array($year_start, $year_end)
        );
        
        // Users monitored
        $report['users_monitored'] = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {block_ai_proctor} 
             WHERE timecreated >= ? AND timecreated <= ?",
            array($year_start, $year_end)
        );
        
        // Violation breakdown by severity
        $report['violations_by_severity'] = $DB->get_records_sql(
            "SELECT severity, COUNT(*) as count FROM {block_ai_proctor} 
             WHERE timecreated >= ? AND timecreated <= ? 
             GROUP BY severity",
            array($year_start, $year_end)
        );
        
        // Data retention compliance
        $retention_days = get_config('block_ai_proctor', 'evidence_retention_days') ?: 90;
        $retention_cutoff = time() - ($retention_days * 24 * 60 * 60);
        $old_records = $DB->count_records_select('block_ai_proctor',
            'timecreated < ?', array($retention_cutoff));
        
        $report['retention_compliance'] = array(
            'policy_days' => $retention_days,
            'records_past_retention' => $old_records,
            'compliant' => ($old_records == 0)
        );
        
        // Save annual report
        $config = new stdClass();
        $config->courseid = 0;
        $config->setting_name = 'annual_compliance_' . date('Y');
        $config->setting_value = json_encode($report);
        $config->timecreated = time();
        $config->timemodified = time();
        
        try {
            $DB->insert_record('block_ai_proctor_config', $config);
            $stats['reports_generated']++;
            $this->log_info('Annual compliance report generated successfully');
        } catch (Exception $e) {
            $stats['errors'][] = 'Failed to save annual report: ' . $e->getMessage();
        }
    }
    
    /**
     * Create data retention summary
     * 
     * @param array &$stats Statistics array
     */
    private function create_retention_summary(&$stats) {
        global $DB;
        
        $summary = array(
            'created_at' => time(),
            'archival_stats' => $stats,
            'current_data_status' => array(
                'active_evidence_records' => $DB->count_records('block_ai_proctor'),
                'active_sessions' => $DB->count_records('block_ai_proctor_sessions', array('status' => 'active')),
                'total_configurations' => $DB->count_records('block_ai_proctor_config')
            ),
            'storage_efficiency' => array(
                'files_compressed' => $stats['files_compressed'],
                'space_saved' => $stats['space_saved'],
                'space_saved_human' => $this->format_bytes($stats['space_saved'])
            )
        );
        
        $config = new stdClass();
        $config->courseid = 0;
        $config->setting_name = 'retention_summary_' . date('Y-m');
        $config->setting_value = json_encode($summary);
        $config->timecreated = time();
        $config->timemodified = time();
        
        try {
            $DB->insert_record('block_ai_proctor_config', $config);
            $this->log_info('Data retention summary created');
        } catch (Exception $e) {
            $stats['errors'][] = 'Failed to save retention summary: ' . $e->getMessage();
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
        mtrace('[AI Proctor Archive] ' . $message);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message to log
     */
    private function log_error($message) {
        mtrace('[AI Proctor Archive ERROR] ' . $message);
        debugging($message, DEBUG_NORMAL);
    }
}
?>
