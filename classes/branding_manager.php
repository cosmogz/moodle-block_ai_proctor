<?php
/**
 * White-Label Branding System
 * Custom theming and branding for institutions
 * 
 * @package block_ai_proctor
 * @copyright 2026 AI Proctor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

class branding_manager {
    
    /**
     * Save branding settings
     */
    public static function save_branding($institutionid, $branding) {
        global $DB;
        
        $institution = $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
        
        if (!$institution) {
            return false;
        }
        
        // Save logo
        if (!empty($branding['logo'])) {
            $branding['logo_url'] = self::save_logo($institutionid, $branding['logo']);
        }
        
        $institution->branding = json_encode($branding);
        $institution->timemodified = time();
        
        return $DB->update_record('block_ai_proctor_institutions', $institution);
    }
    
    /**
     * Get branding settings
     */
    public static function get_branding($institutionid) {
        global $DB;
        
        $institution = $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
        
        if (!$institution || !$institution->branding) {
            return self::get_default_branding();
        }
        
        return json_decode($institution->branding, true);
    }
    
    /**
     * Generate custom CSS
     */
    public static function generate_css($institutionid) {
        $branding = self::get_branding($institutionid);
        
        $css = "/* AI Proctor Custom Branding */\n\n";
        
        // Primary color
        if (!empty($branding['primary_color'])) {
            $css .= ":root {\n";
            $css .= "  --primary-color: {$branding['primary_color']};\n";
            $css .= "}\n\n";
            
            $css .= ".btn-primary, .primary-bg {\n";
            $css .= "  background-color: {$branding['primary_color']} !important;\n";
            $css .= "}\n\n";
        }
        
        // Secondary color
        if (!empty($branding['secondary_color'])) {
            $css .= ".btn-secondary, .secondary-bg {\n";
            $css .= "  background-color: {$branding['secondary_color']} !important;\n";
            $css .= "}\n\n";
        }
        
        // Custom fonts
        if (!empty($branding['font_family'])) {
            $css .= "body, .custom-font {\n";
            $css .= "  font-family: {$branding['font_family']}, sans-serif !important;\n";
            $css .= "}\n\n";
        }
        
        // Logo styling
        if (!empty($branding['logo_url'])) {
            $css .= ".brand-logo {\n";
            $css .= "  background-image: url('{$branding['logo_url']}');\n";
            $css .= "  background-size: contain;\n";
            $css .= "  background-repeat: no-repeat;\n";
            $css .= "}\n\n";
        }
        
        // Hide AI Proctor branding if white-label enabled
        if (!empty($branding['hide_aiproctor_branding'])) {
            $css .= ".aiproctor-brand, .powered-by {\n";
            $css .= "  display: none !important;\n";
            $css .= "}\n\n";
        }
        
        return $css;
    }
    
    /**
     * Apply branding to page
     */
    public static function apply_branding($PAGE, $institutionid) {
        $branding = self::get_branding($institutionid);
        
        // Add custom CSS
        $css_url = new moodle_url('/blocks/ai_proctor/branding_css.php', ['id' => $institutionid]);
        $PAGE->requires->css($css_url);
        
        // Set custom title
        if (!empty($branding['site_name'])) {
            $PAGE->set_heading($branding['site_name']);
        }
        
        // Add custom header HTML
        if (!empty($branding['custom_header'])) {
            $PAGE->requires->js_init_code($branding['custom_header']);
        }
    }
    
    /**
     * Save logo file
     */
    private static function save_logo($institutionid, $logo_data) {
        global $CFG;
        
        $upload_dir = $CFG->dataroot . '/aiproctor/logos/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = "institution_{$institutionid}_" . time() . ".png";
        $filepath = $upload_dir . $filename;
        
        file_put_contents($filepath, $logo_data);
        
        return "/aiproctor/logos/{$filename}";
    }
    
    /**
     * Default branding
     */
    private static function get_default_branding() {
        return [
            'site_name' => 'AI Proctor',
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'font_family' => '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto',
            'hide_aiproctor_branding' => false
        ];
    }
}
