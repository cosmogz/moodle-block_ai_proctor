<?php
require_once('../../config.php');

global $CFG;

// 1. GET FILENAME
$file = required_param('file', PARAM_FILE);

// 2. SECURITY (Teachers Only)
require_login();
$system_context = context_system::instance();
if (!has_capability('moodle/grade:viewall', $system_context) && !is_siteadmin()) {
    die("Access Denied.");
}

// 3. LOCATE FILE
$filepath = $CFG->dataroot . '/ai_proctor_evidence/' . $file;

if (!file_exists($filepath)) {
    header('HTTP/1.0 404 Not Found');
    die('Image not found.');
}

// 4. SERVE FILE
header('Content-Type: image/jpeg');
readfile($filepath);
?>