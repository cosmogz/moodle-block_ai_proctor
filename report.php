<?php
require_once('../../config.php');

global $DB, $USER, $CFG, $PAGE, $OUTPUT;

// 1. GET PARAMETERS
$courseid = required_param('courseid', PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$target   = optional_param('target', 0, PARAM_INT);

// 2. SECURITY
require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/grade:viewall', $context);

// 3. HANDLE ACTIONS (Kill Switch)
if ($action === 'ban' && $target && confirm_sesskey()) {
    $record = new stdClass();
    $record->courseid = $courseid;
    $record->userid = $target;
    $record->imagedata = 'banned'; 
    $record->message = '❌ DISQUALIFIED BY TEACHER';
    $record->timecreated = time();
    $DB->insert_record('block_ai_proctor', $record);
    redirect(new moodle_url('/blocks/ai_proctor/report.php', ['courseid' => $courseid]), 'Student Disqualified.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// 4. GET STUDENT DATA (Aggregated)
// Sorts by Violation Count DESC (Red flag students at top)
$sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
               COUNT(p.id) as violations, 
               MAX(p.timecreated) as last_violation
        FROM {user} u
        JOIN {block_ai_proctor} p ON p.userid = u.id
        WHERE p.courseid = ? AND p.imagedata != 'banned'
        GROUP BY u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt
        ORDER BY violations DESC";

$students = $DB->get_records_sql($sql, [$courseid]);

// 5. SETUP PAGE
$PAGE->set_url(new moodle_url('/blocks/ai_proctor/report.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title('Proctor Command Center');
$PAGE->set_heading('🛡️ AI Proctor Command Center');
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', -apple-system, sans-serif !important; background: #f8fafc !important; }
    
    .main-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 32px;
        border-radius: 16px;
        margin-bottom: 32px;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }
    
    .main-header h1 {
        font-size: 36px;
        font-weight: 800;
        margin: 0;
        text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    .main-header p {
        opacity: 0.95;
        margin: 8px 0 0 0;
        font-size: 16px;
    }
    
    .risk-card { 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 16px;
        border-radius: 12px;
        overflow: hidden;
        background: white;
    }
    .risk-card:hover { 
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }
    .risk-high { border-left: 6px solid #ef4444; background: linear-gradient(90deg, #fef2f2 0%, #ffffff 100%); }
    .risk-med  { border-left: 6px solid #f59e0b; background: linear-gradient(90deg, #fffbeb 0%, #ffffff 100%); }
    .risk-low  { border-left: 6px solid #10b981; background: linear-gradient(90deg, #f0fdf4 0%, #ffffff 100%); }
    
    .heat-bar-bg { 
        width: 100%;
        height: 10px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 12px;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }
    .heat-bar-fill { 
        height: 100%;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 10px;
    }
    
    .stat-box { 
        background: white;
        padding: 28px;
        border-radius: 16px;
        text-align: center;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .stat-box:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    .stat-num { 
        font-size: 48px;
        font-weight: 800;
        display: block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
    }
    .stat-label {
        color: #64748b;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .section-header {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 32px 0 20px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .btn-modern {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        border: none;
        text-transform: none;
    }
    .btn-modern-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }
    .btn-modern-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        color: white;
    }
    .btn-modern-danger {
        background: #ef4444;
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    .btn-modern-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        color: white;
    }
</style>

<div class="main-header">
    <h1>🛡️ AI Proctor Command Center</h1>
    <p>Real-time exam monitoring and violation management system</p>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-box">
            <span class="stat-num"><?php echo count($students); ?></span>
            <span class="stat-label">Students Monitored</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-box">
            <?php $high = array_filter($students, function($s){ return $s->violations >= 5; }); ?>
            <span class="stat-num" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo count($high); ?></span>
            <span class="stat-label">High Risk</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-box">
            <?php $totalViolations = array_sum(array_map(function($s){ return $s->violations; }, $students)); ?>
            <span class="stat-num" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $totalViolations; ?></span>
            <span class="stat-label">Total Violations</span>
        </div>
    </div>
</div>

<div class="section-header">
    <span>⚠️</span>
    <span>Risk Leaderboard</span>
</div>

<?php if (empty($students)): ?>
    <div class="alert alert-info">No monitoring data yet. Waiting for students...</div>
<?php endif; ?>

<div class="container-fluid p-0">
    <?php foreach ($students as $student): ?>
        <?php 
            $v = $student->violations;
            $risk = ($v >= 5) ? 'risk-high' : (($v >= 3) ? 'risk-med' : 'risk-low');
            $color = ($v >= 5) ? '#dc3545' : (($v >= 3) ? '#ffc107' : '#28a745');
            $pct = min(100, ($v / 10) * 100);
            
            $userObj = (object)['id'=>$student->id, 'picture'=>$student->picture, 'firstname'=>$student->firstname, 'lastname'=>$student->lastname, 'email'=>$student->email, 'imagealt'=>$student->imagealt];
            $avatar = $OUTPUT->user_picture($userObj, array('size' => 50));
        ?>

        <div class="card risk-card <?php echo $risk; ?>">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4 d-flex align-items-center">
                        <div style="margin-right:15px;"><?php echo $avatar; ?></div>
                        <div>
                            <h5 class="m-0 font-weight-bold"><?php echo fullname($student); ?></h5>
                            <small class="text-muted"><?php echo $student->email; ?></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between">
                            <strong>Violations: <?php echo $v; ?></strong>
                            <small class="text-muted">Last: <?php echo userdate($student->last_violation, '%H:%M'); ?></small>
                        </div>
                        <div class="heat-bar-bg"><div class="heat-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div></div>
                    </div>
                    <div class="col-md-4 text-right">
                        <a href="report_detail.php?courseid=<?php echo $courseid; ?>&userid=<?php echo $student->id; ?>" class="btn btn-sm btn-modern btn-modern-primary" style="margin-right: 8px;">
                            🕵️ View Evidence
                        </a>
                        
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="ban">
                            <input type="hidden" name="target" value="<?php echo $student->id; ?>">
                            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <button type="submit" class="btn btn-sm btn-modern btn-modern-danger" onclick="return confirm('DISQUALIFY STUDENT?\n\nThis will block all future uploads and mark the student for review.\n\nContinue?')">Disqualify</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php echo $OUTPUT->footer(); ?>
