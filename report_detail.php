<?php
require_once('../../config.php');

global $DB, $USER, $CFG, $PAGE, $OUTPUT;

// 1. GET PARAMETERS
$courseid = required_param('courseid', PARAM_INT);
$userid   = required_param('userid', PARAM_INT);

// 2. SECURITY CHECKS
require_login();
$context = context_course::instance($courseid);
// Only Teachers and Admins can view this
require_capability('moodle/grade:viewall', $context);

// 3. SETUP PAGE
$PAGE->set_url(new moodle_url('/blocks/ai_proctor/report_detail.php', ['courseid'=>$courseid, 'userid'=>$userid]));
$PAGE->set_context($context);
$PAGE->set_title('Evidence Detail');
$PAGE->set_heading('🕵️ Evidence Detail');
$PAGE->set_pagelayout('popup'); 

echo $OUTPUT->header();

// 4. DISPLAY HEADER
$user = $DB->get_record('user', ['id' => $userid]);
echo "<h3>Evidence for: " . fullname($user) . "</h3>";
echo "<a href='report.php?courseid=$courseid' class='btn btn-secondary mb-3'>⬅️ Back to Dashboard</a>";

// 5. GET EVIDENCE RECORDS
$records = $DB->get_records('block_ai_proctor', ['courseid' => $courseid, 'userid' => $userid], 'timecreated DESC');

if (!$records) {
    echo "<div class='alert alert-success'>No violations found for this student.</div>";
} else {
    echo "<div class='row'>";
    foreach ($records as $r) {
        // Skip the special "Banned" marker record
        if ($r->imagedata === 'banned') continue;

        // --- THE FIX IS HERE ---
        // We point to image.php which securely fetches the file from moodledata
        $img_url = $CFG->wwwroot . '/blocks/ai_proctor/image.php?file=' . $r->imagedata;
        
        $time = userdate($r->timecreated, '%H:%M:%S');
        $color = ($r->message == 'No Face') ? 'text-danger' : 'text-warning';

        echo "<div class='col-md-3 mb-4'>";
        echo "<div class='card shadow-sm'>";
        
        // Image Thumbnail
        echo "<a href='$img_url' target='_blank'>
                <img src='$img_url' class='card-img-top' style='height:160px; object-fit:cover; border-bottom:1px solid #eee;'>
              </a>";
              
        // Evidence Info
        echo "<div class='card-body p-2 text-center'>";
        echo "<strong class='$color'>{$r->message}</strong><br>";
        echo "<small class='text-muted'>$time</small>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
}

echo $OUTPUT->footer();
?>