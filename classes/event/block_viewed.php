<?php
/**
 * AI Proctor Block Viewed Event
 * 
 * Event triggered when the AI Proctor block is displayed to a user.
 * Used for tracking engagement and system usage analytics.
 * 
 * @package    block_ai_proctor
 * @copyright  2025 Medwax Corporation Africa Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Medwax Corporation Africa Ltd.
 * @link       https://medwax.com
 */

namespace block_ai_proctor\event;

defined('MOODLE_INTERNAL') || die();

class block_viewed extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_instances';
    }
    
    /**
     * Get event name
     * @return string
     */
    public static function get_name() {
        return get_string('event_block_viewed', 'block_ai_proctor');
    }
    
    /**
     * Get event description
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the AI Proctor block in course with id '$this->courseid'.";
    }
    
    /**
     * Get event URL
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
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
    }
    
    /**
     * Create event instance
     * @param int $courseid Course ID
     * @param int $blockid Block instance ID
     * @param \context $context Context
     * @return block_viewed
     */
    public static function create_from_block($courseid, $blockid, $context) {
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $blockid,
            'other' => [
                'blocktype' => 'ai_proctor',
                'timestamp' => time()
            ]
        ]);
        
        return $event;
    }
}
