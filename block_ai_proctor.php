<?php
/**
 * AI Proctor Block
 *
 * @package    block_ai_proctor
 * @copyright  2024 Qigen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Block definition for AI Proctor.
 *
 * @package    block_ai_proctor
 * @copyright  2024 Qigen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * AI Proctor block class.
 */
class block_ai_proctor extends block_base {
    
    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_ai_proctor');
    }

    public function specialization() {
        if (isset($this->config->title)) {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('pluginname', 'block_ai_proctor');
        }
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'mod' => false,
            'my' => false
        ];
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $USER, $COURSE, $CFG;

        if ($this->content !== null) { return $this->content; }
        $this->content = new stdClass();

        if ($COURSE->id == 1) { 
            $this->content->text = ''; 
            return $this->content; 
        }

        $context = context_course::instance($COURSE->id);
        $is_teacher = has_capability('moodle/grade:viewall', $context) || is_siteadmin();
        
        $upload_url = $CFG->wwwroot . '/blocks/ai_proctor/upload_image.php';
        $report_url = $CFG->wwwroot . '/blocks/ai_proctor/report.php?courseid=' . $COURSE->id;
        
        $sesskey = sesskey();
        $course_id = $COURSE->id;
        $verified_key = 'ai_proctor_auth_' . $course_id;
        $count_key = 'ai_proctor_count_' . $course_id;

        ob_start();
?>

<!-- Modern Status Header -->
<div id="proctor-status-bar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; color: white;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <div id="status-pulse" style="width: 8px; height: 8px; background: #4ade80; border-radius: 50%; animation: pulse 2s infinite;"></div>
            <span id="header-status" style="font-weight: 600; font-size: 13px;"><?php echo get_string('active', 'block_ai_proctor'); ?></span>
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; backdrop-filter: blur(10px);">
            <span style="font-size: 11px; opacity: 0.9;"><?php echo get_string('strikes', 'block_ai_proctor'); ?>:</span>
            <span id="header-strikes" style="font-weight: 700; font-size: 14px; margin-left: 4px;">0</span><span style="opacity: 0.7;">/5</span>
        </div>
    </div>
</div>

<?php if ($is_teacher): ?>
    <div style="text-align:center;">
        <form action="<?php echo $report_url; ?>" method="get" target="_blank">
            <input type="hidden" name="courseid" value="<?php echo $course_id; ?>">
            <button type="submit" class="btn" style="width:100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)';"><?php echo get_string('command_center', 'block_ai_proctor'); ?></button>
        </form>
    </div>
<?php else: ?>



<div id="click-blocker">
    <div id="click-blocker-msg">⚠️ <?php echo get_string('exam_hidden', 'block_ai_proctor'); ?><br><span style="font-size:16px; font-weight:normal;"><?php echo get_string('face_lost', 'block_ai_proctor'); ?></span></div>
</div>

<!-- Intelligent Warning Overlay -->
<div id="warning-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.97); backdrop-filter:blur(25px); z-index:99995; font-family:'Inter',sans-serif;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; max-width:600px; width:90%;">
        <div style="width:100px; height:100px; background:linear-gradient(135deg, #fbbf24, #f59e0b); border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center; font-size:50px; box-shadow:0 20px 60px rgba(251,191,36,0.4); animation:pulse 2s infinite;">⚠️</div>
        
        <h2 style="color:white; font-size:28px; font-weight:700; margin-bottom:16px;"><?php echo get_string('position_required', 'block_ai_proctor'); ?></h2>
        
        <div id="warning-message" style="color:#cbd5e1; font-size:18px; margin-bottom:32px; line-height:1.6;"></div>
        
        <div id="warning-instructions" style="background:rgba(255,255,255,0.05); padding:24px; border-radius:12px; border:1px solid rgba(255,255,255,0.1); margin-bottom:24px; text-align:left;">
            <div style="color:#fbbf24; font-weight:600; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">📋 How to Fix:</div>
            <ul id="instruction-list" style="color:#94a3b8; font-size:15px; line-height:2; list-style:none; padding:0;">
            </ul>
        </div>
        
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; color:#64748b; font-size:14px;">
            <div style="width:8px; height:8px; background:#fbbf24; border-radius:50%; animation:pulse 2s infinite;"></div>
            <span id="warning-timer"><?php echo get_string('warning_timer', 'block_ai_proctor'); ?></span>
        </div>
        
        <div id="warning-countdown" style="margin-top:20px; font-size:48px; font-weight:700; color:#fbbf24;">8</div>
    </div>
</div>

<div id="ai-shield">
    <div id="shield-icon">🛡️</div>
    <div id="shield-title"><?php echo get_string('shield_title', 'block_ai_proctor'); ?></div>
    <div id="shield-status"><?php echo get_string('shield_status_init', 'block_ai_proctor'); ?></div>
    
    <!-- Progress Bar -->
    <div style="width: 480px; background: rgba(255,255,255,0.1); height: 8px; border-radius: 10px; margin: 16px 0; overflow: hidden;">
        <div id="init-progress" style="width: 0%; height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.5s ease;"></div>
    </div>
    
    <!-- Step Indicators -->
    <div id="init-steps" style="width: 480px; text-align: left; font-size: 13px; color: #94a3b8; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step1-icon" style="margin-right: 8px;">⏳</span>
            <span id="step1-text"><?php echo get_string('step_system', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step2-icon" style="margin-right: 8px;">⏳</span>
            <span id="step2-text"><?php echo get_string('step_camera', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step3-icon" style="margin-right: 8px;">⏳</span>
            <span id="step3-text"><?php echo get_string('step_access', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step4-icon" style="margin-right: 8px;">⏳</span>
            <span id="step4-text"><?php echo get_string('step_model', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step5-icon" style="margin-right: 8px;">⏳</span>
            <span id="step5-text"><?php echo get_string('step_model', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step6-icon" style="margin-right: 8px;">⏳</span>
            <span id="step6-text"><?php echo get_string('step_face', 'block_ai_proctor'); ?></span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step7-icon" style="margin-right: 8px;">⏳</span>
            <span id="step7-text"><?php echo get_string('step_scan', 'block_ai_proctor'); ?></span>
        </div>
    </div>
    
    <div class="big-camera">
        <video id="webcam" autoplay playsinline muted></video>
    </div>
    
    <div class="shield-footer">
        <div class="shield-footer-dot"></div>
        <span id="shield-footer-text"><?php echo get_string('shield_connection', 'block_ai_proctor'); ?></span>
    </div>
</div>

<div id="ai-hud" style="display:none;">
    <div id="hud-header">🛡️ AI PROCTOR ACTIVE</div>
    
    <div id="hud-content">
        <div class="status-grid">
            <div class="status-card">
                <div class="status-label"><?php echo get_string('status', 'block_ai_proctor'); ?></div>
                <div class="status-value" id="debug-mode">NORMAL</div>
            </div>
            <div class="status-card">
                <div class="status-label"><?php echo get_string('risk_level', 'block_ai_proctor'); ?></div>
                <div class="status-value" id="debug-suspicion">0%</div>
            </div>
        </div>
        
        <div id="mini-video-container">
        </div>
        
        <div id="heat-bar-container">
            <div id="heat-bar"></div>
        </div>
        
        <div class="metrics-section">
            <div class="metric-row">
                <span class="metric-label"><?php echo get_string('eye_tracking', 'block_ai_proctor'); ?></span>
                <span class="metric-value" id="val-eye">0.00</span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Head Position</span>
                <span class="metric-value" id="val-pitch">0.00</span>
            </div>
        </div>
        
        <div style="font-size: 10px; opacity: 0.5; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Activity Log</div>
        <ul id="violation-log"></ul>
        
        <div style="text-align:center; color:#64748b; font-size:9px; margin-top:12px; opacity: 0.7;">Optimized • 10 FPS • Encrypted</div>
        <div style="text-align:center; color:#64748b; font-size:8px; margin-top:8px; opacity: 0.5; border-top: 1px solid rgba(255,255,255,0.05); padding-top:8px;"><?php echo get_string('powered_by', 'block_ai_proctor'); ?></div>
    </div>
</div>

<div id="ai-proctor-config" style="display:none;" data-course_id="<?php echo $course_id; ?>" data-sess_key="<?php echo $sesskey; ?>" data-upload_url="<?php echo $upload_url; ?>" data-verified_key="<?php echo $verified_key; ?>" data-count_key="<?php echo $count_key; ?>" data-institution_code="<?php echo get_config('block_ai_proctor', 'institution_code'); ?>" data-analytics_enabled="<?php echo get_config('block_ai_proctor', 'analytics_enabled') ? 'true' : 'false'; ?>" data-user_id="<?php echo $USER->id; ?>"></div>
<script type="module">
import { init } from './amd/src/proctor.js';
init();
</script>

<?php endif; ?>

<?php
        $html = ob_get_contents();
        ob_end_clean();
        $this->content->text = $html;
        return $this->content;
    }

    /**
     * Define cron task execution for the block.
     */
    public function cron() {
        mtrace('Running AI Proctor block cron...');
        return true;
    }
}
