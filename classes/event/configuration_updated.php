<?php
/**
 * AI Proctor Configuration Updated Event
 * 
 * Event triggered when the AI Proctor block configuration is modified.
 * Tracks administrative changes to monitoring settings and thresholds.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class configuration_updated extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_instances';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_configuration_updated', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        $changesCount = $this->other['changes_count'] ?? 0;
        $mainChanges = $this->other['main_changes'] ?? 'Settings updated';
        
        return "User '$this->userid' updated AI Proctor configuration in course '$this->courseid'. " .
               "Changes: $changesCount items modified ($mainChanges)";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', [
            'id' => $this->courseid,
            'bui_editid' => $this->objectid
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
        
        if (!isset($this->objectid)) {
            throw new \coding_exception('objectid (block instance id) must be set');
        }
    }
    
    /**
     * Create event instance
     * @param int $courseid Course ID
     * @param int $blockinstanceid Block instance ID
     * @param \context $context Context
     * @param array $oldconfig Previous configuration
     * @param array $newconfig New configuration
     * @return configuration_updated
     */
    public static function create_from_config($courseid, $blockinstanceid, $context, $oldconfig = [], $newconfig = []) {
        $changes = self::analyze_changes($oldconfig, $newconfig);
        
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $blockinstanceid,
            'other' => [
                'changes_count' => count($changes['modified']),
                'main_changes' => self::format_main_changes($changes),
                'detailed_changes' => $changes,
                'previous_config' => $oldconfig,
                'new_config' => $newconfig,
                'configuration_version' => time(),
                'admin_user' => $context->get_context_name(),
                'timestamp' => time()
            ]
        ]);
        
        return $event;
    }
    
    /**
     * Analyze configuration changes
     * @param array $oldconfig
     * @param array $newconfig
     * @return array Change analysis
     */
    private static function analyze_changes($oldconfig, $newconfig) {
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];
        
        // Find new settings
        foreach ($newconfig as $key => $value) {
            if (!array_key_exists($key, $oldconfig)) {
                $changes['added'][$key] = $value;
            } elseif ($oldconfig[$key] !== $value) {
                $changes['modified'][$key] = [
                    'old' => $oldconfig[$key],
                    'new' => $value
                ];
            }
        }
        
        // Find removed settings
        foreach ($oldconfig as $key => $value) {
            if (!array_key_exists($key, $newconfig)) {
                $changes['removed'][$key] = $value;
            }
        }
        
        return $changes;
    }
    
    /**
     * Format main changes for description
     * @param array $changes
     * @return string
     */
    private static function format_main_changes($changes) {
        $summary = [];
        
        if (!empty($changes['modified'])) {
            $keyNames = array_keys($changes['modified']);
            $friendlyNames = self::get_friendly_names($keyNames);
            $summary[] = 'Modified: ' . implode(', ', array_slice($friendlyNames, 0, 3));
            
            if (count($friendlyNames) > 3) {
                $summary[] = '+ ' . (count($friendlyNames) - 3) . ' more';
            }
        }
        
        if (!empty($changes['added'])) {
            $summary[] = 'Added ' . count($changes['added']) . ' setting(s)';
        }
        
        if (!empty($changes['removed'])) {
            $summary[] = 'Removed ' . count($changes['removed']) . ' setting(s)';
        }
        
        return empty($summary) ? 'No changes detected' : implode('; ', $summary);
    }
    
    /**
     * Get friendly names for configuration keys
     * @param array $keys
     * @return array
     */
    private static function get_friendly_names($keys) {
        $mapping = [
            'title' => 'Block Title',
            'monitoring_enabled' => 'Monitoring Status',
            'strict_mode_threshold' => 'Strict Mode Threshold',
            'violation_limit' => 'Violation Limit',
            'evidence_retention' => 'Evidence Retention',
            'ai_sensitivity' => 'AI Sensitivity',
            'warning_duration' => 'Warning Duration',
            'lockdown_enabled' => 'Lockdown Mode'
        ];
        
        return array_map(function($key) use ($mapping) {
            return $mapping[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }, $keys);
    }
}
