<?php
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
 * @copyright  2026 Your Name
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
            <span id="header-status" style="font-weight: 600; font-size: 13px;">Active</span>
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; backdrop-filter: blur(10px);">
            <span style="font-size: 11px; opacity: 0.9;">Strikes:</span>
            <span id="header-strikes" style="font-weight: 700; font-size: 14px; margin-left: 4px;">0</span><span style="opacity: 0.7;">/5</span>
        </div>
    </div>
</div>

<?php if ($is_teacher): ?>
    <div style="text-align:center;">
        <form action="<?php echo $report_url; ?>" method="get" target="_blank">
            <input type="hidden" name="courseid" value="<?php echo $course_id; ?>">
            <button type="submit" class="btn" style="width:100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)';">📊 Command Center</button>
        </form>
    </div>
<?php else: ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
    }
    
    @keyframes slideInUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }

    #click-blocker {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(20px);
        z-index: 99990; display: none; cursor: not-allowed;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    #click-blocker-msg {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white; padding: 40px 50px; 
        font-weight: 700; font-size: 28px; text-align: center;
        border-radius: 20px; 
        box-shadow: 0 25px 50px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1);
        font-family: 'Inter', sans-serif;
        animation: slideInUp 0.4s ease;
    }
    
    #click-blocker-msg span {
        display: block;
        font-size: 16px; 
        font-weight: 400; 
        margin-top: 12px;
        opacity: 0.95;
    }

    #ai-hud { 
        position: fixed; top: 20px; right: 20px; width: 320px; 
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(20px);
        color: white; border-radius: 16px; 
        z-index: 99999; padding: 0; 
        border: 1px solid rgba(255,255,255,0.1); 
        font-family: 'Inter', sans-serif;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        animation: slideInUp 0.5s ease;
        overflow: hidden;
    }
    
    #hud-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 16px;
        text-align: center;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    #hud-content {
        padding: 16px;
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .status-card {
        background: rgba(255,255,255,0.05);
        padding: 12px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .status-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.6;
        margin-bottom: 4px;
    }
    
    .status-value {
        font-size: 20px;
        font-weight: 700;
        color: #4ade80;
    }
    
    #heat-bar-container {
        background: rgba(255,255,255,0.1);
        height: 8px;
        border-radius: 10px;
        overflow: hidden;
        margin: 16px 0;
        position: relative;
    }
    
    #heat-bar { 
        height: 100%; 
        width: 0%; 
        background: linear-gradient(90deg, #4ade80, #22c55e);
        transition: width 0.3s ease, background 0.3s ease;
        border-radius: 10px;
        position: relative;
    }
    
    #heat-bar.warning {
        background: linear-gradient(90deg, #fbbf24, #f59e0b);
    }
    
    #heat-bar.danger {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }
    
    .metrics-section {
        background: rgba(255,255,255,0.03);
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 12px;
    }
    
    .metric-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 11px;
    }
    
    .metric-label {
        opacity: 0.7;
    }
    
    .metric-value {
        font-weight: 600;
        font-family: 'Courier New', monospace;
        color: #a78bfa;
    }
    
    #violation-log {
        height: 80px; 
        overflow-y: auto; 
        background: rgba(0,0,0,0.3);
        margin-top: 12px; 
        padding: 8px; 
        border-radius: 8px; 
        list-style: none;
        border: 1px solid rgba(255,255,255,0.05);
    }
    
    #violation-log::-webkit-scrollbar {
        width: 4px;
    }
    
    #violation-log::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 4px;
    }
    
    #violation-log::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.2);
        border-radius: 4px;
    }
    
    #violation-log li { 
        padding: 6px 8px;
        margin-bottom: 4px;
        background: rgba(239, 68, 68, 0.1);
        border-left: 3px solid #ef4444;
        border-radius: 4px;
        font-size: 10px;
        animation: slideInUp 0.3s ease;
    }
    
    .log-time {
        color: #a78bfa;
        font-weight: 600;
        margin-right: 8px;
    }
    
    .log-msg {
        color: #fca5a5;
    }
    
    #mini-video-container {
        height: 140px;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid rgba(255,255,255,0.1);
        position: relative;
    }
    
    #mini-video-container::after {
        content: 'LIVE';
        position: absolute;
        top: 8px;
        left: 8px;
        background: #ef4444;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.5px;
        z-index: 3;
    }



    #ai-shield { 
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        z-index: 999999; display: flex; flex-direction: column; 
        align-items: center; justify-content: center; 
        font-family: 'Inter', sans-serif;
    }
    
    #shield-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        margin-bottom: 24px;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
        animation: pulse 2s infinite;
    }
    
    #shield-title {
        font-size: 32px;
        font-weight: 700;
        color: white;
        margin-bottom: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    #shield-status {
        color: #94a3b8;
        font-size: 16px;
        margin-bottom: 32px;
        font-weight: 500;
    }
    
    .big-camera { 
        width: 480px; 
        height: 360px; 
        margin: 0 auto; 
        background: #000; 
        border-radius: 16px; 
        border: 3px solid rgba(255,255,255,0.1); 
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        position: relative;
    }
    
    .big-camera::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        pointer-events: none;
    }
    
    video { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        transform: scaleX(-1); 
    }
    
    .shield-footer {
        margin-top: 24px;
        display: flex;
        gap: 8px;
        align-items: center;
        color: #64748b;
        font-size: 12px;
    }
    
    .shield-footer-dot {
        width: 6px;
        height: 6px;
        background: #4ade80;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
</style>

<div id="click-blocker">
    <div id="click-blocker-msg">⚠️ EXAM HIDDEN<br><span style="font-size:16px; font-weight:normal;">Face Lost. Return to continue.</span></div>
</div>

<!-- Intelligent Warning Overlay -->
<div id="warning-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.97); backdrop-filter:blur(25px); z-index:99995; font-family:'Inter',sans-serif;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; max-width:600px; width:90%;">
        <div style="width:100px; height:100px; background:linear-gradient(135deg, #fbbf24, #f59e0b); border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center; font-size:50px; box-shadow:0 20px 60px rgba(251,191,36,0.4); animation:pulse 2s infinite;">⚠️</div>
        
        <h2 style="color:white; font-size:28px; font-weight:700; margin-bottom:16px;">Position Adjustment Required</h2>
        
        <div id="warning-message" style="color:#cbd5e1; font-size:18px; margin-bottom:32px; line-height:1.6;"></div>
        
        <div id="warning-instructions" style="background:rgba(255,255,255,0.05); padding:24px; border-radius:12px; border:1px solid rgba(255,255,255,0.1); margin-bottom:24px; text-align:left;">
            <div style="color:#fbbf24; font-weight:600; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">📋 How to Fix:</div>
            <ul id="instruction-list" style="color:#94a3b8; font-size:15px; line-height:2; list-style:none; padding:0;">
            </ul>
        </div>
        
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; color:#64748b; font-size:14px;">
            <div style="width:8px; height:8px; background:#fbbf24; border-radius:50%; animation:pulse 2s infinite;"></div>
            <span id="warning-timer">You have 8 seconds to adjust your position</span>
        </div>
        
        <div id="warning-countdown" style="margin-top:20px; font-size:48px; font-weight:700; color:#fbbf24;">8</div>
    </div>
</div>

<div id="ai-shield">
    <div id="shield-icon">🛡️</div>
    <div id="shield-title">AI Proctor System</div>
    <div id="shield-status">Initializing secure monitoring...</div>
    
    <!-- Progress Bar -->
    <div style="width: 480px; background: rgba(255,255,255,0.1); height: 8px; border-radius: 10px; margin: 16px 0; overflow: hidden;">
        <div id="init-progress" style="width: 0%; height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.5s ease;"></div>
    </div>
    
    <!-- Step Indicators -->
    <div id="init-steps" style="width: 480px; text-align: left; font-size: 13px; color: #94a3b8; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step1-icon" style="margin-right: 8px;">⏳</span>
            <span id="step1-text">Checking system requirements...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step2-icon" style="margin-right: 8px;">⏳</span>
            <span id="step2-text">Checking camera support...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step3-icon" style="margin-right: 8px;">⏳</span>
            <span id="step3-text">Requesting camera access...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step4-icon" style="margin-right: 8px;">⏳</span>
            <span id="step4-text">Loading AI model...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step5-icon" style="margin-right: 8px;">⏳</span>
            <span id="step5-text">Loading AI model...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step6-icon" style="margin-right: 8px;">⏳</span>
            <span id="step6-text">Initializing face detection...</span>
        </div>
        <div style="display: flex; align-items: center; padding: 6px 0;">
            <span id="step7-icon" style="margin-right: 8px;">⏳</span>
            <span id="step7-text">Environment scan required...</span>
        </div>
    </div>
    
    <div class="big-camera">
        <video id="webcam" autoplay playsinline muted></video>
    </div>
    
    <div class="shield-footer">
        <div class="shield-footer-dot"></div>
        <span id="shield-footer-text">Secure Connection Established</span>
    </div>
</div>

<div id="ai-hud" style="display:none;">
    <div id="hud-header">🛡️ AI PROCTOR ACTIVE</div>
    
    <div id="hud-content">
        <div class="status-grid">
            <div class="status-card">
                <div class="status-label">Status</div>
                <div class="status-value" id="debug-mode">NORMAL</div>
            </div>
            <div class="status-card">
                <div class="status-label">Risk Level</div>
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
                <span class="metric-label">Eye Tracking</span>
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
        <div style="text-align:center; color:#64748b; font-size:8px; margin-top:8px; opacity: 0.5; border-top: 1px solid rgba(255,255,255,0.05); padding-top:8px;">Powered by Medwax Corporation Africa Ltd.</div>
    </div>
</div>

<script type="module">
    import { FilesetResolver, FaceLandmarker } from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3";

    const CONFIG = {
        course_id: <?php echo $course_id; ?>,
        sess_key: "<?php echo $sesskey; ?>",
        upload_url: "<?php echo $upload_url; ?>",
        verified_key: "<?php echo $verified_key; ?>",
        count_key: "<?php echo $count_key; ?>",
        asset_path: "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
        wasm_path: "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm",
        
        // Commercial licensing & analytics
        institution_code: "<?php echo get_config('block_ai_proctor', 'institution_code'); ?>",
        analytics_enabled: <?php echo get_config('block_ai_proctor', 'analytics_enabled') ? 'true' : 'false'; ?>,
        session_id: Date.now().toString(36) + Math.random().toString(36).substr(2, 5),
        license_server: "https://analytics.medwax.com/api/v1/"
    };

    // Commercial Usage Tracking
    class AIProctorAnalytics {
        constructor() {
            this.sessionStart = Date.now();
            this.violationCount = 0;
            this.trackingData = {
                session_id: CONFIG.session_id,
                course_id: CONFIG.course_id,
                user_hash: this.hashUserId(<?php echo $USER->id; ?>),
                institution_code: CONFIG.institution_code,
                start_time: this.sessionStart,
                violations: [],
                system_info: this.getSystemInfo()
            };
            
            if (CONFIG.analytics_enabled) {
                this.initTracking();
            }
        }
        
        hashUserId(userId) {
            // Simple hash for privacy - use stronger hash in production
            return btoa(userId.toString() + CONFIG.sess_key).substr(0, 16);
        }
        
        getSystemInfo() {
            return {
                user_agent: navigator.userAgent,
                screen_resolution: screen.width + 'x' + screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language,
                platform: navigator.platform
            };
        }
        
        initTracking() {
            // Track session start
            this.sendAnalyticsEvent('session_start', {
                timestamp: this.sessionStart,
                exam_type: 'standard'
            });
            
            // Track session end on page unload
            window.addEventListener('beforeunload', () => {
                this.endSession();
            });
            
            // Periodic heartbeat every 60 seconds
            setInterval(() => {
                this.sendHeartbeat();
            }, 60000);
        }
        
        trackViolation(type, details) {
            if (!CONFIG.analytics_enabled) return;
            
            this.violationCount++;
            const violation = {
                type: type,
                timestamp: Date.now(),
                details: details,
                session_time: Date.now() - this.sessionStart
            };
            
            this.trackingData.violations.push(violation);
            
            this.sendAnalyticsEvent('violation_detected', {
                violation_type: type,
                violation_details: details,
                total_violations: this.violationCount
            });
        }
        
        endSession() {
            if (!CONFIG.analytics_enabled) return;
            
            const sessionData = {
                ...this.trackingData,
                end_time: Date.now(),
                duration: Date.now() - this.sessionStart,
                total_violations: this.violationCount
            };
            
            // Send final session data
            this.sendAnalyticsEvent('session_end', sessionData, true); // Synchronous
            
            // Also store locally for backup
            this.storeSessionLocally(sessionData);
        }
        
        sendAnalyticsEvent(eventType, data, synchronous = false) {
            const payload = {
                institution_code: CONFIG.institution_code,
                session_id: CONFIG.session_id,
                event_type: eventType,
                timestamp: Date.now(),
                data: data
            };
            
            const options = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': CONFIG.sess_key
                },
                body: JSON.stringify(payload)
            };
            
            if (synchronous && navigator.sendBeacon) {
                // Use sendBeacon for reliable delivery on page unload
                navigator.sendBeacon(
                    CONFIG.license_server + 'analytics/event',
                    JSON.stringify(payload)
                );
            } else {
                // Regular async request
                fetch(CONFIG.license_server + 'analytics/event', options)
                    .catch(error => {
                        console.warn('Analytics error:', error);
                        // Queue for retry
                        this.queueForRetry(payload);
                    });
            }
        }
        
        sendHeartbeat() {
            this.sendAnalyticsEvent('heartbeat', {
                session_duration: Date.now() - this.sessionStart,
                current_violations: this.violationCount,
                is_active: !document.hidden
            });
        }
        
        queueForRetry(payload) {
            const retryQueue = JSON.parse(localStorage.getItem('ai_proctor_retry_queue') || '[]');
            retryQueue.push(payload);
            localStorage.setItem('ai_proctor_retry_queue', JSON.stringify(retryQueue));
        }
        
        storeSessionLocally(sessionData) {
            const localSessions = JSON.parse(localStorage.getItem('ai_proctor_sessions') || '[]');
            localSessions.push(sessionData);
            
            // Keep only last 10 sessions locally
            if (localSessions.length > 10) {
                localSessions.splice(0, localSessions.length - 10);
            }
            
            localStorage.setItem('ai_proctor_sessions', JSON.stringify(localSessions));
        }
    }
    
    // Initialize analytics
    const analytics = new AIProctorAnalytics();

    const evidenceCanvas = document.createElement('canvas');
    evidenceCanvas.width = 320; evidenceCanvas.height = 240;
    const evidenceCtx = evidenceCanvas.getContext('2d');
    
    let faceLandmarker = null;
    let video = document.getElementById("webcam");
    let isExamRunning = false;
    let violationTotal = parseInt(sessionStorage.getItem(CONFIG.count_key) || "0");
    let isStrictMode = (violationTotal >= 5);
    
    let suspicionMeter = 0;
    const MAX_METER = 10; 
    const HEAT_RATE = 2.0;
    const COOL_RATE = 0.05;
    
    let lastLoopTime = 0;
    let watchdogTimer = null;
    let lastProcessTime = 0;
    const FRAME_INTERVAL = 100; // 10 FPS

    // INTELLIGENT WARNING SYSTEM
    let warningActive = false;
    let lastWarningTime = 0;
    let warningCooldown = 15000; // 15 seconds between warnings
    let currentWarningType = null;
    let warningStartTime = 0;
    let warningGracePeriod = 8000; // 8 seconds to correct behavior
    let videoRecorder = null;
    let recordedChunks = [];
    let evidenceUploadCooldown = 30000; // 30 seconds minimum between evidence uploads
    let lastEvidenceUpload = 0;
    
    // ENVIRONMENT SCANNING SYSTEM
    let environmentScanRequired = true;
    let scanProgress = 0;
    let scanStartTime = 0;
    let lastHeadPosition = 0;
    let scanDirection = 1; // 1 for right, -1 for left
    let scanCompleted = false;
    const SCAN_DURATION = 15000; // 15 seconds to complete scan
    const SCAN_THRESHOLD = 0.8; // 80% completion required
    
    // PERMANENT BAN SYSTEM
    let isBanned = false;
    let banReason = '';
    const MAX_VIOLATIONS = 10; // Permanent ban after 10 violations
    const BAN_KEY = 'ai_proctor_banned_' + CONFIG.course_id;

    // --- BALANCED LIMITS ---
    const LIMITS = { 
        HEAD_LEFT: 0.40, HEAD_RIGHT: 0.60, HEAD_UP: 0.35, 
        MOUTH_OPEN: 0.05,
        
        // COMPLEX LOOK DOWN:
        EYE_DOWN_THRESHOLD: 0.40, // Eyes must be heavily down
        PITCH_DOWN_THRESHOLD: 0.55 // Head must actually tilt (Nose position)
    };

    // UI Init
    document.getElementById("header-strikes").innerText = violationTotal;
    (function(){
        if (sessionStorage.getItem(CONFIG.verified_key) === 'true') {
            document.getElementById("ai-shield").style.display = 'none';
            document.getElementById("ai-hud").style.display = 'block';
            isExamRunning = true;
        }
    })();



    function updateStep(num, icon, text, progress) {
        document.getElementById(`step${num}-icon`).innerText = icon;
        document.getElementById(`step${num}-text`).innerText = text;
        document.getElementById("init-progress").style.width = progress + "%";
    }

    // SYSTEM REQUIREMENTS CHECKER
    async function checkSystemRequirements() {
        const results = {
            hasWarnings: false,
            issues: [],
            recommendations: [],
            summary: "System optimal",
            scores: {},
            isSecure: false
        };

        try {
            // 1. LOCKDOWN BROWSER DETECTION (CRITICAL)
            const lockdownStatus = detectLockdownBrowser();
            results.scores.lockdown = lockdownStatus;
            
            if (!lockdownStatus.isDetected) {
                results.hasWarnings = true;
                results.issues.push("🚨 SECURITY RISK: Not running in a lockdown browser");
                results.recommendations.push("Install and use Safe Exam Browser (SEB) or equivalent lockdown browser");
                results.recommendations.push("Contact your institution's IT department for lockdown browser setup");
            } else {
                results.isSecure = true;
                results.summary = `Secure environment: ${lockdownStatus.type}`;
            }

            // 2. KIOSK MODE VERIFICATION
            if (!document.fullscreenElement && !window.navigator.standalone) {
                if (!lockdownStatus.isDetected) {
                    results.hasWarnings = true;
                    results.issues.push("Browser not in full-screen kiosk mode");
                    results.recommendations.push("Enable full-screen mode (F11) for secure exam environment");
                }
            }

            // 3. CPU PERFORMANCE TEST
            const cpuStart = performance.now();
            let iterations = 0;
            const targetTime = 100; // Run for 100ms
            
            while (performance.now() - cpuStart < targetTime) {
                Math.sqrt(Math.random() * 1000);
                iterations++;
            }
            
            const cpuScore = iterations / 1000; // Normalize score
            results.scores.cpu = cpuScore;
            
            if (cpuScore < 30) { // Reduced threshold for lockdown browsers
                results.hasWarnings = true;
                results.issues.push("Slow CPU detected - may cause frame drops in lockdown environment");
                results.recommendations.push("Close unnecessary background processes before starting exam");
            }

            // 4. MEMORY CHECK (Lockdown browsers need more RAM)
            if (navigator.deviceMemory) {
                const ramGB = navigator.deviceMemory;
                results.scores.ram = ramGB;
                
                const minRAM = lockdownStatus.isDetected ? 6 : 4; // Higher requirement for lockdown browsers
                if (ramGB < minRAM) {
                    results.hasWarnings = true;
                    results.issues.push(`Low RAM (${ramGB}GB) - minimum ${minRAM}GB recommended for secure exam environment`);
                    results.recommendations.push("Close all other applications before starting the exam");
                    results.recommendations.push("Restart the lockdown browser if performance is poor");
                }
            }

            // 5. GPU/WebGL CHECK
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            
            if (!gl) {
                results.hasWarnings = true;
                results.issues.push("WebGL not supported - AI processing will be slower");
                results.recommendations.push("Ensure lockdown browser supports hardware acceleration");
            } else {
                const renderer = gl.getParameter(gl.RENDERER);
                results.scores.gpu = renderer;
                
                if (renderer.includes('Software') || renderer.includes('Microsoft')) {
                    results.hasWarnings = true;
                    results.issues.push("Software GPU rendering - poor performance in secure environment");
                    results.recommendations.push("Enable hardware acceleration in lockdown browser settings");
                }
            }

            // 6. NETWORK RESTRICTIONS CHECK
            const netStart = performance.now();
            try {
                await fetch('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', { cache: 'no-cache' });
                const netLatency = performance.now() - netStart;
                results.scores.network = netLatency;
                
                if (netLatency > 150) { // Higher tolerance for lockdown browsers
                    results.hasWarnings = true;
                    results.issues.push("High network latency - may affect AI model loading");
                    results.recommendations.push("Ensure stable internet connection for lockdown browser");
                }
            } catch (e) {
                results.hasWarnings = true;
                results.issues.push("Network connectivity issues in secure environment");
                results.recommendations.push("Check lockdown browser network permissions");
            }

            // 7. STORAGE SPACE CHECK (lockdown browsers cache more data)
            if ('storage' in navigator && 'estimate' in navigator.storage) {
                const storage = await navigator.storage.estimate();
                const freeSpaceGB = (storage.quota - storage.usage) / (1024 * 1024 * 1024);
                results.scores.storage = freeSpaceGB;
                
                const minStorage = lockdownStatus.isDetected ? 2 : 1; // Higher requirement
                if (freeSpaceGB < minStorage) {
                    results.hasWarnings = true;
                    results.issues.push(`Low storage (${freeSpaceGB.toFixed(1)}GB) - lockdown browsers need more cache space`);
                    results.recommendations.push("Clear lockdown browser cache and temporary files");
                }
            }

            // 8. BROWSER COMPATIBILITY FOR LOCKDOWN
            const userAgent = navigator.userAgent.toLowerCase();
            if (lockdownStatus.isDetected) {
                // Check SEB version compatibility
                if (lockdownStatus.type.includes('SEB') && lockdownStatus.version) {
                    const version = parseFloat(lockdownStatus.version);
                    if (version < 3.0) {
                        results.hasWarnings = true;
                        results.issues.push(`Outdated Safe Exam Browser v${lockdownStatus.version}`);
                        results.recommendations.push("Update to Safe Exam Browser 3.0+ for optimal compatibility");
                    }
                }
            }

            // GENERATE SUMMARY
            if (!results.hasWarnings && results.isSecure) {
                results.summary = "Secure lockdown environment - optimal for exams";
            } else if (results.isSecure && results.issues.length <= 2) {
                results.summary = "Secure environment with minor performance issues";
            } else if (!results.isSecure) {
                results.summary = "⚠️ SECURITY WARNING: Insecure environment detected";
            } else {
                results.summary = "Performance optimization needed for secure exams";
            }

            return results;

        } catch (error) {
            return {
                hasWarnings: true,
                issues: ["System analysis failed - proceed with caution"],
                recommendations: ["Ensure you're using a proper lockdown browser"],
                summary: "System check incomplete",
                scores: {},
                isSecure: false
            };
        }
    }

    async function performEnvironmentScan() {
        return new Promise((resolve, reject) => {
            environmentScanRequired = true;
            scanProgress = 0;
            scanStartTime = Date.now();
            lastHeadPosition = 0.5; // Start from center
            scanDirection = 1;
            scanCompleted = false;
            
            // Show scan overlay
            const scanOverlay = document.createElement('div');
            scanOverlay.id = 'environment-scan-overlay';
            scanOverlay.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);
                z-index: 999998; font-family: 'Inter', sans-serif;
                display: flex; align-items: center; justify-content: center;
            `;
            
            scanOverlay.innerHTML = `
                <div style="text-align: center; color: white; max-width: 600px; padding: 40px;">
                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                         border-radius: 50%; margin: 0 auto 32px; display: flex; align-items: center;
                         justify-content: center; font-size: 60px; animation: pulse 2s infinite;">🔍</div>
                    
                    <h2 style="font-size: 28px; font-weight: 700; margin-bottom: 16px;">Environment Scan Required</h2>
                    
                    <p style="color: #cbd5e1; font-size: 18px; margin-bottom: 32px; line-height: 1.6;">
                        Turn slowly clockwise 360° to show your exam environment is secure
                    </p>
                    
                    <div style="background: rgba(59,130,246,0.1); padding: 24px; border-radius: 12px; margin-bottom: 24px;">
                        <div style="color: #3b82f6; font-weight: 600; margin-bottom: 12px;">📋 Requirements:</div>
                        <ul style="text-align: left; color: #94a3b8; font-size: 15px; line-height: 1.8;">
                            <li>Turn slowly - complete 360° in 15 seconds</li>
                            <li>Keep your face visible at all times</li>
                            <li>Show desk area, walls, and behind you</li>
                            <li>No unauthorized materials or people visible</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin-bottom: 24px;">
                        <div style="color: #cbd5e1; font-size: 14px; margin-bottom: 8px;">Scan Progress:</div>
                        <div style="background: rgba(255,255,255,0.1); height: 12px; border-radius: 10px; overflow: hidden;">
                            <div id="scan-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #3b82f6, #1d4ed8);
                                 transition: width 0.3s ease; border-radius: 10px;"></div>
                        </div>
                        <div style="color: #3b82f6; font-size: 24px; font-weight: 700; margin-top: 12px;" id="scan-percentage">0%</div>
                    </div>
                    
                    <div style="color: #64748b; font-size: 13px; opacity: 0.8;">
                        ⏱️ <span id="scan-timer">15</span> seconds remaining
                    </div>
                </div>
            `;
            
            document.body.appendChild(scanOverlay);
            
            // Start scan monitoring
            const scanInterval = setInterval(() => {
                if (!faceLandmarker || !video) return;
                
                try {
                    const result = faceLandmarker.detectForVideo(video, performance.now());
                    if (result.faceLandmarks.length > 0) {
                        const noseX = result.faceLandmarks[0][1].x;
                        updateScanProgress(noseX);
                    }
                } catch (e) {}
                
                const elapsed = Date.now() - scanStartTime;
                const remaining = Math.max(0, SCAN_DURATION - elapsed);
                const timerEl = document.getElementById('scan-timer');
                if (timerEl) timerEl.innerText = Math.ceil(remaining / 1000);
                
                if (elapsed >= SCAN_DURATION || scanCompleted) {
                    clearInterval(scanInterval);
                    document.body.removeChild(scanOverlay);
                    
                    if (scanProgress >= SCAN_THRESHOLD) {
                        environmentScanRequired = false;
                        resolve();
                    } else {
                        reject(new Error('SCAN_INCOMPLETE: Environment scan incomplete. Please try again.'));
                    }
                }
            }, 100);
        });
    }
    
    function updateScanProgress(noseX) {
        // Calculate scan progress based on head movement
        const movement = Math.abs(noseX - lastHeadPosition);
        if (movement > 0.05) { // Significant head movement detected
            scanProgress += movement * 2; // Progress based on movement amount
            scanProgress = Math.min(1, scanProgress);
            
            // Update UI
            const progressBar = document.getElementById('scan-progress-bar');
            const percentage = document.getElementById('scan-percentage');
            if (progressBar) progressBar.style.width = (scanProgress * 100) + '%';
            if (percentage) percentage.innerText = Math.floor(scanProgress * 100) + '%';
            
            lastHeadPosition = noseX;
            
            if (scanProgress >= SCAN_THRESHOLD) {
                scanCompleted = true;
            }
        }
    }
    
    function showPermanentBan() {
        const banOverlay = document.createElement('div');
        banOverlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: linear-gradient(135deg, #7f1d1d 0%, #450a0a 100%);
            z-index: 999999; font-family: 'Inter', sans-serif;
            display: flex; align-items: center; justify-content: center;
        `;
        
        const banReason = sessionStorage.getItem(BAN_KEY + '_reason') || 'Multiple security violations';
        const banTime = sessionStorage.getItem(BAN_KEY + '_time') || new Date().toLocaleString();
        
        banOverlay.innerHTML = `
            <div style="text-align: center; color: white; max-width: 500px; padding: 40px;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #dc2626, #991b1b);
                     border-radius: 50%; margin: 0 auto 32px; display: flex; align-items: center;
                     justify-content: center; font-size: 60px; animation: pulse 2s infinite;">🚫</div>
                
                <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 16px; color: #fca5a5;">EXAM ACCESS DENIED</h1>
                
                <p style="color: #fecaca; font-size: 18px; margin-bottom: 24px; line-height: 1.6;">
                    You have been permanently banned from this exam due to security violations.
                </p>
                
                <div style="background: rgba(0,0,0,0.3); padding: 24px; border-radius: 12px; margin-bottom: 32px;
                     border-left: 4px solid #dc2626;">
                    <div style="color: #dc2626; font-weight: 600; margin-bottom: 12px; font-size: 16px;">Ban Details:</div>
                    <div style="color: #fca5a5; font-size: 14px; margin-bottom: 8px;">Reason: ${banReason}</div>
                    <div style="color: #fca5a5; font-size: 14px; margin-bottom: 8px;">Time: ${banTime}</div>
                    <div style="color: #fca5a5; font-size: 14px;">Status: Permanent</div>
                </div>
                
                <div style="background: rgba(239,68,68,0.1); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <div style="color: #ef4444; font-weight: 600; margin-bottom: 12px;">📞 Next Steps:</div>
                    <ul style="text-align: left; color: #fca5a5; font-size: 14px; line-height: 1.8;">
                        <li>Contact your instructor immediately</li>
                        <li>Report to academic affairs office</li>
                        <li>Provide documentation of technical issues (if applicable)</li>
                        <li>Request alternative exam arrangement if eligible</li>
                    </ul>
                </div>
                
                <div style="color: #94a3b8; font-size: 12px; opacity: 0.8; margin-top: 20px;">
                    This action has been logged and reported to course instructors.<br>
                    Session ID: ${Date.now().toString(36).toUpperCase()}
                </div>
            </div>
        `;
        
        document.body.appendChild(banOverlay);
        
        // Hide other elements
        document.getElementById('ai-shield').style.display = 'none';
    }

    function detectLockdownBrowser() {
        const userAgent = navigator.userAgent;
        const result = {
            isDetected: false,
            type: 'Unknown',
            version: null,
            features: []
        };

        // Safe Exam Browser Detection
        if (userAgent.includes('SEB/')) {
            const sebMatch = userAgent.match(/SEB\/(\d+\.\d+)/);
            result.isDetected = true;
            result.type = 'Safe Exam Browser (SEB)';
            result.version = sebMatch ? sebMatch[1] : null;
            result.features.push('Kiosk Mode', 'Application Blocking', 'Network Filtering');
        }
        // Respondus LockDown Browser
        else if (userAgent.includes('RLDBrowser') || userAgent.includes('LockDown')) {
            result.isDetected = true;
            result.type = 'Respondus LockDown Browser';
            result.features.push('Screen Recording Block', 'Application Lock', 'Network Control');
        }
        // ExamSoft Examplify
        else if (userAgent.includes('Examplify') || userAgent.includes('ExamSoft')) {
            result.isDetected = true;
            result.type = 'ExamSoft Examplify';
            result.features.push('Secure Assessment', 'Application Control', 'Offline Capability');
        }
        // Proctorio Extension Check
        else if (window.proctorio || document.querySelector('[data-proctorio]')) {
            result.isDetected = true;
            result.type = 'Proctorio-Enhanced Browser';
            result.features.push('Extension-Based Security', 'Screen Monitoring', 'Browser Lock');
        }
        // TestNav
        else if (userAgent.includes('TestNav') || userAgent.includes('Pearson')) {
            result.isDetected = true;
            result.type = 'Pearson TestNav';
            result.features.push('Secure Testing', 'Application Block', 'System Lock');
        }
        // Generic Kiosk Mode Detection
        else if (
            window.navigator.standalone || // iOS standalone mode
            document.fullscreenElement || // Fullscreen mode
            (screen.availHeight === screen.height && screen.availWidth === screen.width) // Possible kiosk
        ) {
            result.isDetected = true;
            result.type = 'Kiosk/Fullscreen Mode';
            result.features.push('Full Screen', 'Limited Navigation');
        }

        // Additional security feature detection
        try {
            // Test if dev tools are blocked (common in lockdown browsers)
            if (typeof DevToolsChecker !== 'undefined' || window.devtools?.isOpen === false) {
                result.features.push('Developer Tools Blocked');
            }
            
            // Test if printing is disabled
            if (!window.print || window.print.toString().includes('[native code]') === false) {
                result.features.push('Print Disabled');
            }
            
            // Test if copy/paste is restricted
            if (document.oncopy === null || document.onpaste === null) {
                result.features.push('Clipboard Restricted');
            }
        } catch (e) {
            // Security restrictions prevented testing - good sign for lockdown browser
            result.features.push('Enhanced Security Restrictions');
        }

        return result;
    }

    async function showSystemWarning(sysCheck) {
        return new Promise((resolve) => {
            // Create warning overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
                background: rgba(15,23,42,0.98); backdrop-filter: blur(25px); 
                z-index: 999999; font-family: 'Inter', sans-serif;
                display: flex; align-items: center; justify-content: center;
            `;

            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                border-radius: 16px; padding: 32px; max-width: 700px; width: 90%;
                border: 1px solid rgba(255,255,255,0.1);
                box-shadow: 0 25px 50px rgba(0,0,0,0.5);
                color: white; text-align: center;
            `;

            const isSecurityIssue = !sysCheck.isSecure;
            const iconColor = isSecurityIssue ? '#ef4444' : '#f59e0b';
            const icon = isSecurityIssue ? '🚨' : '⚠️';
            const title = isSecurityIssue ? 'Security Warning' : 'Performance Warning';

            let issuesList = sysCheck.issues.map((issue, i) => 
                `<div style="display:flex; align-items:center; padding:8px 0; text-align:left;">
                    <span style="color:${isSecurityIssue ? '#ef4444' : '#f59e0b'}; margin-right:8px;">${isSecurityIssue ? '🚨' : '⚠️'}</span>
                    <span>${issue}</span>
                </div>`
            ).join('');

            let recommendationsList = sysCheck.recommendations.map((rec, i) => 
                `<div style="display:flex; align-items:center; padding:6px 0; text-align:left;">
                    <span style="color:#3b82f6; margin-right:8px;">💡</span>
                    <span style="font-size:14px;">${rec}</span>
                </div>`
            ).join('');

            // Add lockdown browser info if detected
            let lockdownInfo = '';
            if (sysCheck.scores.lockdown && sysCheck.scores.lockdown.isDetected) {
                const features = sysCheck.scores.lockdown.features.join(', ');
                lockdownInfo = `
                    <div style="background:rgba(34,197,94,0.1); padding:16px; border-radius:12px; 
                         margin-bottom:24px; text-align:left; border:1px solid rgba(34,197,94,0.2);">
                        <div style="color:#22c55e; font-weight:600; margin-bottom:8px; font-size:14px;">
                            ✅ SECURE ENVIRONMENT DETECTED:
                        </div>
                        <div style="color:#cbd5e1; font-size:13px; margin-bottom:4px;">
                            <strong>Browser:</strong> ${sysCheck.scores.lockdown.type}
                        </div>
                        ${sysCheck.scores.lockdown.version ? `<div style="color:#cbd5e1; font-size:13px; margin-bottom:4px;">
                            <strong>Version:</strong> ${sysCheck.scores.lockdown.version}
                        </div>` : ''}
                        <div style="color:#cbd5e1; font-size:13px;">
                            <strong>Security Features:</strong> ${features}
                        </div>
                    </div>
                `;
            }

            const warningText = isSecurityIssue 
                ? "Your current browser environment is NOT secure for exams. You must use a lockdown browser."
                : "Your device may not run optimally in the secure exam environment.";

            const continueText = isSecurityIssue ? "⚠️ UNSAFE - Continue Anyway" : "Continue Anyway";
            const continueColor = isSecurityIssue ? "rgba(239,68,68,0.8)" : "linear-gradient(135deg, #059669, #047857)";

            dialog.innerHTML = `
                <div style="width:80px; height:80px; background:linear-gradient(135deg, ${iconColor}, ${iconColor}CC); 
                     border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; 
                     justify-content:center; font-size:40px;">${icon}</div>
                
                <h2 style="font-size:24px; font-weight:700; margin-bottom:12px; color:${iconColor};">
                    ${title}
                </h2>
                
                <p style="color:#cbd5e1; font-size:16px; margin-bottom:24px; line-height:1.5;">
                    ${warningText}
                </p>
                
                ${lockdownInfo}
                
                <div style="background:rgba(255,255,255,0.05); padding:20px; border-radius:12px; 
                     margin-bottom:24px; text-align:left;">
                    <div style="color:${iconColor}; font-weight:600; margin-bottom:12px; font-size:14px;">
                        🔍 DETECTED ISSUES:
                    </div>
                    ${issuesList}
                </div>
                
                <div style="background:rgba(59,130,246,0.1); padding:20px; border-radius:12px; 
                     margin-bottom:32px; text-align:left;">
                    <div style="color:#3b82f6; font-weight:600; margin-bottom:12px; font-size:14px;">
                        💡 RECOMMENDATIONS:
                    </div>
                    ${recommendationsList}
                    ${!sysCheck.isSecure ? `
                        <div style="background:rgba(239,68,68,0.1); padding:12px; border-radius:8px; 
                             margin-top:12px; border-left:3px solid #ef4444;">
                            <div style="color:#ef4444; font-weight:600; font-size:13px; margin-bottom:6px;">
                                🚨 CRITICAL: Install a Lockdown Browser
                            </div>
                            <div style="color:#fca5a5; font-size:12px; line-height:1.4;">
                                • Safe Exam Browser (SEB) - Free, open-source<br>
                                • Respondus LockDown Browser - Institutional license<br>
                                • Contact your institution's IT support
                            </div>
                        </div>
                    ` : ''}
                </div>
                
                <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
                    <button id="sys-continue" style="background:${continueColor}; 
                           color:white; border:none; padding:14px 24px; border-radius:8px; 
                           font-weight:600; cursor:pointer; font-size:15px;">
                        ${continueText}
                    </button>
                    ${isSecurityIssue ? `
                        <button id="sys-download-seb" style="background:linear-gradient(135deg, #22c55e, #16a34a); 
                               color:white; border:none; padding:14px 24px; border-radius:8px; 
                               font-weight:600; cursor:pointer; font-size:15px;">
                            📥 Download Safe Exam Browser
                        </button>
                    ` : `
                        <button id="sys-optimize" style="background:linear-gradient(135deg, #3b82f6, #2563eb); 
                               color:white; border:none; padding:14px 24px; border-radius:8px; 
                               font-weight:600; cursor:pointer; font-size:15px;">
                            Optimize First
                        </button>
                    `}
                    <button id="sys-cancel" style="background:rgba(100,116,139,0.2); 
                           color:#94a3b8; border:1px solid #64748b; padding:14px 24px; border-radius:8px; 
                           font-weight:600; cursor:pointer; font-size:15px;">
                        Cancel Exam
                    </button>
                </div>
                
                <div style="margin-top:20px; font-size:12px; color:#64748b; opacity:0.8;">
                    ${isSecurityIssue 
                        ? "⚠️ Running exams without lockdown browsers violates security policies and may result in academic consequences"
                        : "Continuing with performance issues may result in crashes or poor monitoring quality"
                    }
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // Event handlers
            document.getElementById('sys-continue').onclick = () => {
                document.body.removeChild(overlay);
                resolve(true);
            };

            const optimizeBtn = document.getElementById('sys-optimize');
            if (optimizeBtn) {
                optimizeBtn.onclick = () => {
                    document.body.removeChild(overlay);
                    window.open('https://support.google.com/chrome/answer/142065?hl=en', '_blank');
                    resolve(false);
                };
            }

            const sebBtn = document.getElementById('sys-download-seb');
            if (sebBtn) {
                sebBtn.onclick = () => {
                    document.body.removeChild(overlay);
                    window.open('https://safeexambrowser.org/download_en.html', '_blank');
                    resolve(false);
                };
            }

            document.getElementById('sys-cancel').onclick = () => {
                document.body.removeChild(overlay);
                resolve(false);
            };
        });
    }

    async function init() {
        const status = document.getElementById("shield-status");
        
        try {
            // STEP 1: System Requirements Check
            updateStep(1, "🔍", "Analyzing system performance...", 5);
            status.innerText = "Checking device compatibility...";
            
            const sysCheck = await checkSystemRequirements();
            if (sysCheck.hasWarnings) {
                const proceed = await showSystemWarning(sysCheck);
                if (!proceed) {
                    updateStep(1, "⚠️", "System check cancelled by user", 5);
                    return;
                }
            }
            updateStep(1, "✅", sysCheck.summary, 15);
            
            // STEP 2: Check browser support
            updateStep(2, "🔍", "Checking browser compatibility...", 20);
            status.innerText = "Checking system compatibility...";
            
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                updateStep(2, "❌", "Browser doesn't support camera", 15);
                throw new Error("BROWSER_NOT_SUPPORTED: Please use Chrome, Firefox, or Edge");
            }
            
            await new Promise(r => setTimeout(r, 300)); // Brief pause for visibility
            updateStep(2, "✅", "Browser compatible", 30);
            
            // STEP 3: Request Camera
            updateStep(3, "📹", "Requesting camera access - CLICK ALLOW!", 35);
            status.innerText = "📹 Requesting camera... Please click ALLOW";
            document.getElementById("shield-footer-text").innerText = "Waiting for camera permission...";
            
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: "user"
                }, 
                audio: false 
            });
            
            updateStep(3, "✅", "Camera access granted: " + stream.getVideoTracks()[0].label, 50);
            status.innerText = "Camera connected, starting video...";
            
            video.srcObject = stream;
            
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    updateStep(3, "⚠️", "Camera timeout - may be in use", 50);
                    reject(new Error("CAMERA_TIMEOUT: Camera failed to start. Close other apps using camera."));
                }, 15000);
                
                video.onloadeddata = () => { 
                    if(isExamRunning) window.MoveVideoToHud(); 
                    clearTimeout(timeout);
                    updateStep(3, "✅", "Camera live and streaming", 60);
                    resolve(); 
                };
                
                video.play().catch(e => {
                    clearTimeout(timeout);
                    reject(new Error("VIDEO_PLAY_ERROR: " + e.message));
                });
            });

            // STEP 4: Download AI Model
            updateStep(4, "📥", "Downloading AI model (10MB)...", 60);
            status.innerText = "Downloading AI engine... Please wait";
            document.getElementById("shield-footer-text").innerText = "Loading neural network...";
            
            // Model download happens automatically with FilesetResolver
            updateStep(4, "✅", "AI model downloaded", 65);

            // STEP 5: Load AI Framework
            updateStep(5, "📥", "Loading AI framework...", 70);
            status.innerText = "Loading AI framework...";
            
            const vision = await FilesetResolver.forVisionTasks(CONFIG.wasm_path);
            updateStep(5, "✅", "AI framework ready", 75);
            
            // STEP 6: Initialize Face Detection
            updateStep(6, "🤖", "Initializing face detection AI...", 80);
            status.innerText = "Initializing face detection...";
            
            faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                baseOptions: { 
                    modelAssetPath: CONFIG.asset_path, 
                    delegate: "GPU" 
                },
                outputFaceBlendshapes: true, 
                runningMode: "VIDEO", 
                numFaces: 1 
            });
            
            updateStep(6, "✅", "Face detection ready", 85);
            
            // Check if student is banned
            if (sessionStorage.getItem(BAN_KEY) === 'true') {
                showPermanentBan();
                return;
            }
            
            // STEP 7: Environment Scan
            updateStep(7, "🔍", "Environment scan required - Turn slowly 360°", 90);
            status.innerText = "📹 Scan your environment by turning slowly 360°";
            document.getElementById("shield-footer-text").innerText = "Environment verification required...";
            
            await performEnvironmentScan();
            
            updateStep(7, "✅", "Environment verified", 100);
            status.innerText = "✅ System Active - Position your face in camera";
            document.getElementById("shield-footer-text").innerText = "AI Monitoring Active";

            // Hide steps after success
            setTimeout(() => {
                document.getElementById("init-steps").style.display = "none";
            }, 2000);
            
            if(isExamRunning && isStrictMode) enableStrictModeUI();
            
            startLoop();
            startWatchdog();

        } catch (err) {
            let errorMsg = err.message;
            let errorCode = "";
            let helpText = "";
            let showRetry = true;
            
            if (errorMsg.includes("BROWSER_NOT_SUPPORTED")) {
                errorCode = "Incompatible Browser";
                helpText = "This browser doesn't support camera access. Please use Chrome, Firefox, or Microsoft Edge.";
                updateStep(2, "❌", "Browser not supported", 15);
            } else if (err.name === "NotAllowedError" || err.name === "PermissionDeniedError") {
                errorCode = "Camera Permission Denied";
                helpText = "You must allow camera access. Look for the camera icon in your browser's address bar and click Allow, then refresh this page.";
                updateStep(3, "❌", "Camera access denied", 35);
            } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
                errorCode = "No Camera Found";
                helpText = "No camera detected on this device. Please connect a webcam and refresh the page.";
                updateStep(3, "❌", "No camera detected", 35);
            } else if (err.name === "NotReadableError" || err.name === "TrackStartError" || errorMsg.includes("CAMERA_TIMEOUT")) {
                errorCode = "Camera In Use";
                helpText = "Camera is being used by another application (Zoom, Teams, Skype, etc.). Close other apps and refresh.";
                updateStep(3, "❌", "Camera in use by another app", 35);
            } else if (errorMsg.includes("VIDEO_PLAY")) {
                errorCode = "Video Playback Error";
                helpText = "Failed to start video playbook. This may be a browser security setting. Try refreshing the page.";
                updateStep(3, "❌", "Video playbook failed", 50);
            } else {
                errorCode = "Initialization Failed";
                helpText = "Error: " + err.message.substring(0, 100);
            }
            
            status.innerHTML = `<span style='color:#ef4444; font-weight:700; font-size:20px;'>❌ ${errorCode}</span><br><span style='font-size:14px; color:#cbd5e1; margin-top:12px; display:block; line-height:1.5;'>${helpText}</span><br><br><button onclick="location.reload()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">🔄 Refresh & Retry</button>`;
            
            document.getElementById("shield-footer-text").innerText = "Initialization failed";
            document.getElementById("init-progress").style.background = "linear-gradient(90deg, #ef4444, #dc2626)";
        }
    }

    function startLoop() { predictWebcam(); }

    async function predictWebcam() {
        window.requestAnimationFrame(predictWebcam);
        const now = performance.now();
        if (now - lastProcessTime < FRAME_INTERVAL) return;
        lastProcessTime = now;

        try {
            if (!faceLandmarker || !video || video.paused || video.ended) return;
            lastLoopTime = now;
            const result = faceLandmarker.detectForVideo(video, now);
            if (!isExamRunning) runQuickCheck(result);
            else runMonitor(result);
        } catch (e) {}
    }

    function startWatchdog() {
        if (watchdogTimer) clearInterval(watchdogTimer);
        watchdogTimer = setInterval(() => {
            if (performance.now() - lastLoopTime > 2000) { startLoop(); }
        }, 1000);
    }

    function runQuickCheck(result) {
        if (result.faceLandmarks.length > 0) {
            document.getElementById("shield-status").innerText = "✅ Face detected! Starting monitoring...";
            setTimeout(() => { if(!isExamRunning) window.StartProctorExam(); }, 1000);
        } else {
            document.getElementById("shield-status").innerText = "Position yourself in front of the camera";
        }
    }



    function runMonitor(result) {
        const blocker = document.getElementById("click-blocker");
        const now = Date.now();

        // 1. NO FACE - Show mini silhouette for repositioning
        if (!result.faceLandmarks || result.faceLandmarks.length === 0) {
            blocker.style.display = 'block';
            showMiniSilhouette(true);
            handleViolation("No Face");
            return;
        }
        
        // 2. FACE DETECTED - Continue monitoring
        blocker.style.display = 'none';

        // 3. DATA ANALYSIS
        const noseX = result.faceLandmarks[0][1].x;
        const noseY = result.faceLandmarks[0][1].y;
        
        let eyeL = 0, eyeR = 0, mouthScore = 0;
        
        if (result.faceBlendshapes && result.faceBlendshapes.length > 0) {
            const shapes = result.faceBlendshapes[0].categories;
            eyeL = shapes.find(s => s.categoryName === 'eyeLookDownLeft')?.score || 0;
            eyeR = shapes.find(s => s.categoryName === 'eyeLookDownRight')?.score || 0;
            mouthScore = shapes.find(s => s.categoryName === 'mouthOpen')?.score || 0;
        }

        const eyeAvg = (eyeL + eyeR) / 2;
        document.getElementById("val-eye").innerText = eyeAvg.toFixed(2);
        document.getElementById("val-pitch").innerText = noseY.toFixed(2);

        // 4. INTELLIGENT VIOLATION DETECTION
        let violation = null;
        
        if (noseX < LIMITS.HEAD_LEFT) violation = "Turning Left";
        if (noseX > LIMITS.HEAD_RIGHT) violation = "Turning Right";
        
        if (isStrictMode) {
            if (eyeAvg > LIMITS.EYE_DOWN_THRESHOLD && noseY > LIMITS.PITCH_DOWN_THRESHOLD) {
                violation = "Looking Down";
            }
            if (mouthScore > LIMITS.MOUTH_OPEN) violation = "Talking";
        }

        // 5. INTELLIGENT RESPONSE
        if (violation) {
            // Check if we're in grace period of active warning
            if (warningActive && currentWarningType === violation) {
                const elapsed = now - warningStartTime;
                updateWarningCountdown(Math.max(0, warningGracePeriod - elapsed));
                
                // Grace period expired - escalate
                if (elapsed >= warningGracePeriod) {
                    escalateViolation(violation);
                }
            } else if (!warningActive && (now - lastWarningTime) >= warningCooldown) {
                // New violation - show intelligent warning first
                showIntelligentWarning(violation);
            } else {
                // Still in cooldown, just update meter
                suspicionMeter += HEAT_RATE * 0.3; // Reduced heat during cooldown
                updateBar();
            }
        } else {
            // Behavior corrected!
            if (warningActive) {
                dismissWarning(true); // Successfully corrected
            }
            if(suspicionMeter > 0) suspicionMeter -= COOL_RATE;
            updateHeaderStatus("● Normal", "green");
            updateBar();
        }
    }



    function handleViolation(reason) {
        // Commercial analytics tracking
        if (CONFIG.analytics_enabled) {
            analytics.trackViolation(reason, {
                timestamp: Date.now(),
                confidence: suspicionMeter / MAX_METER,
                is_strict_mode: isStrictMode,
                session_time: Date.now() - analytics.sessionStart
            });
        }
        
        suspicionMeter += (reason === "No Face") ? 3.0 : HEAT_RATE; 
        
        const statusEl = document.getElementById("debug-mode");
        statusEl.innerText = reason.toUpperCase();
        statusEl.style.color = "#ef4444";
        
        const pulse = document.getElementById("status-pulse");
        if (pulse) {
            pulse.style.background = "#ef4444";
        }
        
        updateHeaderStatus(reason, "#ef4444");
        updateBar();

        if (suspicionMeter >= MAX_METER) { 
            triggerLockdown(reason);
        }
    }
    
    function updateHeaderStatus(text, color) {
        const el = document.getElementById("header-status");
        const pulse = document.getElementById("status-pulse");
        
        if(el.innerText !== text) {
            el.innerText = text;
            
            // Update colors based on status
            if (color === "#ef4444") {
                if (pulse) pulse.style.background = "#ef4444";
            } else {
                if (pulse) pulse.style.background = "#4ade80";
            }
        }
    }

    function logViolationToHUD(msg) {
        const log = document.getElementById("violation-log");
        const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const item = document.createElement("li");
        item.innerHTML = `<span class="log-time">${time}</span><span class="log-msg">${msg}</span>`;
        log.prepend(item);
        
        // Keep only last 10 logs
        while (log.children.length > 10) {
            log.removeChild(log.lastChild);
        }
    }

    function updateBar() {
        const visualMeter = Math.min(MAX_METER, Math.max(0, suspicionMeter));
        const pct = (visualMeter / MAX_METER) * 100;
        const bar = document.getElementById("heat-bar");
        bar.style.width = pct + "%";
        
        // Update classes for gradient styles
        bar.classList.remove('warning', 'danger');
        
        if(pct < 50) {
            // Keep default green gradient
        } else if(pct < 80) {
            bar.classList.add('warning');
        } else {
            bar.classList.add('danger');
        }
        
        document.getElementById("debug-suspicion").innerText = pct.toFixed(0) + "%";
        
        // Update status card color
        const statusValue = document.getElementById("debug-mode");
        if (pct === 0) {
            statusValue.style.color = "#4ade80";
            statusValue.innerText = "NORMAL";
        }
    }

    function triggerLockdown(reason) {
        violationTotal++;
        sessionStorage.setItem(CONFIG.count_key, violationTotal);
        
        document.getElementById("header-strikes").innerText = violationTotal;
        logViolationToHUD("⚠️ STRIKE " + violationTotal + " - " + reason);
        
        // Check for permanent ban
        if (violationTotal >= MAX_VIOLATIONS) {
            triggerPermanentBan(reason);
            return;
        }
        
        if (violationTotal >= 5) enableStrictModeUI();

        captureEvidence(reason);
        
        isExamRunning = false;
        
        document.getElementById("ai-shield").style.display = 'flex';
        document.getElementById("ai-hud").style.display = 'none';
        document.getElementById("click-blocker").style.display = 'none';
        window.MoveVideoToShield();
        
        const warningLevel = violationTotal >= 8 ? 'FINAL WARNING' : 'VIOLATION DETECTED';
        const warningColor = violationTotal >= 8 ? '#dc2626' : '#ef4444';
        
        document.getElementById("shield-status").innerHTML = `<span style='color:${warningColor}; font-size:20px; font-weight:700;'>⚠️ ${warningLevel}</span><br><span style='color:#94a3b8; font-size:14px; margin-top:8px; display:inline-block;'>${reason}</span><br><span style='color:#fbbf24; font-size:12px; margin-top:4px; display:inline-block;'>${MAX_VIOLATIONS - violationTotal} strikes remaining</span>`;
        
        suspicionMeter = 0; 
        updateBar();
        
        setTimeout(() => { 
            document.getElementById("shield-status").innerText = "Position yourself in camera view to resume";
        }, 3500);
    }
    
    function triggerPermanentBan(reason) {
        isBanned = true;
        banReason = `Excessive violations: ${reason} (${violationTotal}/${MAX_VIOLATIONS})`;
        
        // Store ban permanently
        sessionStorage.setItem(BAN_KEY, 'true');
        sessionStorage.setItem(BAN_KEY + '_reason', banReason);
        sessionStorage.setItem(BAN_KEY + '_time', new Date().toLocaleString());
        
        // Log final evidence
        captureEvidence(`BANNED: ${banReason}`);
        logViolationToHUD(`🚫 PERMANENTLY BANNED - ${reason}`);
        
        // Upload ban notification to server
        fetch(CONFIG.upload_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                courseid: CONFIG.course_id,
                sesskey: CONFIG.sess_key,
                batch_data: [{ 
                    message: `STUDENT BANNED: ${banReason}`,
                    ban_details: {
                        reason: banReason,
                        time: new Date().toISOString(),
                        total_violations: violationTotal,
                        session_id: Date.now().toString(36).toUpperCase()
                    }
                }]
            })
        });
        
        // Show permanent ban screen
        setTimeout(() => {
            showPermanentBan();
        }, 1000);
    }

    function captureEvidence(msg) {
        evidenceCtx.drawImage(video, 0, 0, 320, 240);
        const data = evidenceCanvas.toDataURL('image/jpeg', 0.5);
        
        fetch(CONFIG.upload_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                courseid: CONFIG.course_id, 
                sesskey: CONFIG.sess_key,
                batch_data: [{ image: data, message: msg }]
            })
        }).then(res => {
            if(!res.ok) console.error("Upload Error: " + res.status);
        });
    }

    function showIntelligentWarning(violationType) {
        warningActive = true;
        currentWarningType = violationType;
        warningStartTime = Date.now();
        lastWarningTime = Date.now();
        
        const overlay = document.getElementById("warning-overlay");
        const message = document.getElementById("warning-message");
        const instructions = document.getElementById("instruction-list");
        
        // Blur exam content
        document.body.style.filter = "blur(8px)";
        overlay.style.display = "block";
        
        // Set specific instructions based on violation type
        const guides = {
            "Turning Left": {
                message: "Your head is turned too far to the left",
                instructions: [
                    "⬅️ Slowly turn your head back to the center",
                    "👀 Look directly at the screen",
                    "📐 Keep your face straight and centered in the camera",
                    "✅ The warning will disappear when positioned correctly"
                ]
            },
            "Turning Right": {
                message: "Your head is turned too far to the right",
                instructions: [
                    "➡️ Slowly turn your head back to the center",
                    "👀 Look directly at the screen",
                    "📐 Keep your face straight and centered in the camera",
                    "✅ The warning will disappear when positioned correctly"
                ]
            },
            "Looking Down": {
                message: "You are looking down - eyes must face the screen",
                instructions: [
                    "⬆️ Lift your head and look up at the screen",
                    "👁️ Keep your eyes on the exam questions",
                    "💺 Adjust your chair height if screen is too high/low",
                    "✅ Maintain eye contact with the screen"
                ]
            },
            "Talking": {
                message: "Verbal communication detected during exam",
                instructions: [
                    "🤐 Close your mouth - no talking allowed",
                    "🔇 Maintain complete silence",
                    "📵 Ensure no other person is in the room",
                    "⚠️ Repeated talking will result in exam suspension"
                ]
            },
            "No Face": {
                message: "Your face is not visible in the camera",
                instructions: [
                    "📹 Position yourself in front of the camera",
                    "💡 Ensure adequate lighting on your face",
                    "🪟 Remove any objects blocking the camera",
                    "✅ Your face must be fully visible at all times"
                ]
            }
        };
        
        const guide = guides[violationType] || guides["No Face"];
        message.innerText = guide.message;
        instructions.innerHTML = guide.instructions.map(i => `<li style="padding:4px 0;">➤ ${i}</li>`).join('');
        
        logViolationToHUD("⚠️ WARNING: " + violationType + " - Guidance shown");
        
        // Start countdown
        let remaining = Math.floor(warningGracePeriod / 1000);
        const countdownInterval = setInterval(() => {
            remaining--;
            const countdownEl = document.getElementById("warning-countdown");
            if (countdownEl) countdownEl.innerText = remaining;
            if (remaining <= 0 || !warningActive) clearInterval(countdownInterval);
        }, 1000);
    }
    
    function updateWarningCountdown(remainingMs) {
        const seconds = Math.ceil(remainingMs / 1000);
        const countdownEl = document.getElementById("warning-countdown");
        if (countdownEl) countdownEl.innerText = seconds;
    }
    
    function dismissWarning(corrected) {
        warningActive = false;
        currentWarningType = null;
        document.body.style.filter = "none";
        document.getElementById("warning-overlay").style.display = "none";
        
        if (corrected) {
            logViolationToHUD("✅ Position corrected - Warning dismissed");
            suspicionMeter = Math.max(0, suspicionMeter - 2); // Reward correction
            updateBar();
        }
    }
    
    function escalateViolation(violationType) {
        dismissWarning(false);
        
        const now = Date.now();
        
        // Check if we should upload evidence (respect cooldown)
        if (now - lastEvidenceUpload >= evidenceUploadCooldown) {
            // Record 5-second video clip with audio
            startVideoRecording(violationType);
            lastEvidenceUpload = now;
            
            violationTotal++;
            sessionStorage.setItem(CONFIG.count_key, violationTotal);
            document.getElementById("header-strikes").innerText = violationTotal;
            
            logViolationToHUD("🎥 EVIDENCE CAPTURED: " + violationType + " - Video clip recorded");
            
            // Disable strict mode after evidence collection (give student benefit of doubt)
            if (isStrictMode && violationTotal < 5) {
                isStrictMode = false;
                const statusBar = document.getElementById("proctor-status-bar");
                if (statusBar) {
                    statusBar.style.background = "linear-gradient(135deg, #667eea 0%, #764ba2 100%)";
                    statusBar.style.boxShadow = "0 4px 15px rgba(102, 126, 234, 0.3)";
                }
                document.getElementById("header-status").innerText = "Monitoring (Reduced Sensitivity)";
                logViolationToHUD("ℹ️ Aggressive tracking disabled - Continue exam with caution");
            }
            
            if (violationTotal >= 5) enableStrictModeUI();
        } else {
            // Still in upload cooldown - just log without evidence
            logViolationToHUD("⚠️ " + violationType + " continues (No evidence - cooldown active)");
            suspicionMeter += HEAT_RATE * 0.5;
            updateBar();
        }
    }
    
    function startVideoRecording(violationType) {
        try {
            const stream = video.srcObject;
            
            // Try to get audio if available (optional)
            navigator.mediaDevices.getUserMedia({ audio: true }).then(audioStream => {
                const combinedStream = new MediaStream([
                    ...stream.getVideoTracks(),
                    ...audioStream.getAudioTracks()
                ]);
                
                recordWithStream(combinedStream, violationType, true);
            }).catch(() => {
                // Audio not available, record video only
                recordWithStream(stream, violationType, false);
            });
            
        } catch(e) {
            console.error("Video recording failed:", e);
            captureEvidence(violationType); // Fallback to image
        }
    }
    
    function recordWithStream(stream, violationType, hasAudio) {
        recordedChunks = [];
        
        const options = { mimeType: 'video/webm;codecs=vp8,opus' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            options.mimeType = 'video/webm';
        }
        
        videoRecorder = new MediaRecorder(stream, options);
        
        videoRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recordedChunks.push(e.data);
        };
        
        videoRecorder.onstop = () => {
            const blob = new Blob(recordedChunks, { type: 'video/webm' });
            uploadVideoEvidence(blob, violationType, hasAudio);
        };
        
        videoRecorder.start();
        logViolationToHUD("🎥 Recording " + (hasAudio ? "video+audio" : "video") + "...");
        
        // Record for 5 seconds
        setTimeout(() => {
            if (videoRecorder && videoRecorder.state === 'recording') {
                videoRecorder.stop();
            }
        }, 5000);
    }
    
    function uploadVideoEvidence(blob, violationType, hasAudio) {
        const reader = new FileReader();
        reader.onloadend = () => {
            const base64data = reader.result;
            
            fetch(CONFIG.upload_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    courseid: CONFIG.course_id,
                    sesskey: CONFIG.sess_key,
                    batch_data: [{
                        video: base64data,
                        message: violationType + (hasAudio ? " [VIDEO+AUDIO]" : " [VIDEO]"),
                        duration: 5
                    }]
                })
            }).then(res => {
                if (res.ok) {
                    logViolationToHUD("✅ Evidence uploaded - Teacher will review");
                } else {
                    console.error("Upload failed:", res.status);
                }
            });
        };
        reader.readAsDataURL(blob);
    }

    function enableStrictModeUI() {
        isStrictMode = true;
        const statusBar = document.getElementById("proctor-status-bar");
        if (statusBar) {
            statusBar.style.background = "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)";
            statusBar.style.boxShadow = "0 4px 15px rgba(239, 68, 68, 0.4)";
        }
        document.getElementById("header-status").innerText = "⚡ STRICT MODE";
        const pulse = document.getElementById("status-pulse");
        if (pulse) pulse.style.background = "#fbbf24";
    }

    window.MoveVideoToHud = function() { 
        document.getElementById("mini-video-container").appendChild(video); 
        video.style.width = "100%"; 
        video.style.height = "100%";
    };
    
    window.MoveVideoToShield = function() { 
        document.querySelector(".big-camera").appendChild(video); 
        video.style.width = "100%"; 
        video.style.height = "100%";
    };
    
    window.StartProctorExam = function() {
        sessionStorage.setItem(CONFIG.verified_key, 'true');
        document.getElementById("ai-shield").style.display = 'none';
        document.getElementById("ai-hud").style.display = 'block';
        window.MoveVideoToHud();
        isExamRunning = true;
    };
    
    // Removed beforeunload warning - AI state persists via sessionStorage across pages
    
    init();
</script>

<?php endif; ?>

<?php 
        $html = ob_get_contents();
        ob_end_clean();
        $this->content->text = $html;
        return $this->content;
    }
}
?>