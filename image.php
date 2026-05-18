<?php
/**
 * AI Proctor Block
 *
 * @package    block_ai_proctor
 * @copyright  2024 Qigen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
