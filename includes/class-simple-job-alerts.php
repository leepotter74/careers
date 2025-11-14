<?php
/**
 * Simple Job Alerts using WordPress Posts
 * 
 * Much simpler approach - uses WordPress custom post type as backend
 * but all user interaction stays within Big Bundle frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Simple_Job_Alerts {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Register custom post type
        add_action('init', array($this, 'register_post_type'));
        
        // Handle alert creation from profile page (POST fallback)
        add_action('init', array($this, 'handle_alert_creation'));
        
        // Handle alert management actions (POST fallback)  
        add_action('init', array($this, 'handle_alert_actions'));
        
        // AJAX handlers for smooth user experience
        add_action('wp_ajax_bb_create_simple_alert', array($this, 'ajax_create_alert'));
        add_action('wp_ajax_bb_manage_simple_alert', array($this, 'ajax_manage_alert'));
        
        // Add contextual alert buttons to job pages
        add_action('wp_footer', array($this, 'add_job_alert_button'));
        
        // Check for matching jobs when jobs are published
        add_action('transition_post_status', array($this, 'check_new_job_alerts'), 10, 3);
        
        // Schedule daily email processing
        add_action('bb_process_daily_alerts', array($this, 'process_daily_alerts'));
        if (!wp_next_scheduled('bb_process_daily_alerts')) {
            wp_schedule_event(time(), 'daily', 'bb_process_daily_alerts');
        }
    }
    
    /**
     * Register job_alert custom post type (hidden from admin)
     */
    public function register_post_type() {
        register_post_type('job_alert', array(
            'labels' => array(
                'name' => 'Job Alerts',
                'singular_name' => 'Job Alert'
            ),
            'public' => false,           // Hidden from frontend
            'show_ui' => false,          // Hidden from admin (unless needed for debugging)
            'show_in_menu' => false,     // Not in admin menu
            'supports' => array('title', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true
        ));
    }
    
    /**
     * Create a new job alert (creates a WordPress post)
     */
    public function create_alert($user_id, $alert_data) {
        $post_data = array(
            'post_type' => 'job_alert',
            'post_title' => sanitize_text_field($alert_data['name']),
            'post_author' => $user_id,
            'post_status' => 'publish',
            'meta_input' => array(
                '_alert_keywords' => sanitize_textarea_field($alert_data['keywords']),
                '_alert_categories' => array_map('intval', $alert_data['categories']),
                '_alert_locations' => array_map('intval', $alert_data['locations']),
                '_alert_frequency' => sanitize_text_field($alert_data['frequency']),
                '_alert_type' => sanitize_text_field($alert_data['type']),
                '_alert_active' => 1,
                '_alert_last_sent' => ''
            )
        );
        
        $alert_id = wp_insert_post($post_data);
        
        if (is_wp_error($alert_id)) {
            return false;
        }
        
        return $alert_id;
    }
    
    /**
     * Get user's job alerts
     */
    public function get_user_alerts($user_id) {
        $alerts = get_posts(array(
            'post_type' => 'job_alert',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        // Add meta data to each alert
        foreach ($alerts as $alert) {
            $alert->keywords = get_post_meta($alert->ID, '_alert_keywords', true);
            $alert->categories = get_post_meta($alert->ID, '_alert_categories', true) ?: array();
            $alert->locations = get_post_meta($alert->ID, '_alert_locations', true) ?: array();
            $alert->frequency = get_post_meta($alert->ID, '_alert_frequency', true);
            $alert->alert_type = get_post_meta($alert->ID, '_alert_type', true);
            $alert->is_active = get_post_meta($alert->ID, '_alert_active', true);
        }
        
        return $alerts;
    }
    
    /**
     * Update an alert
     */
    public function update_alert($alert_id, $user_id, $alert_data) {
        // Verify ownership
        $alert = get_post($alert_id);
        if (!$alert || $alert->post_author != $user_id) {
            return false;
        }
        
        // Update post title
        wp_update_post(array(
            'ID' => $alert_id,
            'post_title' => sanitize_text_field($alert_data['name'])
        ));
        
        // Update meta fields
        update_post_meta($alert_id, '_alert_keywords', sanitize_textarea_field($alert_data['keywords']));
        update_post_meta($alert_id, '_alert_categories', array_map('intval', $alert_data['categories']));
        update_post_meta($alert_id, '_alert_locations', array_map('intval', $alert_data['locations']));
        update_post_meta($alert_id, '_alert_frequency', sanitize_text_field($alert_data['frequency']));
        
        return true;
    }
    
    /**
     * Delete an alert
     */
    public function delete_alert($alert_id, $user_id) {
        // Verify ownership
        $alert = get_post($alert_id);
        if (!$alert || $alert->post_author != $user_id) {
            return false;
        }
        
        return wp_delete_post($alert_id, true);
    }
    
    /**
     * Toggle alert active status
     */
    public function toggle_alert($alert_id, $user_id) {
        // Verify ownership
        $alert = get_post($alert_id);
        if (!$alert || $alert->post_author != $user_id) {
            return false;
        }
        
        $current_status = get_post_meta($alert_id, '_alert_active', true);
        $new_status = $current_status ? 0 : 1;
        
        update_post_meta($alert_id, '_alert_active', $new_status);
        
        return true;
    }
    
    /**
     * Check if a job matches alert criteria
     */
    private function job_matches_alert($job_id, $alert) {
        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_vacancy') {
            return false;
        }
        
        // Check categories
        if (!empty($alert->categories)) {
            $job_categories = wp_get_post_terms($job_id, 'job_category', array('fields' => 'ids'));
            $matching_categories = array_intersect($alert->categories, $job_categories);
            if (empty($matching_categories)) {
                return false;
            }
        }
        
        // Check locations
        if (!empty($alert->locations)) {
            $job_locations = wp_get_post_terms($job_id, 'job_location', array('fields' => 'ids'));
            $matching_locations = array_intersect($alert->locations, $job_locations);
            if (empty($matching_locations)) {
                return false;
            }
        }
        
        // Check keywords
        if (!empty($alert->keywords)) {
            $content_to_search = strtolower($job->post_title . ' ' . $job->post_content);
            $keywords = array_map('trim', explode(',', strtolower($alert->keywords)));
            
            foreach ($keywords as $keyword) {
                if (!empty($keyword) && strpos($content_to_search, $keyword) !== false) {
                    return true; // Found at least one keyword
                }
            }
            return false; // No keywords found
        }
        
        return true; // Matches all criteria
    }
    
    /**
     * Handle alert creation from POST request
     */
    public function handle_alert_creation() {
        if (!isset($_POST['bb_create_alert']) || !is_user_logged_in()) {
            return;
        }
        
        // Don't handle if this is an AJAX request
        if (wp_doing_ajax()) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_bb_alert_nonce'], 'bb_create_alert_' . get_current_user_id())) {
            wp_die(__('Security check failed.', 'big-bundle'));
        }
        
        $user_id = get_current_user_id();
        
        $alert_data = array(
            'name' => $_POST['alert_name'] ?: 'Unnamed Alert',
            'keywords' => $_POST['alert_keywords'] ?: '',
            'categories' => $_POST['alert_categories'] ?: array(),
            'locations' => $_POST['alert_locations'] ?: array(),
            'frequency' => $_POST['alert_frequency'] ?: 'immediate',
            'type' => $_POST['alert_type'] ?: 'profile'
        );
        
        $alert_id = $this->create_alert($user_id, $alert_data);
        
        if ($alert_id) {
            $redirect_url = add_query_arg('alert_created', '1', wp_get_referer());
            wp_redirect($redirect_url);
            exit;
        } else {
            $redirect_url = add_query_arg('alert_error', '1', wp_get_referer());
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Handle alert management actions (edit, delete, toggle)
     */
    public function handle_alert_actions() {
        if (!isset($_POST['bb_alert_action']) || !is_user_logged_in()) {
            return;
        }
        
        // Don't handle if this is an AJAX request
        if (wp_doing_ajax()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $action = $_POST['bb_alert_action'];
        $alert_id = intval($_POST['alert_id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_bb_alert_nonce'], 'bb_alert_action_' . $user_id)) {
            wp_die(__('Security check failed.', 'big-bundle'));
        }
        
        $success = false;
        
        switch ($action) {
            case 'delete':
                $success = $this->delete_alert($alert_id, $user_id);
                $message = $success ? 'deleted' : 'delete_error';
                break;
                
            case 'toggle':
                $success = $this->toggle_alert($alert_id, $user_id);
                $message = $success ? 'toggled' : 'toggle_error';
                break;
                
            case 'update':
                $alert_data = array(
                    'name' => $_POST['alert_name'] ?: 'Unnamed Alert',
                    'keywords' => $_POST['alert_keywords'] ?: '',
                    'categories' => $_POST['alert_categories'] ?: array(),
                    'locations' => $_POST['alert_locations'] ?: array(),
                    'frequency' => $_POST['alert_frequency'] ?: 'immediate'
                );
                $success = $this->update_alert($alert_id, $user_id, $alert_data);
                $message = $success ? 'updated' : 'update_error';
                break;
        }
        
        $redirect_url = add_query_arg('alert_' . $message, '1', wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Check for new job alerts when jobs are published
     */
    public function check_new_job_alerts($new_status, $old_status, $post) {
        if ($post->post_type !== 'job_vacancy' || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        
        // Get all active immediate alerts
        $alerts = get_posts(array(
            'post_type' => 'job_alert',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_alert_frequency',
                    'value' => 'immediate'
                ),
                array(
                    'key' => '_alert_active',
                    'value' => 1
                )
            )
        ));
        
        
        foreach ($alerts as $alert) {
            // Add meta data
            $alert->keywords = get_post_meta($alert->ID, '_alert_keywords', true);
            $alert->categories = get_post_meta($alert->ID, '_alert_categories', true) ?: array();
            $alert->locations = get_post_meta($alert->ID, '_alert_locations', true) ?: array();
            
            if ($this->job_matches_alert($post->ID, $alert)) {
                $this->send_alert_email($alert->post_author, $post->ID, $alert->post_title);
                update_post_meta($alert->ID, '_alert_last_sent', current_time('mysql'));
            }
        }
    }
    
    /**
     * Send alert email
     */
    private function send_alert_email($user_id, $job_id, $alert_name) {
        $user = get_userdata($user_id);
        $job = get_post($job_id);
        
        if (!$user || !$job) {
            return false;
        }
        
        $subject = sprintf(__('Job Alert: %s', 'big-bundle'), $job->post_title);
        
        $message = sprintf(__("Hello %s,\n\nA new job has been posted that matches your alert '%s':\n\n", 'big-bundle'), $user->display_name, $alert_name);
        $message .= sprintf(__("Job Title: %s\n\n", 'big-bundle'), $job->post_title);
        $message .= sprintf(__("View and Apply: %s\n\n", 'big-bundle'), get_permalink($job_id));
        $message .= __("Best regards,\nHR Team", 'big-bundle');
        
        // Set up headers to CC admin for debugging
        $admin_email = get_option('admin_email');
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Cc: ' . $admin_email
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send digest email with multiple jobs
     */
    private function send_digest_email($user_id, $jobs, $alert_name, $frequency) {
        $user = get_userdata($user_id);
        if (!$user || empty($jobs)) return false;
        
        $job_count = count($jobs);
        $freq_text = ucfirst($frequency);
        $subject = sprintf(__('%s Job Alert: %d new job%s', 'big-bundle'), $freq_text, $job_count, $job_count > 1 ? 's' : '');
        
        $message = sprintf(__("Hello %s,\n\nYour %s job alert '%s' found %d new job%s:\n\n", 'big-bundle'), 
            $user->display_name, strtolower($frequency), $alert_name, $job_count, $job_count > 1 ? 's' : '');
        
        foreach ($jobs as $job) {
            $message .= sprintf(__("• %s\n  %s\n\n", 'big-bundle'), $job->post_title, get_permalink($job->ID));
        }
        
        $message .= __("\nBest regards,\nHR Team", 'big-bundle');
        
        $admin_email = get_option('admin_email');
        $headers = array('Content-Type: text/plain; charset=UTF-8', 'Cc: ' . $admin_email);
        
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Add contextual alert button to job pages
     */
    public function add_job_alert_button() {
        if (!is_singular('job_vacancy') || !is_user_logged_in()) {
            return;
        }
        
        global $post;
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add alert button after job content
            var $alertButton = $('<div style="margin: 20px 0; text-align: center; padding: 15px; background: #f0f8ff; border: 1px solid #bee5eb; border-radius: 6px;">' +
                '<button type="button" onclick="createJobAlert()" style="background: #0073aa; color: white; border: none; padding: 12px 20px; border-radius: 4px; font-size: 16px; cursor: pointer;">' +
                '🔔 Get alerts for similar jobs</button>' +
                '</div>');
            
            $('.entry-content, .job-content, .job-description').first().after($alertButton);
        });
        
        function createJobAlert() {
            var confirmed = confirm('Create an alert to be notified when similar jobs are posted?\n\nThis will take you to your profile to set up the alert.');
            if (confirmed) {
                <?php
                // Find profile page
                global $wpdb;
                $profile_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[bb_user_profile]%' AND post_type = 'page' AND post_status = 'publish' LIMIT 1");
                $profile_url = $profile_page_id ? get_permalink($profile_page_id) : home_url('/profile/');
                ?>
                window.location.href = '<?php echo add_query_arg('create_alert_for', $post->ID, $profile_url); ?>';
            }
        }
        </script>
        <?php
    }
    
    /**
     * Process daily/weekly alerts
     */
    public function process_daily_alerts() {
        
        // Get jobs published in the last 24 hours
        $recent_jobs = get_posts(array(
            'post_type' => 'job_vacancy',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => '1 day ago',
                    'inclusive' => true,
                )
            )
        ));
        
        if (empty($recent_jobs)) {
            return;
        }
        
        // Get all active daily alerts
        $daily_alerts = get_posts(array(
            'post_type' => 'job_alert',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_alert_frequency',
                    'value' => 'daily'
                ),
                array(
                    'key' => '_alert_active',
                    'value' => 1
                )
            )
        ));
        
        // Get all active weekly alerts (if it's Monday)
        $weekly_alerts = array();
        if (date('N') == 1) { // Monday
            $weekly_alerts = get_posts(array(
                'post_type' => 'job_alert',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => '_alert_frequency',
                        'value' => 'weekly'
                    ),
                    array(
                        'key' => '_alert_active',
                        'value' => 1
                    )
                )
            ));
        }
        
        $all_alerts = array_merge($daily_alerts, $weekly_alerts);
        
        
        // Process each alert
        foreach ($all_alerts as $alert) {
            $alert->keywords = get_post_meta($alert->ID, '_alert_keywords', true);
            $alert->categories = get_post_meta($alert->ID, '_alert_categories', true) ?: array();
            $alert->locations = get_post_meta($alert->ID, '_alert_locations', true) ?: array();
            $alert->frequency = get_post_meta($alert->ID, '_alert_frequency', true);
            
            // Find matching jobs for this alert
            $matching_jobs = array();
            foreach ($recent_jobs as $job) {
                if ($this->job_matches_alert($job->ID, $alert)) {
                    $matching_jobs[] = $job;
                }
            }
            
            // Send digest email if there are matching jobs
            if (!empty($matching_jobs)) {
                $this->send_digest_email($alert->post_author, $matching_jobs, $alert->post_title, $alert->frequency);
                update_post_meta($alert->ID, '_alert_last_sent', current_time('mysql'));
            }
        }
    }
    
    /**
     * AJAX: Create alert smoothly
     */
    public function ajax_create_alert() {
        // Security checks
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in to create job alerts.', 'big-bundle'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'bb_create_alert_' . get_current_user_id())) {
            wp_send_json_error(__('Security check failed.', 'big-bundle'));
        }
        
        $user_id = get_current_user_id();
        
        // Handle array fields properly
        $categories = array();
        if (isset($_POST['alert_categories']) && is_array($_POST['alert_categories'])) {
            $categories = array_map('intval', $_POST['alert_categories']);
        }
        
        $locations = array();
        if (isset($_POST['alert_locations']) && is_array($_POST['alert_locations'])) {
            $locations = array_map('intval', $_POST['alert_locations']);
        }
        
        $alert_data = array(
            'name' => sanitize_text_field($_POST['alert_name'] ?? '') ?: 'Unnamed Alert',
            'keywords' => sanitize_textarea_field($_POST['alert_keywords'] ?? ''),
            'categories' => $categories,
            'locations' => $locations,
            'frequency' => sanitize_text_field($_POST['alert_frequency'] ?? 'immediate'),
            'type' => sanitize_text_field($_POST['alert_type'] ?? 'profile')
        );
        
        $alert_id = $this->create_alert($user_id, $alert_data);
        
        if ($alert_id) {
            // Get the new alert data to return
            $new_alert = get_post($alert_id);
            $new_alert->keywords = get_post_meta($alert_id, '_alert_keywords', true);
            $new_alert->frequency = get_post_meta($alert_id, '_alert_frequency', true);
            $new_alert->is_active = get_post_meta($alert_id, '_alert_active', true);
            
            wp_send_json_success(array(
                'message' => __('Job alert created successfully!', 'big-bundle'),
                'alert' => $new_alert
            ));
        } else {
            wp_send_json_error(__('Failed to create job alert.', 'big-bundle'));
        }
    }
    
    /**
     * AJAX: Manage alerts (delete, toggle)
     */
    public function ajax_manage_alert() {
        // Security checks
        if (!wp_verify_nonce($_POST['nonce'], 'bb_alert_action_' . get_current_user_id())) {
            wp_send_json_error(__('Security check failed.', 'big-bundle'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in to manage job alerts.', 'big-bundle'));
        }
        
        $user_id = get_current_user_id();
        $action = sanitize_text_field($_POST['action_type']);
        $alert_id = intval($_POST['alert_id']);
        
        $success = false;
        $message = '';
        
        switch ($action) {
            case 'delete':
                $success = $this->delete_alert($alert_id, $user_id);
                $message = $success ? __('Job alert deleted successfully!', 'big-bundle') : __('Failed to delete alert.', 'big-bundle');
                break;
                
            case 'toggle':
                $success = $this->toggle_alert($alert_id, $user_id);
                $message = $success ? __('Job alert status updated!', 'big-bundle') : __('Failed to update alert status.', 'big-bundle');
                break;
        }
        
        if ($success) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error($message);
        }
    }
    
    /**
     * Extract keywords from job for contextual alerts
     */
    public function extract_keywords_from_job($job) {
        if (!$job) return '';
        
        $title_words = str_word_count(strtolower($job->post_title), 1);
        
        // Common words to exclude
        $exclude = array('job', 'position', 'role', 'work', 'the', 'and', 'or', 'in', 'at', 'for', 'to', 'of', 'with');
        
        // Get meaningful words from title
        $keywords = array_filter($title_words, function($word) use ($exclude) {
            return strlen($word) > 3 && !in_array($word, $exclude);
        });
        
        return implode(', ', array_unique(array_slice($keywords, 0, 5)));
    }
}