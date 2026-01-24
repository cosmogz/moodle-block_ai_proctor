<?php
/**
 * Multi-Institution Management Database Schema
 * Centralized system for managing multiple universities
 * 
 * @package block_ai_proctor
 * @copyright 2026 AI Proctor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Multi-Institution Database Manager
 */
class multi_institution_db {
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $DB;
        $dbman = $DB->get_manager();
        
        // Table: Institutions
        $table = new xmldb_table('block_ai_proctor_institutions');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('domain', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('api_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('api_secret', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL);
            $table->add_field('tier', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'free');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('contact_email', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('contact_name', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('license_expires', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('max_students', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('max_exams_month', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('features', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('branding', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('settings', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('domain', XMLDB_INDEX_UNIQUE, ['domain']);
            $table->add_index('api_key', XMLDB_INDEX_UNIQUE, ['api_key']);
            
            $dbman->create_table($table);
        }
        
        // Table: Institution Usage
        $table = new xmldb_table('block_ai_proctor_usage');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('institutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('year', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL);
            $table->add_field('month', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL);
            $table->add_field('exams_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('students_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('violations_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('total_duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('api_calls', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('storage_used', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('billable_amount', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0.00');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('institutionid', XMLDB_KEY_FOREIGN, ['institutionid'], 'block_ai_proctor_institutions', ['id']);
            $table->add_index('institution_period', XMLDB_INDEX_UNIQUE, ['institutionid', 'year', 'month']);
            
            $dbman->create_table($table);
        }
        
        // Table: Institution Admins
        $table = new xmldb_table('block_ai_proctor_inst_admins');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('institutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('role', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'admin');
            $table->add_field('permissions', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('institutionid', XMLDB_KEY_FOREIGN, ['institutionid'], 'block_ai_proctor_institutions', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('institution_user', XMLDB_INDEX_UNIQUE, ['institutionid', 'userid']);
            
            $dbman->create_table($table);
        }
        
        // Table: Institution Payments
        $table = new xmldb_table('block_ai_proctor_payments');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('institutionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('invoice_number', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('amount', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL);
            $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'USD');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('billing_period_start', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('billing_period_end', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('subscription_fee', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0.00');
            $table->add_field('usage_fee', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0.00');
            $table->add_field('exams_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('payment_method', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('transaction_id', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('paid_at', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('institutionid', XMLDB_KEY_FOREIGN, ['institutionid'], 'block_ai_proctor_institutions', ['id']);
            $table->add_index('invoice_number', XMLDB_INDEX_UNIQUE, ['invoice_number']);
            $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            
            $dbman->create_table($table);
        }
        
        return true;
    }
    
    /**
     * Get all institutions
     */
    public static function get_institutions($filters = []) {
        global $DB;
        
        $sql = "SELECT * FROM {block_ai_proctor_institutions} WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['tier'])) {
            $sql .= " AND tier = :tier";
            $params['tier'] = $filters['tier'];
        }
        
        $sql .= " ORDER BY name ASC";
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Get institution by ID
     */
    public static function get_institution($institutionid) {
        global $DB;
        return $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
    }
    
    /**
     * Get institution by API key
     */
    public static function get_institution_by_api_key($api_key) {
        global $DB;
        return $DB->get_record('block_ai_proctor_institutions', ['api_key' => $api_key]);
    }
    
    /**
     * Create new institution
     */
    public static function create_institution($data) {
        global $DB;
        
        $institution = new stdClass();
        $institution->name = $data['name'];
        $institution->domain = $data['domain'];
        $institution->api_key = self::generate_api_key();
        $institution->api_secret = self::generate_api_secret();
        $institution->tier = $data['tier'] ?? 'free';
        $institution->status = 'active';
        $institution->contact_email = $data['contact_email'] ?? null;
        $institution->contact_name = $data['contact_name'] ?? null;
        $institution->license_expires = $data['license_expires'] ?? null;
        $institution->max_students = $data['max_students'] ?? null;
        $institution->max_exams_month = $data['max_exams_month'] ?? 100;
        $institution->features = json_encode($data['features'] ?? []);
        $institution->branding = json_encode($data['branding'] ?? []);
        $institution->settings = json_encode($data['settings'] ?? []);
        $institution->timecreated = time();
        $institution->timemodified = time();
        
        $institutionid = $DB->insert_record('block_ai_proctor_institutions', $institution);
        
        // Create initial usage record
        self::initialize_usage($institutionid);
        
        return $institutionid;
    }
    
    /**
     * Update institution
     */
    public static function update_institution($institutionid, $data) {
        global $DB;
        
        $institution = $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
        
        if (!$institution) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            if (property_exists($institution, $key) && $key !== 'id') {
                if (in_array($key, ['features', 'branding', 'settings']) && is_array($value)) {
                    $institution->$key = json_encode($value);
                } else {
                    $institution->$key = $value;
                }
            }
        }
        
        $institution->timemodified = time();
        
        return $DB->update_record('block_ai_proctor_institutions', $institution);
    }
    
    /**
     * Track exam usage
     */
    public static function track_exam($institutionid) {
        global $DB;
        
        $year = date('Y');
        $month = date('n');
        
        $usage = $DB->get_record('block_ai_proctor_usage', [
            'institutionid' => $institutionid,
            'year' => $year,
            'month' => $month
        ]);
        
        if ($usage) {
            $usage->exams_count++;
            $usage->timemodified = time();
            $DB->update_record('block_ai_proctor_usage', $usage);
        } else {
            $usage = new stdClass();
            $usage->institutionid = $institutionid;
            $usage->year = $year;
            $usage->month = $month;
            $usage->exams_count = 1;
            $usage->students_count = 0;
            $usage->violations_count = 0;
            $usage->total_duration = 0;
            $usage->api_calls = 0;
            $usage->storage_used = 0;
            $usage->billable_amount = 0;
            $usage->timecreated = time();
            $usage->timemodified = time();
            $DB->insert_record('block_ai_proctor_usage', $usage);
        }
        
        return $usage;
    }
    
    /**
     * Get usage for institution
     */
    public static function get_usage($institutionid, $year = null, $month = null) {
        global $DB;
        
        $params = ['institutionid' => $institutionid];
        
        if ($year) {
            $params['year'] = $year;
        }
        
        if ($month) {
            $params['month'] = $month;
        }
        
        return $DB->get_records('block_ai_proctor_usage', $params, 'year DESC, month DESC');
    }
    
    /**
     * Calculate billing for period
     */
    public static function calculate_billing($institutionid, $year, $month) {
        global $DB;
        
        $institution = self::get_institution($institutionid);
        $usage = $DB->get_record('block_ai_proctor_usage', [
            'institutionid' => $institutionid,
            'year' => $year,
            'month' => $month
        ]);
        
        if (!$institution || !$usage) {
            return null;
        }
        
        // Base subscription fee
        $subscription_fees = [
            'free' => 0,
            'professional' => 299,
            'enterprise' => 999
        ];
        
        $subscription_fee = $subscription_fees[$institution->tier] ?? 0;
        
        // Usage fee ($0.08 per exam over free tier limit)
        $free_limit = $institution->tier === 'free' ? 100 : 0;
        $billable_exams = max(0, $usage->exams_count - $free_limit);
        $usage_fee = $billable_exams * 0.08;
        
        $total = $subscription_fee + $usage_fee;
        
        // Update usage record
        $usage->billable_amount = $total;
        $DB->update_record('block_ai_proctor_usage', $usage);
        
        return [
            'subscription_fee' => $subscription_fee,
            'usage_fee' => $usage_fee,
            'total' => $total,
            'exams_count' => $usage->exams_count,
            'billable_exams' => $billable_exams
        ];
    }
    
    /**
     * Generate unique API key
     */
    private static function generate_api_key() {
        return 'ak_' . bin2hex(random_bytes(24));
    }
    
    /**
     * Generate API secret
     */
    private static function generate_api_secret() {
        return bin2hex(random_bytes(48));
    }
    
    /**
     * Initialize usage tracking for new institution
     */
    private static function initialize_usage($institutionid) {
        global $DB;
        
        $usage = new stdClass();
        $usage->institutionid = $institutionid;
        $usage->year = date('Y');
        $usage->month = date('n');
        $usage->exams_count = 0;
        $usage->students_count = 0;
        $usage->violations_count = 0;
        $usage->total_duration = 0;
        $usage->api_calls = 0;
        $usage->storage_used = 0;
        $usage->billable_amount = 0;
        $usage->timecreated = time();
        $usage->timemodified = time();
        
        return $DB->insert_record('block_ai_proctor_usage', $usage);
    }
}
