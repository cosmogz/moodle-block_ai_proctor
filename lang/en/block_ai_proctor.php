<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Proctor';
$string['ai_proctor'] = 'AI Proctor';
$string['ai_proctor:addinstance'] = 'Add AI Proctor Block';
$string['ai_proctor:myaddinstance'] = 'Add AI Proctor to My Moodle';
$string['config_enabled'] = 'Enable Monitoring';
$string['privacy:metadata'] = 'The AI Proctor block stores proctoring data for monitoring purposes.';

// Backup/Restore Language Strings
$string['includeevidence'] = 'Include Evidence Files';
$string['includeevidence_help'] = 'Whether to include captured evidence files (images and videos) in the course backup. Warning: This may significantly increase backup file size.';
$string['includesessions'] = 'Include Session Data';
$string['includesessions_help'] = 'Whether to include detailed proctoring session data. This may contain sensitive monitoring information.';
$string['anonymizedata'] = 'Anonymize User Data';
$string['anonymizedata_help'] = 'Remove user identifiers from backed up evidence data for privacy compliance. Evidence will be preserved but not linked to specific users.';
$string['backupcomplete'] = 'AI Proctor data backup completed successfully';
$string['restorecomplete'] = 'AI Proctor data restored successfully';
$string['backupfailed'] = 'AI Proctor backup failed';
$string['restorefailed'] = 'AI Proctor restore failed';

// Backup Warning Messages
$string['backup_warning_large_videos'] = 'This backup contains {$a} video evidence files which may significantly increase backup size and processing time.';
$string['backup_warning_session_data'] = 'This backup includes detailed session monitoring data that may contain sensitive information about student behavior.';
$string['backup_warning_recent_evidence'] = 'This backup contains {$a} recent evidence records from the last 24 hours. Ensure compliance with data retention policies.';

// Scheduled Task Names and Descriptions
$string['cleanup_old_evidence'] = 'AI Proctor: Cleanup Old Evidence';
$string['cleanup_old_evidence_desc'] = 'Automatically removes old evidence files and maintains database performance by cleaning up expired session records and orphaned data.';
$string['generate_reports'] = 'AI Proctor: Generate Reports';
$string['generate_reports_desc'] = 'Generates weekly usage statistics, system health reports, and compliance summaries for administrative oversight.';
$string['archive_old_data'] = 'AI Proctor: Archive Old Data';
$string['archive_old_data_desc'] = 'Archives resolved violation records, compresses old evidence files, and generates annual compliance reports for long-term data management.';

// Event strings
$string['event_block_viewed'] = 'AI Proctor block viewed';
$string['event_monitoring_started'] = 'AI monitoring session started';
$string['event_monitoring_ended'] = 'AI monitoring session ended';
$string['event_violation_detected'] = 'AI violation detected';
$string['event_evidence_captured'] = 'Evidence captured';
$string['event_configuration_updated'] = 'AI Proctor configuration updated';

// Privacy and GDPR strings
$string['privacy:metadata:block_ai_proctor'] = 'The AI Proctor evidence table stores monitoring data captured during exams, including violation records, evidence files, and system analysis.';
$string['privacy:metadata:block_ai_proctor:userid'] = 'The ID of the user being monitored during the exam session.';
$string['privacy:metadata:block_ai_proctor:courseid'] = 'The ID of the course where the monitoring session occurred.';
$string['privacy:metadata:block_ai_proctor:ip_address'] = 'The IP address of the user during the monitoring session for security tracking.';
$string['privacy:metadata:block_ai_proctor:user_agent'] = 'Browser and device information used to identify potential security risks.';
$string['privacy:metadata:block_ai_proctor:violation_type'] = 'The type of potential academic integrity violation detected by the AI system.';
$string['privacy:metadata:block_ai_proctor:evidence_data'] = 'Base64-encoded image or video data captured as evidence of potential violations.';
$string['privacy:metadata:block_ai_proctor:ai_confidence'] = 'The AI system confidence level (0-100%) for the detected violation.';
$string['privacy:metadata:block_ai_proctor:timestamp'] = 'The exact time when the violation was detected and evidence was captured.';
$string['privacy:metadata:block_ai_proctor:session_id'] = 'Unique identifier linking evidence to a specific monitoring session.';
$string['privacy:metadata:block_ai_proctor:evidence_type'] = 'Format of the captured evidence (image, video, audio, etc.).';
$string['privacy:metadata:block_ai_proctor:file_path'] = 'Server file path where evidence files are stored for administrative review.';
$string['privacy:metadata:block_ai_proctor:file_size'] = 'Size of the evidence file in bytes for storage management.';

$string['privacy:metadata:block_ai_proctor_config'] = 'User-specific configuration settings for AI monitoring behavior and preferences.';
$string['privacy:metadata:block_ai_proctor_config:userid'] = 'The ID of the user whose monitoring preferences are stored.';
$string['privacy:metadata:block_ai_proctor_config:courseid'] = 'The course where these monitoring preferences apply.';
$string['privacy:metadata:block_ai_proctor_config:setting_name'] = 'The name of the configuration setting (e.g., sensitivity level, alert preferences).';
$string['privacy:metadata:block_ai_proctor_config:setting_value'] = 'The user-configured value for the monitoring setting.';
$string['privacy:metadata:block_ai_proctor_config:created_at'] = 'When the configuration setting was first created.';
$string['privacy:metadata:block_ai_proctor_config:updated_at'] = 'When the configuration setting was last modified.';

$string['privacy:metadata:block_ai_proctor_sessions'] = 'Complete monitoring session records tracking user behavior throughout exam periods.';
$string['privacy:metadata:block_ai_proctor_sessions:userid'] = 'The ID of the user whose monitoring session is recorded.';
$string['privacy:metadata:block_ai_proctor_sessions:courseid'] = 'The course where the monitoring session took place.';
$string['privacy:metadata:block_ai_proctor_sessions:starttime'] = 'Timestamp when the AI monitoring session began.';
$string['privacy:metadata:block_ai_proctor_sessions:endtime'] = 'Timestamp when the AI monitoring session ended.';
$string['privacy:metadata:block_ai_proctor_sessions:duration'] = 'Total duration of the monitoring session in seconds.';
$string['privacy:metadata:block_ai_proctor_sessions:violationcount'] = 'Total number of violations detected during the session.';
$string['privacy:metadata:block_ai_proctor_sessions:evidencecount'] = 'Number of evidence files captured during the session.';
$string['privacy:metadata:block_ai_proctor_sessions:systeminfo'] = 'JSON data containing browser, device, and system information for security analysis.';
$string['privacy:metadata:block_ai_proctor_sessions:status'] = 'Final status of the monitoring session (completed, terminated, suspended, etc.).';
$string['privacy:metadata:block_ai_proctor_sessions:finaldata'] = 'JSON summary of session metrics, suspicion levels, and final AI analysis results.';

$string['privacy:metadata:mediapipe_ai'] = 'External AI service (Google MediaPipe) processes facial recognition and behavior analysis data.';
$string['privacy:metadata:mediapipe_ai:facial_landmarks'] = 'Facial landmark coordinates processed by MediaPipe for position and movement analysis.';
$string['privacy:metadata:mediapipe_ai:behavior_analysis'] = 'AI-generated analysis of user behavior patterns and potential violations.';
$string['privacy:metadata:mediapipe_ai:processing_timestamp'] = 'When the AI processing of facial data occurred.';

$string['privacy:metadata:core_files'] = 'Evidence files (images and videos) are stored using Moodle\'s file system for security and access control.';

$string['privacy:path:evidence'] = 'AI Proctor Evidence Records';
$string['privacy:path:sessions'] = 'AI Proctor Monitoring Sessions';
$string['privacy:path:config'] = 'AI Proctor Configuration Settings';
$string['privacy:path:evidence_files'] = 'Evidence Files';
$string['privacy:metadata:evidence_file'] = 'Metadata about captured evidence files including violation context and AI analysis results.';
?>
