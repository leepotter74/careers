<?php
/**
 * Application Handler Class
 * 
 * Handles database operations and management for job applications
 */

if (!defined('ABSPATH')) {
    exit;
}

class Application_Handler {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'recruitment_applications';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX handlers for admin
        add_action('wp_ajax_bb_get_applications', array($this, 'get_applications_ajax'));
        add_action('wp_ajax_bb_get_application_details', array($this, 'get_application_details_ajax'));
        add_action('wp_ajax_bb_update_application_status', array($this, 'update_application_status_ajax'));
        add_action('wp_ajax_bb_delete_application', array($this, 'delete_application_ajax'));
        add_action('wp_ajax_bb_export_applications', array($this, 'export_applications_ajax'));

        // AJAX handler for loading saved applications
        add_action('wp_ajax_bb_load_saved_application', array($this, 'load_saved_application_ajax'));
        add_action('wp_ajax_nopriv_bb_load_saved_application', array($this, 'load_saved_application_ajax'));
    }
    
    /**
     * Create a new application
     */
    public function create_application($data) {
        global $wpdb;
        
        $defaults = array(
            'job_id' => 0,
            'user_id' => get_current_user_id() ?: null,
            'applicant_name' => '',
            'applicant_email' => '',
            'applicant_phone' => '',
            'application_data' => '',
            'application_status' => 'submitted',
            'save_token' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Serialize application data if it's an array
        if (is_array($data['application_data'])) {
            $data['application_data'] = json_encode($data['application_data']);
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update an existing application
     */
    public function update_application($application_id, $data) {
        global $wpdb;
        
        // Serialize application data if it's an array
        if (isset($data['application_data']) && is_array($data['application_data'])) {
            $data['application_data'] = json_encode($data['application_data']);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $application_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get application by ID
     */
    public function get_application($application_id) {
        global $wpdb;
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $application_id
        ));
        
        if ($application && $application->application_data) {
            $application->application_data = json_decode($application->application_data, true);
        }
        
        return $application;
    }
    
    /**
     * Get application by save token
     */
    public function get_application_by_token($save_token) {
        global $wpdb;
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE save_token = %s",
            $save_token
        ));
        
        if ($application && $application->application_data) {
            $application->application_data = json_decode($application->application_data, true);
        }
        
        return $application;
    }
    
    /**
     * Get applications with filters and pagination
     */
    public function get_applications($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'job_id' => null,
            'status' => null,
            'user_id' => null,
            'search' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        // Build WHERE clause
        if ($args['job_id']) {
            $where_clauses[] = "job_id = %d";
            $where_values[] = $args['job_id'];
        }
        
        if ($args['status']) {
            $where_clauses[] = "application_status = %s";
            $where_values[] = $args['status'];
        }
        
        if ($args['user_id']) {
            $where_clauses[] = "user_id = %d";
            $where_values[] = $args['user_id'];
        }
        
        if ($args['search']) {
            $where_clauses[] = "(applicant_name LIKE %s OR applicant_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Order by
        $allowed_orderby = array('id', 'job_id', 'applicant_name', 'applicant_email', 'application_status', 'created_date');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_date';
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'DESC';
        
        // Build query
        $query = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY {$orderby} {$order}";
        
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        // Execute query
        if (!empty($where_values)) {
            $applications = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $applications = $wpdb->get_results($query);
        }
        
        // Decode application data
        foreach ($applications as &$application) {
            if ($application->application_data) {
                $application->application_data = json_decode($application->application_data, true);
            }
        }
        
        return $applications;
    }
    
    /**
     * Get applications count
     */
    public function get_applications_count($args = array()) {
        global $wpdb;
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['job_id'])) {
            $where_clauses[] = "job_id = %d";
            $where_values[] = $args['job_id'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = "application_status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = "(applicant_name LIKE %s OR applicant_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
        
        if (!empty($where_values)) {
            return $wpdb->get_var($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_var($query);
        }
    }
    
    /**
     * Delete application
     */
    public function delete_application($application_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $application_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Update application status
     */
    public function update_status($application_id, $status) {
        return $this->update_application($application_id, array(
            'application_status' => $status
        ));
    }
    
    /**
     * Get application statistics
     */
    public function get_stats($job_id = null) {
        global $wpdb;
        
        $where_sql = $job_id ? $wpdb->prepare("WHERE job_id = %d", $job_id) : "";
        
        $stats = $wpdb->get_results("
            SELECT 
                application_status,
                COUNT(*) as count
            FROM {$this->table_name} 
            {$where_sql}
            GROUP BY application_status
        ");
        
        $formatted_stats = array(
            'total' => 0,
            'submitted' => 0,
            'under_review' => 0,
            'shortlisted' => 0,
            'interviewed' => 0,
            'offered' => 0,
            'hired' => 0,
            'rejected' => 0,
            'withdrawn' => 0,
            'draft' => 0
        );
        
        foreach ($stats as $stat) {
            $formatted_stats[$stat->application_status] = (int) $stat->count;
            $formatted_stats['total'] += (int) $stat->count;
        }
        
        return $formatted_stats;
    }
    
    /**
     * Get recent applications
     */
    public function get_recent_applications($limit = 10) {
        global $wpdb;
        
        $applications = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, p.post_title as job_title 
            FROM {$this->table_name} a
            LEFT JOIN {$wpdb->posts} p ON a.job_id = p.ID
            WHERE a.application_status != 'draft'
            ORDER BY a.created_date DESC 
            LIMIT %d
        ", $limit));
        
        foreach ($applications as &$application) {
            if ($application->application_data) {
                $application->application_data = json_decode($application->application_data, true);
            }
        }
        
        return $applications;
    }
    
    /**
     * AJAX: Get applications for admin
     */
    public function get_applications_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);
        $job_id = intval($_POST['job_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'search' => $search
        );
        
        if ($job_id > 0) {
            $args['job_id'] = $job_id;
        }
        
        if (!empty($status)) {
            $args['status'] = $status;
        }
        
        $applications = $this->get_applications($args);
        $total = $this->get_applications_count($args);
        
        // Add job titles
        foreach ($applications as &$application) {
            $application->job_title = get_the_title($application->job_id);
        }
        
        wp_send_json_success(array(
            'applications' => $applications,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ));
    }
    
    /**
     * AJAX: Get application details
     */
    public function get_application_details_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }

        $application_id = intval($_POST['application_id'] ?? 0);

        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID.', 'big-bundle'));
        }

        $application = $this->get_application($application_id);

        if (!$application) {
            wp_send_json_error(__('Application not found.', 'big-bundle'));
        }

        // Get job details
        $job_title = get_the_title($application->job_id);
        $job_url = get_edit_post_link($application->job_id);

        // Format status label
        $status_labels = array(
            'submitted' => __('Submitted', 'big-bundle'),
            'under_review' => __('Under Review', 'big-bundle'),
            'shortlisted' => __('Shortlisted', 'big-bundle'),
            'interviewed' => __('Interviewed', 'big-bundle'),
            'offered' => __('Offered', 'big-bundle'),
            'hired' => __('Hired', 'big-bundle'),
            'rejected' => __('Rejected', 'big-bundle'),
            'withdrawn' => __('Withdrawn', 'big-bundle'),
            'draft' => __('Draft', 'big-bundle')
        );

        $status_label = $status_labels[$application->application_status] ?? ucfirst(str_replace('_', ' ', $application->application_status));

        // Format application data
        $app_data = $application->application_data ?: array();
        $formatted_data = '';

        if (!empty($app_data)) {
            $form_type = $app_data['form_type'] ?? 'unknown';
            $form_title = $app_data['form_title'] ?? '';

            // Add form information header
            if ($form_title) {
                $formatted_data .= '<div class="application-form-info" style="margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-left: 3px solid #0073aa; border-radius: 4px;">';
                $formatted_data .= '<strong>' . __('Form:', 'big-bundle') . '</strong> ' . esc_html($form_title);
                if ($form_type) {
                    $form_type_label = str_replace('_', ' ', ucwords($form_type, '_'));
                    $formatted_data .= ' <span style="color: #666;">(' . esc_html($form_type_label) . ')</span>';
                }
                $formatted_data .= '</div>';
            }

            $formatted_data .= '<div class="application-fields" style="margin-top: 15px;">';

            // Handle different form types differently
            if ($form_type === 'gravity_forms' && isset($app_data['fields']) && is_array($app_data['fields'])) {
                // Gravity Forms: structured data with label, value, type
                foreach ($app_data['fields'] as $field_id => $field_data) {
                    if (is_array($field_data) && isset($field_data['label']) && !empty($field_data['value'])) {
                        $formatted_data .= $this->format_field_row($field_data['label'], $field_data['value']);
                    }
                }
            } elseif (isset($app_data['fields']) && is_array($app_data['fields'])) {
                // Contact Form 7 and other forms: flat key-value pairs
                foreach ($app_data['fields'] as $key => $value) {
                    // Skip internal/meta fields
                    if (in_array($key, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', 'job-id', 'g-recaptcha-response'])) {
                        continue;
                    }

                    // Skip empty values
                    if (empty($value)) {
                        continue;
                    }

                    // Format the field label from the field name
                    $field_label = $this->format_field_label($key);
                    $formatted_data .= $this->format_field_row($field_label, $value);
                }
            } else {
                // Fallback for unknown structures
                foreach ($app_data as $key => $value) {
                    if (in_array($key, ['form_type', 'form_id', 'form_title', 'entry_id', 'fields']) || empty($value)) {
                        continue;
                    }

                    $field_label = $this->format_field_label($key);
                    $formatted_data .= $this->format_field_row($field_label, $value);
                }
            }

            $formatted_data .= '</div>';
        }

        if (empty($formatted_data) || strpos($formatted_data, 'field-row') === false) {
            $formatted_data = '<p><em>' . __('No additional application data available.', 'big-bundle') . '</em></p>';
        }

        wp_send_json_success(array(
            'applicant_name' => $application->applicant_name,
            'applicant_email' => $application->applicant_email,
            'phone' => $application->applicant_phone,
            'job_title' => $job_title,
            'job_url' => $job_url,
            'status_label' => $status_label,
            'created_date' => $application->created_date,
            'updated_date' => $application->updated_date,
            'formatted_data' => $formatted_data
        ));
    }

    /**
     * AJAX: Update application status
     */
    public function update_application_status_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }
        
        $application_id = intval($_POST['application_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        $allowed_statuses = array(
            'submitted', 'under_review', 'shortlisted', 'interviewed', 
            'offered', 'hired', 'rejected', 'withdrawn'
        );
        
        if (!in_array($new_status, $allowed_statuses)) {
            wp_send_json_error(__('Invalid status.', 'big-bundle'));
        }
        
        $result = $this->update_status($application_id, $new_status);
        
        if ($result) {
            // Send status update email to applicant
            $this->send_status_update_email($application_id, $new_status);
            
            wp_send_json_success(__('Status updated successfully.', 'big-bundle'));
        } else {
            wp_send_json_error(__('Failed to update status.', 'big-bundle'));
        }
    }
    
    /**
     * AJAX: Delete application
     */
    public function delete_application_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }
        
        $application_id = intval($_POST['application_id']);
        
        $result = $this->delete_application($application_id);
        
        if ($result) {
            wp_send_json_success(__('Application deleted successfully.', 'big-bundle'));
        } else {
            wp_send_json_error(__('Failed to delete application.', 'big-bundle'));
        }
    }
    
    /**
     * AJAX: Load saved application
     */
    public function load_saved_application_ajax() {
        check_ajax_referer('bb_recruitment_public_nonce', 'nonce');
        
        $save_token = sanitize_text_field($_POST['save_token'] ?? '');
        
        if (empty($save_token)) {
            wp_send_json_error(__('Invalid save token.', 'big-bundle'));
        }
        
        $application = $this->get_application_by_token($save_token);
        
        if (!$application) {
            wp_send_json_error(__('Saved application not found.', 'big-bundle'));
        }
        
        // Check if application is still draft
        if ($application->application_status !== 'draft') {
            wp_send_json_error(__('This application has already been submitted.', 'big-bundle'));
        }
        
        wp_send_json_success(array(
            'application_data' => $application->application_data,
            'job_id' => $application->job_id
        ));
    }
    
    /**
     * Export applications to CSV
     */
    public function export_applications_csv($args = array()) {
        $applications = $this->get_applications($args);
        
        $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        $headers = array(
            'ID',
            'Job Title',
            'Applicant Name',
            'Email',
            'Phone',
            'Status',
            'Applied Date',
            'Cover Letter',
            'Experience',
            'Availability'
        );
        
        fputcsv($output, $headers);
        
        // CSV data
        foreach ($applications as $application) {
            $job_title = get_the_title($application->job_id);
            $app_data = $application->application_data ?: array();
            
            $row = array(
                $application->id,
                $job_title,
                $application->applicant_name,
                $application->applicant_email,
                $application->applicant_phone,
                ucfirst($application->application_status),
                $application->created_date,
                $app_data['cover_letter'] ?? '',
                $app_data['experience'] ?? '',
                $app_data['availability'] ?? ''
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Export applications
     */
    public function export_applications_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }
        
        $job_id = intval($_POST['job_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $args = array();
        
        if ($job_id > 0) {
            $args['job_id'] = $job_id;
        }
        
        if (!empty($status)) {
            $args['status'] = $status;
        }
        
        $this->export_applications_csv($args);
    }
    
    /**
     * Send status update email to applicant
     */
    private function send_status_update_email($application_id, $new_status) {
        $application = $this->get_application($application_id);

        if (!$application) {
            return false;
        }

        // Use email template manager if available
        if (class_exists('BB_Email_Template_Manager')) {
            $template_manager = BB_Email_Template_Manager::get_instance();

            $data = array(
                'applicant_name' => $application->applicant_name,
                'applicant_email' => $application->applicant_email,
                'job_title' => get_the_title($application->job_id),
                'job_url' => get_permalink($application->job_id),
                'status' => ucfirst(str_replace('_', ' ', $new_status)),
                'application_id' => $application_id
            );

            return $template_manager->send_email($new_status, $application->applicant_email, $data);
        }

        // Fallback to legacy email (for backwards compatibility)
        $job_title = get_the_title($application->job_id);

        $status_messages = array(
            'under_review' => __('Your application is now under review.', 'big-bundle'),
            'shortlisted' => __('Congratulations! You have been shortlisted for this position.', 'big-bundle'),
            'interviewed' => __('Thank you for your interview. We will be in touch soon.', 'big-bundle'),
            'offered' => __('Congratulations! We would like to offer you this position.', 'big-bundle'),
            'hired' => __('Welcome to the team! We look forward to working with you.', 'big-bundle'),
            'rejected' => __('Thank you for your application. Unfortunately, you have not been successful on this occasion.', 'big-bundle'),
            'withdrawn' => __('Your application has been withdrawn as requested.', 'big-bundle')
        );

        $status_message = $status_messages[$new_status] ?? __('Your application status has been updated.', 'big-bundle');

        $subject = sprintf(__('Application Update - %s', 'big-bundle'), $job_title);

        // Build message without escaping apostrophes
        $message = sprintf(
            /* translators: 1: Applicant name, 2: Status message, 3: Job title, 4: Status */
            "Dear %s,\n\n%s\n\nPosition: %s\nStatus: %s\n\nIf you have any questions, please don't hesitate to contact us.\n\nBest regards,\nHR Team",
            $application->applicant_name,
            $status_message,
            $job_title,
            ucfirst(str_replace('_', ' ', $new_status))
        );

        return wp_mail($application->applicant_email, $subject, $message);
    }

    /**
     * Format a field label from a field name/key
     */
    private function format_field_label($key) {
        // Remove common prefixes
        $key = preg_replace('/^(your-|applicant-|user-|job-)/', '', $key);

        // Replace hyphens and underscores with spaces
        $label = str_replace(['-', '_'], ' ', $key);

        // Capitalize each word
        $label = ucwords($label);

        // Handle common abbreviations
        $label = str_replace(['Cv', 'Url', 'Id'], ['CV', 'URL', 'ID'], $label);

        return $label;
    }

    /**
     * Format a field row for display
     */
    private function format_field_row($label, $value) {
        $output = '<div class="field-row" style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e5e5e5;">';
        $output .= '<div class="field-label" style="font-weight: 600; color: #23282d; margin-bottom: 5px;">';
        $output .= esc_html($label);
        $output .= '</div>';
        $output .= '<div class="field-value" style="color: #555; line-height: 1.6;">';

        // Handle different value types
        if (is_array($value)) {
            // For arrays, display as a list or comma-separated
            if (count($value) > 3) {
                // Display as unordered list for longer arrays
                $output .= '<ul style="margin: 5px 0; padding-left: 20px;">';
                foreach ($value as $item) {
                    if (!empty($item)) {
                        $output .= '<li>' . esc_html($item) . '</li>';
                    }
                }
                $output .= '</ul>';
            } else {
                // Display as comma-separated for shorter arrays
                $filtered_values = array_filter($value, function($item) { return !empty($item); });
                $output .= esc_html(implode(', ', $filtered_values));
            }
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            // Display URLs as clickable links
            $output .= '<a href="' . esc_url($value) . '" target="_blank" style="color: #0073aa;">' . esc_html($value) . '</a>';
        } elseif (is_email($value)) {
            // Display emails as mailto links
            $output .= '<a href="mailto:' . esc_attr($value) . '" style="color: #0073aa;">' . esc_html($value) . '</a>';
        } elseif (strlen($value) > 100) {
            // Display long text with line breaks preserved
            $output .= '<div style="white-space: pre-wrap; background: #f9f9f9; padding: 10px; border-radius: 4px;">' . esc_html($value) . '</div>';
        } else {
            // Display short text normally
            $output .= esc_html($value);
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}