<?php
/**
 * Priority Support System
 * Ticketing system with SLA tracking
 * 
 * @package block_ai_proctor
 * @copyright 2026 AI Proctor
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

class support_system {
    
    /**
     * Create new support ticket
     */
    public static function create_ticket($data) {
        global $DB, $USER;
        
        $ticket = new stdClass();
        $ticket->ticket_number = self::generate_ticket_number();
        $ticket->institutionid = $data['institutionid'];
        $ticket->userid = $USER->id;
        $ticket->subject = $data['subject'];
        $ticket->description = $data['description'];
        $ticket->priority = $data['priority'] ?? 'medium';
        $ticket->category = $data['category'] ?? 'general';
        $ticket->status = 'open';
        $ticket->assigned_to = null;
        $ticket->timecreated = time();
        $ticket->timemodified = time();
        
        // Calculate SLA deadline based on priority
        $ticket->sla_deadline = self::calculate_sla_deadline($ticket->priority, $ticket->institutionid);
        
        $ticketid = $DB->insert_record('block_ai_proctor_tickets', $ticket);
        
        // Send notifications
        self::notify_new_ticket($ticketid);
        
        return $ticketid;
    }
    
    /**
     * Update ticket
     */
    public static function update_ticket($ticketid, $data) {
        global $DB;
        
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        
        if (!$ticket) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            if (property_exists($ticket, $key) && $key !== 'id') {
                $ticket->$key = $value;
            }
        }
        
        $ticket->timemodified = time();
        
        // If status changed to resolved
        if ($data['status'] === 'resolved' && $ticket->status !== 'resolved') {
            $ticket->resolved_at = time();
            $ticket->resolution_time = $ticket->resolved_at - $ticket->timecreated;
            self::notify_ticket_resolved($ticketid);
        }
        
        return $DB->update_record('block_ai_proctor_tickets', $ticket);
    }
    
    /**
     * Add comment to ticket
     */
    public static function add_comment($ticketid, $comment, $is_internal = false) {
        global $DB, $USER;
        
        $comment_obj = new stdClass();
        $comment_obj->ticketid = $ticketid;
        $comment_obj->userid = $USER->id;
        $comment_obj->comment = $comment;
        $comment_obj->is_internal = $is_internal;
        $comment_obj->timecreated = time();
        
        $commentid = $DB->insert_record('block_ai_proctor_ticket_comments', $comment_obj);
        
        // Update ticket modified time
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        $ticket->timemodified = time();
        $DB->update_record('block_ai_proctor_tickets', $ticket);
        
        // Notify user
        if (!$is_internal) {
            self::notify_new_comment($ticketid, $commentid);
        }
        
        return $commentid;
    }
    
    /**
     * Get tickets for institution
     */
    public static function get_tickets($institutionid, $filters = []) {
        global $DB;
        
        $sql = "SELECT * FROM {block_ai_proctor_tickets} WHERE institutionid = ?";
        $params = [$institutionid];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        $sql .= " ORDER BY timecreated DESC";
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Get ticket with comments
     */
    public static function get_ticket_details($ticketid) {
        global $DB;
        
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        
        if (!$ticket) {
            return null;
        }
        
        $ticket->comments = $DB->get_records('block_ai_proctor_ticket_comments', 
            ['ticketid' => $ticketid], 'timecreated ASC');
        
        return $ticket;
    }
    
    /**
     * Get SLA statistics
     */
    public static function get_sla_stats($institutionid) {
        global $DB;
        
        $stats = [];
        
        // Total tickets
        $stats['total'] = $DB->count_records('block_ai_proctor_tickets', [
            'institutionid' => $institutionid
        ]);
        
        // Open tickets
        $stats['open'] = $DB->count_records('block_ai_proctor_tickets', [
            'institutionid' => $institutionid,
            'status' => 'open'
        ]);
        
        // Resolved tickets
        $stats['resolved'] = $DB->count_records('block_ai_proctor_tickets', [
            'institutionid' => $institutionid,
            'status' => 'resolved'
        ]);
        
        // SLA breaches
        $stats['sla_breaches'] = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {block_ai_proctor_tickets}
             WHERE institutionid = ? AND status != 'resolved' AND sla_deadline < ?",
            [$institutionid, time()]
        );
        
        // Average resolution time
        $avg_resolution = $DB->get_field_sql(
            "SELECT AVG(resolution_time) FROM {block_ai_proctor_tickets}
             WHERE institutionid = ? AND status = 'resolved'",
            [$institutionid]
        );
        $stats['avg_resolution_hours'] = $avg_resolution ? round($avg_resolution / 3600, 1) : 0;
        
        return $stats;
    }
    
    /**
     * Calculate SLA deadline based on priority and tier
     */
    private static function calculate_sla_deadline($priority, $institutionid) {
        global $DB;
        
        $institution = $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
        
        // Response times in hours
        $sla_times = [
            'free' => [
                'critical' => 24,
                'high' => 48,
                'medium' => 72,
                'low' => 120
            ],
            'professional' => [
                'critical' => 4,
                'high' => 8,
                'medium' => 24,
                'low' => 48
            ],
            'enterprise' => [
                'critical' => 1,
                'high' => 4,
                'medium' => 12,
                'low' => 24
            ]
        ];
        
        $tier = $institution->tier ?? 'free';
        $hours = $sla_times[$tier][$priority] ?? 72;
        
        return time() + ($hours * 3600);
    }
    
    /**
     * Generate unique ticket number
     */
    private static function generate_ticket_number() {
        return 'TICKET-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }
    
    /**
     * Notify new ticket
     */
    private static function notify_new_ticket($ticketid) {
        global $DB;
        
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        $user = $DB->get_record('user', ['id' => $ticket->userid]);
        
        // Email to support team
        $admins = get_admins();
        foreach ($admins as $admin) {
            $subject = "[AI Proctor] New Support Ticket: {$ticket->ticket_number}";
            $message = "New support ticket created:\n\n";
            $message .= "Ticket: {$ticket->ticket_number}\n";
            $message .= "Priority: {$ticket->priority}\n";
            $message .= "Subject: {$ticket->subject}\n";
            $message .= "From: {$user->firstname} {$user->lastname}\n\n";
            $message .= "Description:\n{$ticket->description}\n";
            
            email_to_user($admin, $user, $subject, $message);
        }
        
        // Email confirmation to user
        $subject = "Support Ticket Created: {$ticket->ticket_number}";
        $message = "Your support ticket has been created.\n\n";
        $message .= "Ticket Number: {$ticket->ticket_number}\n";
        $message .= "We'll respond within " . self::get_sla_hours($ticket->priority, $ticket->institutionid) . " hours.\n";
        
        email_to_user($user, get_admin(), $subject, $message);
    }
    
    /**
     * Notify ticket resolved
     */
    private static function notify_ticket_resolved($ticketid) {
        global $DB;
        
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        $user = $DB->get_record('user', ['id' => $ticket->userid]);
        
        $subject = "Support Ticket Resolved: {$ticket->ticket_number}";
        $message = "Your support ticket has been resolved.\n\n";
        $message .= "Ticket: {$ticket->ticket_number}\n";
        $message .= "Subject: {$ticket->subject}\n\n";
        $message .= "If you need further assistance, please reply to this ticket.\n";
        
        email_to_user($user, get_admin(), $subject, $message);
    }
    
    /**
     * Notify new comment
     */
    private static function notify_new_comment($ticketid, $commentid) {
        global $DB;
        
        $ticket = $DB->get_record('block_ai_proctor_tickets', ['id' => $ticketid]);
        $comment = $DB->get_record('block_ai_proctor_ticket_comments', ['id' => $commentid]);
        $user = $DB->get_record('user', ['id' => $ticket->userid]);
        
        $subject = "New Response: {$ticket->ticket_number}";
        $message = "New response to your support ticket:\n\n";
        $message .= "Ticket: {$ticket->ticket_number}\n\n";
        $message .= $comment->comment . "\n";
        
        email_to_user($user, get_admin(), $subject, $message);
    }
    
    /**
     * Get SLA hours for tier and priority
     */
    private static function get_sla_hours($priority, $institutionid) {
        global $DB;
        
        $institution = $DB->get_record('block_ai_proctor_institutions', ['id' => $institutionid]);
        
        $sla_hours = [
            'free' => ['critical' => 24, 'high' => 48, 'medium' => 72, 'low' => 120],
            'professional' => ['critical' => 4, 'high' => 8, 'medium' => 24, 'low' => 48],
            'enterprise' => ['critical' => 1, 'high' => 4, 'medium' => 12, 'low' => 24]
        ];
        
        $tier = $institution->tier ?? 'free';
        return $sla_hours[$tier][$priority] ?? 72;
    }
}
