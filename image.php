<?php
/**
 * AI Proctor Block
 *
 * @package    block_ai_proctor
 * @copyright  2024 Qigen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $DB;

// 1. GET PARAMETERS
$file = required_param('file', PARAM_FILE);
$courseid = required_param('courseid', PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);

// 2. SECURITY (Teachers Only)
require_login($courseid);
$context = context_course::instance($courseid);
if (!has_capability('moodle/grade:viewall', $context) && !is_siteadmin()) {
    die("Access Denied.");
}

// 3. GET FILE VIA MOODLE FILE API
$fs = get_file_storage();
$stored_file = $fs->get_file($context->id, 'block_ai_proctor', 'evidence', $itemid, '/', $file);

if (!$stored_file || $stored_file->is_directory()) {
    // Fallback: Check if it's an old file stored globally with itemid 0
    $stored_file = $fs->get_file($context->id, 'block_ai_proctor', 'evidence', 0, '/', $file);
    if (!$stored_file || $stored_file->is_directory()) {
        header('HTTP/1.0 404 Not Found');
        die('File not found in storage API.');
    }
}

// 4. SERVE FILE
send_stored_file($stored_file, 86400, 0, false); // Moodle core function to serve files
