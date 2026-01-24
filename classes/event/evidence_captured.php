<?php
/**
 * AI Proctor Evidence Captured Event
 * 
 * Event triggered when evidence (photo or video) is captured and stored.
 * Tracks evidence collection for compliance and review purposes.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class evidence_captured extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_ai_proctor';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_evidence_captured', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        $type = $this->other['evidence_type'] ?? 'Unknown';
        $reason = $this->other['violation_reason'] ?? 'System capture';
        $size = $this->other['file_size'] ?? 0;
        $duration = $this->other['duration'] ?? null;
        
        $sizeStr = $size ? ' (' . round($size / 1024, 1) . ' KB)' : '';
        $durationStr = $duration ? " Duration: {$duration}s," : '';
        
        return "Evidence captured for user '$this->userid' in course '$this->courseid': $type$sizeStr. " .
               "Reason: $reason.$durationStr Evidence ID: $this->objectid";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/blocks/ai_proctor/image.php', [
            'id' => $this->objectid
        ]);
    }
    
    /**
     * Custom validation
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        
        if (!isset($this->courseid)) {
            throw new \coding_exception('courseid must be set');
        }
        
        if (!isset($this->other['evidence_type'])) {
            throw new \coding_exception('evidence_type must be set in other data');
        }
    }
    
    /**
     * Create event instance
     * @param int $courseid Course ID
     * @param \context $context Context
     * @param int $evidenceid Evidence record ID
     * @param array $evidencedata Evidence details
     * @return evidence_captured
     */
    public static function create_from_evidence($courseid, $context, $evidenceid, $evidencedata = []) {
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $evidenceid,
            'other' => [
                'evidence_type' => $evidencedata['type'] ?? 'image', // 'image', 'video', 'audio'
                'violation_reason' => $evidencedata['reason'] ?? 'Manual capture',
                'file_size' => $evidencedata['size'] ?? 0,
                'duration' => $evidencedata['duration'] ?? null, // For videos
                'has_audio' => $evidencedata['has_audio'] ?? false,
                'compression_ratio' => $evidencedata['compression'] ?? null,
                'ai_confidence' => $evidencedata['confidence'] ?? null,
                'capture_method' => $evidencedata['method'] ?? 'automatic', // 'automatic', 'manual', 'scheduled'
                'storage_location' => $evidencedata['storage'] ?? 'local',
                'encryption_status' => $evidencedata['encrypted'] ?? true,
                'retention_period' => $evidencedata['retention_days'] ?? 90,
                'timestamp' => time()
            ]
        ]);
        
        return $event;
    }
    
    /**
     * Create batch evidence event for multiple captures
     * @param int $courseid Course ID
     * @param \context $context Context
     * @param array $evidenceList Array of evidence items
     * @return evidence_captured
     */
    public static function create_from_batch($courseid, $context, $evidenceList) {
        $totalSize = array_sum(array_column($evidenceList, 'size'));
        $types = array_unique(array_column($evidenceList, 'type'));
        $count = count($evidenceList);
        
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => 0, // No single object for batch
            'other' => [
                'evidence_type' => 'batch',
                'violation_reason' => 'Multiple violations in session',
                'file_size' => $totalSize,
                'batch_count' => $count,
                'batch_types' => implode(', ', $types),
                'capture_method' => 'automatic_batch',
                'total_items' => $count,
                'compression_ratio' => 0.6, // Average estimate
                'storage_location' => 'local',
                'timestamp' => time()
            ]
        ]);
        
        return $event;
    }
}
