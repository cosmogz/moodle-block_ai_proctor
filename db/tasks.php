<?php
/**
 * AI Proctor Scheduled Tasks Definition
 * 
 * Defines all scheduled tasks for the AI Proctor plugin.
 * These tasks run automatically on a schedule to maintain
 * system performance and data integrity.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    /**
     * Cleanup old evidence files and maintain database
     * 
     * This task runs daily at 2:00 AM to:
     * - Remove old evidence files based on retention policy
     * - Clean up expired session records
     * - Remove orphaned configuration records
     * - Optimize database tables for performance
     */
    array(
        'classname' => 'block_ai_proctor\\task\\cleanup_old_evidence',
        'blocking' => 0,                    // Non-blocking task
        'minute' => '0',                   // Run at minute 0
        'hour' => '2',                     // Run at 2 AM
        'day' => '*',                      // Every day
        'month' => '*',                    // Every month
        'dayofweek' => '*',               // Every day of week
        'disabled' => 0                    // Enabled by default
    ),
    
    /**
     * Generate statistics and maintenance reports
     * 
     * This task runs weekly on Sunday at 3:00 AM to:
     * - Generate usage statistics
     * - Create maintenance reports
     * - Check system health
     */
    array(
        'classname' => 'block_ai_proctor\\task\\generate_reports',
        'blocking' => 0,                    // Non-blocking task
        'minute' => '0',                   // Run at minute 0
        'hour' => '3',                     // Run at 3 AM
        'day' => '*',                      // Any day
        'month' => '*',                    // Every month
        'dayofweek' => '0',               // Sunday (0 = Sunday)
        'disabled' => 1                    // Disabled by default (optional task)
    ),
    
    /**
     * Archive old violation data
     * 
     * This task runs monthly on the 1st at 1:00 AM to:
     * - Archive resolved violations older than 1 year
     * - Compress old evidence files
     * - Generate compliance reports
     */
    array(
        'classname' => 'block_ai_proctor\\task\\archive_old_data',
        'blocking' => 0,                    // Non-blocking task
        'minute' => '0',                   // Run at minute 0
        'hour' => '1',                     // Run at 1 AM
        'day' => '1',                      // First day of month
        'month' => '*',                    // Every month
        'dayofweek' => '*',               // Any day of week
        'disabled' => 1                    // Disabled by default (enterprise feature)
    )
);
?>
