<?php
/**
 * AI Proctor Block
 *
 * @package    block_ai_proctor
 * @copyright  2024 Qigen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_ai_proctor_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('header', 'config', 'Settings');
        $mform->addElement('selectyesno', 'config_enabled', 'Enable Proctoring?');
        $mform->setDefault('config_enabled', 0);
        $mform->addElement('static', 'desc', '', 'Enable only for Exams.');
    }
}
