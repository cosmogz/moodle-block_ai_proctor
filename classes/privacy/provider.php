<?php
/**
 * Privacy provider for AI Proctor Block
 * 
 * Handles GDPR compliance for personal data stored by the AI Proctor system.
 * Manages user monitoring data, evidence files, and session records according
 * to privacy regulations and data protection requirements.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for the AI Proctor block plugin
 */
class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Return the fields which contain personal data
     *
     * @param collection $collection a reference to the collection to use to store the metadata
     * @return collection the updated collection of metadata items
     */
    public static function get_metadata(collection $collection): collection {
        
        // Main evidence table storing monitoring data
        $collection->add_database_table(
            'block_ai_proctor',
            [
                'userid' => 'privacy:metadata:block_ai_proctor:userid',
                'courseid' => 'privacy:metadata:block_ai_proctor:courseid',
                'ip_address' => 'privacy:metadata:block_ai_proctor:ip_address',
                'user_agent' => 'privacy:metadata:block_ai_proctor:user_agent',
                'violation_type' => 'privacy:metadata:block_ai_proctor:violation_type',
                'evidence_data' => 'privacy:metadata:block_ai_proctor:evidence_data',
                'ai_confidence' => 'privacy:metadata:block_ai_proctor:ai_confidence',
                'timestamp' => 'privacy:metadata:block_ai_proctor:timestamp',
                'session_id' => 'privacy:metadata:block_ai_proctor:session_id',
                'evidence_type' => 'privacy:metadata:block_ai_proctor:evidence_type',
                'file_path' => 'privacy:metadata:block_ai_proctor:file_path',
                'file_size' => 'privacy:metadata:block_ai_proctor:file_size'
            ],
            'privacy:metadata:block_ai_proctor'
        );

        // Configuration table storing user-specific settings
        $collection->add_database_table(
            'block_ai_proctor_config',
            [
                'userid' => 'privacy:metadata:block_ai_proctor_config:userid',
                'courseid' => 'privacy:metadata:block_ai_proctor_config:courseid',
                'setting_name' => 'privacy:metadata:block_ai_proctor_config:setting_name',
                'setting_value' => 'privacy:metadata:block_ai_proctor_config:setting_value',
                'created_at' => 'privacy:metadata:block_ai_proctor_config:created_at',
                'updated_at' => 'privacy:metadata:block_ai_proctor_config:updated_at'
            ],
            'privacy:metadata:block_ai_proctor_config'
        );

        // Sessions table storing monitoring session data
        $collection->add_database_table(
            'block_ai_proctor_sessions',
            [
                'userid' => 'privacy:metadata:block_ai_proctor_sessions:userid',
                'courseid' => 'privacy:metadata:block_ai_proctor_sessions:courseid',
                'starttime' => 'privacy:metadata:block_ai_proctor_sessions:starttime',
                'endtime' => 'privacy:metadata:block_ai_proctor_sessions:endtime',
                'duration' => 'privacy:metadata:block_ai_proctor_sessions:duration',
                'violationcount' => 'privacy:metadata:block_ai_proctor_sessions:violationcount',
                'evidencecount' => 'privacy:metadata:block_ai_proctor_sessions:evidencecount',
                'systeminfo' => 'privacy:metadata:block_ai_proctor_sessions:systeminfo',
                'status' => 'privacy:metadata:block_ai_proctor_sessions:status',
                'finaldata' => 'privacy:metadata:block_ai_proctor_sessions:finaldata'
            ],
            'privacy:metadata:block_ai_proctor_sessions'
        );

        // External services - MediaPipe AI processing
        $collection->add_external_location_link(
            'mediapipe_ai',
            [
                'facial_landmarks' => 'privacy:metadata:mediapipe_ai:facial_landmarks',
                'behavior_analysis' => 'privacy:metadata:mediapipe_ai:behavior_analysis',
                'processing_timestamp' => 'privacy:metadata:mediapipe_ai:processing_timestamp'
            ],
            'privacy:metadata:mediapipe_ai'
        );

        // File storage for evidence files
        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:core_files'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user
     *
     * @param int $userid the userid
     * @return contextlist the list of contexts containing user info for the user
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Get contexts where the user has AI proctor data
        $sql = "SELECT DISTINCT c.id
                FROM {context} c
                INNER JOIN {course} co ON c.instanceid = co.id AND c.contextlevel = :contextlevel
                WHERE c.id IN (
                    SELECT DISTINCT ctx.id
                    FROM {context} ctx
                    INNER JOIN {course} crs ON ctx.instanceid = crs.id
                    WHERE ctx.contextlevel = :ctxlevel
                    AND crs.id IN (
                        SELECT DISTINCT courseid 
                        FROM {block_ai_proctor} 
                        WHERE userid = :userid1
                        UNION
                        SELECT DISTINCT courseid 
                        FROM {block_ai_proctor_config} 
                        WHERE userid = :userid2
                        UNION
                        SELECT DISTINCT courseid 
                        FROM {block_ai_proctor_sessions} 
                        WHERE userid = :userid3
                    )
                )";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'ctxlevel' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context
     *
     * @param userlist $userlist the userlist containing the list of users who have data in this context/plugin combination
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $courseid = $context->instanceid;

        // Get users from evidence table
        $sql = "SELECT DISTINCT userid
                FROM {block_ai_proctor}
                WHERE courseid = :courseid1";
        $userlist->add_from_sql('userid', $sql, ['courseid1' => $courseid]);

        // Get users from config table
        $sql = "SELECT DISTINCT userid
                FROM {block_ai_proctor_config}
                WHERE courseid = :courseid2";
        $userlist->add_from_sql('userid', $sql, ['courseid2' => $courseid]);

        // Get users from sessions table
        $sql = "SELECT DISTINCT userid
                FROM {block_ai_proctor_sessions}
                WHERE courseid = :courseid3";
        $userlist->add_from_sql('userid', $sql, ['courseid3' => $courseid]);
    }

    /**
     * Export personal data for the given approved_contextlist
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $courseid = $context->instanceid;
                self::export_user_data_for_course($userid, $courseid, $context);
            }
        }
    }

    /**
     * Export user data for a specific course
     *
     * @param int $userid
     * @param int $courseid
     * @param \context_course $context
     */
    private static function export_user_data_for_course(int $userid, int $courseid, \context_course $context) {
        global $DB;

        // Export evidence data
        $evidence_data = $DB->get_records('block_ai_proctor', 
            ['userid' => $userid, 'courseid' => $courseid], 
            'timestamp DESC'
        );

        if (!empty($evidence_data)) {
            $data = [];
            foreach ($evidence_data as $record) {
                $data[] = [
                    'violation_type' => $record->violation_type,
                    'evidence_type' => $record->evidence_type,
                    'ai_confidence' => $record->ai_confidence . '%',
                    'timestamp' => transform::datetime($record->timestamp),
                    'session_id' => $record->session_id,
                    'ip_address' => $record->ip_address,
                    'user_agent' => $record->user_agent,
                    'file_size' => $record->file_size ? format_float($record->file_size / 1024, 2) . ' KB' : 'N/A',
                    'has_evidence_file' => !empty($record->file_path) ? 'Yes' : 'No'
                ];
            }

            writer::with_context($context)->export_data([
                get_string('privacy:path:evidence', 'block_ai_proctor')
            ], (object) ['evidence_records' => $data]);
        }

        // Export session data
        $session_data = $DB->get_records('block_ai_proctor_sessions', 
            ['userid' => $userid, 'courseid' => $courseid], 
            'starttime DESC'
        );

        if (!empty($session_data)) {
            $data = [];
            foreach ($session_data as $record) {
                $systeminfo = json_decode($record->systeminfo, true) ?: [];
                $finaldata = json_decode($record->finaldata, true) ?: [];
                
                $data[] = [
                    'session_id' => $record->id,
                    'start_time' => transform::datetime($record->starttime),
                    'end_time' => $record->endtime ? transform::datetime($record->endtime) : 'Ongoing',
                    'duration' => $record->duration ? gmdate('H:i:s', $record->duration) : 'N/A',
                    'violation_count' => $record->violationcount,
                    'evidence_count' => $record->evidencecount,
                    'status' => $record->status,
                    'browser_info' => $systeminfo['browser'] ?? 'Unknown',
                    'ip_address' => $systeminfo['ip_address'] ?? 'Unknown',
                    'final_suspicion_level' => $finaldata['suspicion_level'] ?? 'N/A'
                ];
            }

            writer::with_context($context)->export_data([
                get_string('privacy:path:sessions', 'block_ai_proctor')
            ], (object) ['session_records' => $data]);
        }

        // Export configuration data
        $config_data = $DB->get_records('block_ai_proctor_config', 
            ['userid' => $userid, 'courseid' => $courseid], 
            'created_at DESC'
        );

        if (!empty($config_data)) {
            $data = [];
            foreach ($config_data as $record) {
                $data[] = [
                    'setting_name' => $record->setting_name,
                    'setting_value' => $record->setting_value,
                    'created_at' => transform::datetime($record->created_at),
                    'updated_at' => $record->updated_at ? transform::datetime($record->updated_at) : 'Never'
                ];
            }

            writer::with_context($context)->export_data([
                get_string('privacy:path:config', 'block_ai_proctor')
            ], (object) ['configuration_records' => $data]);
        }

        // Export evidence files if they exist
        self::export_evidence_files($userid, $courseid, $context);
    }

    /**
     * Export evidence files for the user
     *
     * @param int $userid
     * @param int $courseid
     * @param \context_course $context
     */
    private static function export_evidence_files(int $userid, int $courseid, \context_course $context) {
        global $DB;

        // Get evidence records with file paths
        $evidence_files = $DB->get_records_select(
            'block_ai_proctor',
            'userid = :userid AND courseid = :courseid AND file_path IS NOT NULL AND file_path != \'\'',
            ['userid' => $userid, 'courseid' => $courseid],
            'timestamp DESC'
        );

        foreach ($evidence_files as $evidence) {
            if (!empty($evidence->file_path) && file_exists($evidence->file_path)) {
                $filename = basename($evidence->file_path);
                $filepath = [
                    get_string('privacy:path:evidence_files', 'block_ai_proctor'),
                    $evidence->violation_type . '_' . date('Y-m-d_H-i-s', $evidence->timestamp)
                ];

                // Export the actual file
                writer::with_context($context)->export_file($filepath, $evidence->file_path);

                // Export metadata about the file
                $file_metadata = [
                    'original_filename' => $filename,
                    'violation_type' => $evidence->violation_type,
                    'evidence_type' => $evidence->evidence_type,
                    'capture_timestamp' => transform::datetime($evidence->timestamp),
                    'file_size' => format_float($evidence->file_size / 1024, 2) . ' KB',
                    'ai_confidence' => $evidence->ai_confidence . '%',
                    'session_id' => $evidence->session_id
                ];

                writer::with_context($context)->export_metadata(
                    $filepath,
                    'evidence_metadata.json',
                    $file_metadata,
                    get_string('privacy:metadata:evidence_file', 'block_ai_proctor')
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context
     *
     * @param \context $context the context to delete in
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_course) {
            $courseid = $context->instanceid;
            self::delete_course_data($courseid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $courseid = $context->instanceid;
                self::delete_user_data_in_course($userid, $courseid);
            }
        }
    }

    /**
     * Delete multiple users within a single context
     *
     * @param approved_userlist $userlist the approved context and user information to delete information for
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        
        if ($context instanceof \context_course) {
            $courseid = $context->instanceid;
            $userids = $userlist->get_userids();

            if (!empty($userids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                
                // Delete evidence files first
                $evidence_records = $DB->get_records_select(
                    'block_ai_proctor',
                    "courseid = :courseid AND userid $insql",
                    array_merge(['courseid' => $courseid], $inparams)
                );

                foreach ($evidence_records as $record) {
                    if (!empty($record->file_path) && file_exists($record->file_path)) {
                        unlink($record->file_path);
                    }
                }

                // Delete database records
                $DB->delete_records_select('block_ai_proctor', 
                    "courseid = :courseid AND userid $insql", 
                    array_merge(['courseid' => $courseid], $inparams));
                    
                $DB->delete_records_select('block_ai_proctor_config', 
                    "courseid = :courseid AND userid $insql", 
                    array_merge(['courseid' => $courseid], $inparams));
                    
                $DB->delete_records_select('block_ai_proctor_sessions', 
                    "courseid = :courseid AND userid $insql", 
                    array_merge(['courseid' => $courseid], $inparams));
            }
        }
    }

    /**
     * Delete all AI proctor data for a specific course
     *
     * @param int $courseid
     */
    private static function delete_course_data(int $courseid) {
        global $DB;

        // Delete evidence files first
        $evidence_records = $DB->get_records('block_ai_proctor', ['courseid' => $courseid]);
        foreach ($evidence_records as $record) {
            if (!empty($record->file_path) && file_exists($record->file_path)) {
                unlink($record->file_path);
            }
        }

        // Delete database records
        $DB->delete_records('block_ai_proctor', ['courseid' => $courseid]);
        $DB->delete_records('block_ai_proctor_config', ['courseid' => $courseid]);
        $DB->delete_records('block_ai_proctor_sessions', ['courseid' => $courseid]);
    }

    /**
     * Delete all AI proctor data for a specific user in a specific course
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function delete_user_data_in_course(int $userid, int $courseid) {
        global $DB;

        // Delete evidence files first
        $evidence_records = $DB->get_records('block_ai_proctor', 
            ['userid' => $userid, 'courseid' => $courseid]);
        
        foreach ($evidence_records as $record) {
            if (!empty($record->file_path) && file_exists($record->file_path)) {
                unlink($record->file_path);
            }
        }

        // Delete database records
        $DB->delete_records('block_ai_proctor', 
            ['userid' => $userid, 'courseid' => $courseid]);
            
        $DB->delete_records('block_ai_proctor_config', 
            ['userid' => $userid, 'courseid' => $courseid]);
            
        $DB->delete_records('block_ai_proctor_sessions', 
            ['userid' => $userid, 'courseid' => $courseid]);
    }
}
