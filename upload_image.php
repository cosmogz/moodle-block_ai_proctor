<?php
/**
 * AI Proctor Evidence Upload Handler
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('../../config.php');

global $DB, $USER, $CFG;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debug_log($message) {
    error_log("[AI_PROCTOR] " . $message);
}

debug_log("Upload request received from user " . $USER->id);

// Security: Require login and valid session
require_login();

try {
    require_sesskey();
} catch (Exception $e) {
    debug_log("Session key validation failed: " . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session - page may have expired. Please refresh.', 'code' => 'INVALID_SESSKEY']);
    exit;
}

// Rate limiting - prevent upload spam
$rate_limit_key = 'ai_proctor_upload_' . $USER->id;
$last_upload_time = get_user_preferences($rate_limit_key, 0);
$min_interval = get_config('block_ai_proctor', 'upload_cooldown') ?: 2;

if ((time() - $last_upload_time) < $min_interval) {
    debug_log("Rate limit exceeded for user " . $USER->id);
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded', 'retry_after' => $min_interval, 'code' => 'RATE_LIMIT']);
    exit;
}

set_user_preference($rate_limit_key, time());

// Get and validate input
$input = file_get_contents('php://input');
debug_log("Input received: " . substr($input, 0, 200) . "...");

$data = json_decode($input, true);
$json_error = json_last_error();

if ($json_error !== JSON_ERROR_NONE) {
    debug_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg(), 'code' => 'JSON_ERROR']);
    exit;
}

if (!$data || !isset($data['courseid']) || !isset($data['sesskey']) || !isset($data['batch_data'])) {
    debug_log("Missing required fields in request");
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields (courseid, sesskey, batch_data)', 'code' => 'MISSING_FIELDS']);
    exit;
}

$courseid = clean_param($data['courseid'], PARAM_INT);
$sesskey_sent = clean_param($data['sesskey'], PARAM_ALPHANUMEXT);

debug_log("Processing request for course {$courseid}, sesskey validation...");

// Validate session key
if ($sesskey_sent !== sesskey()) {
    debug_log("Session key mismatch. Sent: {$sesskey_sent}, Expected: " . sesskey());
    http_response_code(403);
    echo json_encode(['error' => 'Session key mismatch - please refresh page', 'code' => 'SESSKEY_MISMATCH']);
    exit;
}

// Verify user is enrolled in course
$context = context_course::instance($courseid);
if (!is_enrolled($context, $USER)) {
    debug_log("User {$USER->id} not enrolled in course {$courseid}");
    http_response_code(403);
    echo json_encode(['error' => 'Not enrolled in course', 'code' => 'NOT_ENROLLED']);
    exit;
}

// Check if student is banned from this course
$banned = $DB->get_record('block_ai_proctor', [
    'courseid' => $courseid,
    'userid' => $USER->id,
    'status' => 'banned'
]);

if ($banned) {
    debug_log("User {$USER->id} is banned from course {$courseid}");
    http_response_code(403);
    echo json_encode(['error' => 'Access denied - Account suspended for this course', 'code' => 'USER_BANNED']);
    exit;
}

// Prepare secure storage folder
$evidence_folder = $CFG->dataroot . '/ai_proctor_evidence/';
if (!file_exists($evidence_folder)) {
    debug_log("Creating evidence folder: {$evidence_folder}");
    if (!mkdir($evidence_folder, 0755, true)) {
        debug_log("Failed to create evidence folder");
        http_response_code(500);
        echo json_encode(['error' => 'Storage initialization failed', 'code' => 'STORAGE_INIT_FAILED']);
        exit;
    }
}

// Process evidence batch
$uploaded_count = 0;
$errors = [];

debug_log("Processing " . count($data['batch_data']) . " evidence items");

foreach ($data['batch_data'] as $index => $evidence) {
    try {
        debug_log("Processing evidence item {$index}");
        
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->courseid = $courseid;
        $record->violation_type = isset($evidence['message']) ? substr($evidence['message'], 0, 255) : 'Unknown';
        $record->timecreated = time();
        $record->status = 'active';
        $record->severity = determineSeverity($record->violation_type);
        
        // Handle video evidence
        if (isset($evidence['video'])) {
            debug_log("Processing video evidence");
            
            $video_data = $evidence['video'];
            
            // Validate video size
            $max_size = (get_config('block_ai_proctor', 'max_video_size') ?: 5) * 1024 * 1024;
            $video_size = strlen($video_data);
            
            if ($video_size > $max_size) {
                $error_msg = "Video too large: " . round($video_size / 1024 / 1024, 2) . "MB";
                debug_log($error_msg);
                $errors[] = $error_msg;
                continue;
            }
            
            // Extract base64 data
            if (preg_match('/^data:video\/(\w+);base64,(.*)$/', $video_data, $matches)) {
                $video_base64 = $matches[2];
                $video_binary = base64_decode($video_base64);
                
                if ($video_binary === false) {
                    debug_log("Invalid video encoding");
                    $errors[] = "Invalid video encoding";
                    continue;
                }
                
                $filename = 'v_' . $courseid . '_' . $USER->id . '_' . time() . '_' . uniqid() . '.webm';
                $filepath = $evidence_folder . $filename;
                
                if (file_put_contents($filepath, $video_binary) === false) {
                    debug_log("Failed to save video file");
                    $errors[] = "Failed to save video";
                    continue;
                }
                
                $record->evidence_path = $filename;
                $record->evidence_type = 'video';
                $record->duration = isset($evidence['duration']) ? intval($evidence['duration']) : 5;
                
                debug_log("Video saved as {$filename}");
            }
            
        } elseif (isset($evidence['image'])) {
            debug_log("Processing image evidence");
            
            // Handle image evidence
            if (preg_match('/^data:image\/(\w+);base64,(.*)$/', $evidence['image'], $matches)) {
                $image_type = $matches[1];
                $image_base64 = $matches[2];
                $image_binary = base64_decode($image_base64);
                
                if ($image_binary === false) {
                    debug_log("Invalid image encoding");
                    continue;
                }
                
                $filename = 'i_' . $courseid . '_' . $USER->id . '_' . time() . '_' . uniqid() . '.' . $image_type;
                $filepath = $evidence_folder . $filename;
                
                if (file_put_contents($filepath, $image_binary) !== false) {
                    $record->evidence_path = $filename;
                    $record->evidence_type = 'image';
                    debug_log("Image saved as {$filename}");
                }
            }
        }
        
        if (isset($record->evidence_path)) {
            // Try to insert record
            try {
                $DB->insert_record('block_ai_proctor', $record);
                $uploaded_count++;
                debug_log("Database record inserted successfully");
            } catch (dml_exception $e) {
                debug_log("Database error: " . $e->getMessage());
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        debug_log("Exception processing evidence: " . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

debug_log("Upload complete: {$uploaded_count} uploaded, " . count($errors) . " errors");

// Send success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'uploaded' => $uploaded_count,
    'errors' => $errors,
    'debug' => "Processed by user {$USER->id} at " . date('Y-m-d H:i:s')
]);

function determineSeverity($violation_type) {
    $high = ['No Face', 'Talking', 'Multiple'];
    $medium = ['Looking Down', 'Turning'];
    
    foreach ($high as $type) {
        if (stripos($violation_type, $type) !== false) return 'high';
    }
    foreach ($medium as $type) {
        if (stripos($violation_type, $type) !== false) return 'medium';
    }
    return 'low';
}
?>
                continue;
            }
            
            // Extract base64 data
            if (preg_match('/^data:video\/(\w+);base64,(.*)$/', $video_data, $matches)) {
                $video_base64 = $matches[2];
                $video_binary = base64_decode($video_base64);
                
                if ($video_binary === false) {
                    $errors[] = "Invalid video encoding";
                    continue;
                }
                
                $filename = 'v_' . $courseid . '_' . $USER->id . '_' . time() . '_' . uniqid() . '.webm';
                $filepath = $evidence_folder . $filename;
                
                if (file_put_contents($filepath, $video_binary) === false) {
                    $errors[] = "Failed to save video";
                    continue;
                }
                
                $record->evidence_path = $filename;
                $record->evidence_type = 'video';
                $record->duration = isset($evidence['duration']) ? intval($evidence['duration']) : 5;
            }
            
        } elseif (isset($evidence['image'])) {
            // Handle image evidence
            if (preg_match('/^data:image\/(\w+);base64,(.*)$/', $evidence['image'], $matches)) {
                $image_type = $matches[1];
                $image_base64 = $matches[2];
                $image_binary = base64_decode($image_base64);
                
                if ($image_binary === false) {
                    continue;
                }
                
                $filename = 'i_' . $courseid . '_' . $USER->id . '_' . time() . '_' . uniqid() . '.' . $image_type;
                $filepath = $evidence_folder . $filename;
                
                if (file_put_contents($filepath, $image_binary) !== false) {
                    $record->evidence_path = $filename;
                    $record->evidence_type = 'image';
                }
            }
        }
        
        if (isset($record->evidence_path)) {
            $DB->insert_record('block_ai_proctor', $record);
            $uploaded_count++;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'uploaded' => $uploaded_count,
    'errors' => $errors
]);

function determineSeverity($violation_type) {
    $high = ['No Face', 'Talking', 'Multiple'];
    $medium = ['Looking Down', 'Turning'];
    
    foreach ($high as $type) {
        if (stripos($violation_type, $type) !== false) return 'high';
    }
    foreach ($medium as $type) {
        if (stripos($violation_type, $type) !== false) return 'medium';
    }
    return 'low';
}
?>
