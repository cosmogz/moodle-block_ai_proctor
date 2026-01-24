<?php
/**
 * AI Proctor Block Database Upgrade Script
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_ai_proctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025012201) {
        
        // Define main evidence table
        $table = new xmldb_table('block_ai_proctor');
        
        // Add new fields for enterprise features
        $field = new xmldb_field('violation_type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'Unknown');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('evidence_type', XMLDB_TYPE_CHAR, '20', null, null, null, 'image');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('evidence_path', XMLDB_TYPE_CHAR, '500', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('severity', XMLDB_TYPE_CHAR, '20', null, null, null, 'low');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('reviewed_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('review_notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Migrate legacy 'message' field to 'violation_type' if exists
        $legacy_field = new xmldb_field('message', XMLDB_TYPE_CHAR, '255');
        if ($dbman->field_exists($table, $legacy_field)) {
            // Copy data to new field
            $DB->execute("UPDATE {block_ai_proctor} SET violation_type = message WHERE violation_type = 'Unknown' OR violation_type IS NULL");
            // Keep legacy field for backward compatibility
        }
        
        // Create performance indexes
        $index = new xmldb_index('course_user_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('course_status_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'status']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('severity_time_idx', XMLDB_INDEX_NOTUNIQUE, ['severity', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Create configuration table for per-course settings
        $config_table = new xmldb_table('block_ai_proctor_config');
        if (!$dbman->table_exists($config_table)) {
            $config_table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $config_table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $config_table->add_field('setting_name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $config_table->add_field('setting_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $config_table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $config_table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $config_table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $config_table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $config_table->add_key('courseid_setting_unique', XMLDB_KEY_UNIQUE, ['courseid', 'setting_name']);
            
            $dbman->create_table($config_table);
        }

        // Create sessions table for active monitoring
        $sessions_table = new xmldb_table('block_ai_proctor_sessions');
        if (!$dbman->table_exists($sessions_table)) {
            $sessions_table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $sessions_table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $sessions_table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $sessions_table->add_field('session_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $sessions_table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $sessions_table->add_field('violation_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $sessions_table->add_field('last_activity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $sessions_table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $sessions_table->add_field('timeended', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            
            $sessions_table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $sessions_table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $sessions_table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            
            $sessions_table->add_index('session_key_idx', XMLDB_INDEX_UNIQUE, ['session_key']);
            $sessions_table->add_index('course_user_status_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid', 'status']);
            
            $dbman->create_table($sessions_table);
        }
        
        upgrade_block_savepoint(true, 2025012201, 'ai_proctor');
    }

    return true;
}
?>
