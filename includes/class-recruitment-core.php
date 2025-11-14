<?php
/**
 * Recruitment Manager Core Class
 * 
 * Handles core functionality for the Recruitment Manager module
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruitment_Core {
    
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
        // Register shortcodes
        add_shortcode('job_listings', array($this, 'render_job_listings'));
        add_shortcode('job_application_form', array($this, 'render_application_form'));
        
        // AJAX handlers for public forms
        add_action('wp_ajax_submit_job_application', array($this, 'handle_application_submission'));
        add_action('wp_ajax_nopriv_submit_job_application', array($this, 'handle_application_submission'));
        
        add_action('wp_ajax_save_job_application', array($this, 'handle_application_save'));
        add_action('wp_ajax_nopriv_save_job_application', array($this, 'handle_application_save'));
        
        add_action('wp_ajax_load_saved_application', array($this, 'handle_load_saved_application'));
        add_action('wp_ajax_nopriv_load_saved_application', array($this, 'handle_load_saved_application'));
        
        // Cron job for expired jobs
        add_action('bb_recruitment_check_expired_jobs', array($this, 'check_expired_jobs'));
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('bb_recruitment_check_expired_jobs')) {
            wp_schedule_event(time(), 'daily', 'bb_recruitment_check_expired_jobs');
        }
    }
    
    /**
     * Render job listings shortcode
     */
    public function render_job_listings($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'location' => '',
            'limit' => 10,
            'show_expired' => false
        ), $atts);
        
        $args = array(
            'post_type' => 'job_vacancy',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'meta_query' => array()
        );
        
        // Hide expired jobs unless specifically requested
        if (!$atts['show_expired']) {
            $args['meta_query'][] = array(
                'key' => '_job_closing_date',
                'value' => date('Y-m-d'),
                'compare' => '>',
                'type' => 'DATE'
            );
        }
        
        // Add category filter if specified
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'job_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }
        
        $jobs = new WP_Query($args);
        
        ob_start();
        if ($jobs->have_posts()) {
            echo '<div class="bb-job-listings">';
            while ($jobs->have_posts()) {
                $jobs->the_post();
                $this->render_job_listing_item();
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="bb-no-jobs">' . __('No job vacancies currently available.', 'big-bundle') . '</p>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render individual job listing item
     */
    private function render_job_listing_item() {
        $job_id = get_the_ID();
        $closing_date = get_post_meta($job_id, '_job_closing_date', true);
        $location = get_post_meta($job_id, '_job_location', true);
        $job_type = get_post_meta($job_id, '_job_type', true);
        
        ?>
        <div class="bb-job-item" data-job-id="<?php echo esc_attr($job_id); ?>">
            <div class="bb-job-header">
                <h3 class="bb-job-title">
                    <a href="<?php echo esc_url(get_permalink()); ?>"><?php the_title(); ?></a>
                </h3>
                <div class="bb-job-meta">
                    <?php if ($location): ?>
                        <span class="bb-job-location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                    <?php if ($job_type): ?>
                        <span class="bb-job-type"><?php echo esc_html($job_type); ?></span>
                    <?php endif; ?>
                    <?php if ($closing_date): ?>
                        <span class="bb-job-closing">
                            <?php printf(__('Closes: %s', 'big-bundle'), date('d/m/Y', strtotime($closing_date))); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bb-job-excerpt">
                <?php the_excerpt(); ?>
            </div>
            
            <div class="bb-job-actions">
                <a href="<?php echo esc_url(get_permalink()); ?>" class="bb-btn bb-btn-primary">
                    <?php _e('View Details', 'big-bundle'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('apply', '1', get_permalink())); ?>" class="bb-btn bb-btn-secondary">
                    <?php _e('Apply Now', 'big-bundle'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render job application form shortcode
     */
    public function render_application_form($atts) {
        $atts = shortcode_atts(array(
            'job_id' => get_the_ID()
        ), $atts);
        
        $job_id = intval($atts['job_id']);
        
        if (!$job_id || get_post_type($job_id) !== 'job_vacancy') {
            return '<p class="bb-error">' . __('Invalid job vacancy.', 'big-bundle') . '</p>';
        }
        
        // Check if job is still open
        $closing_date = get_post_meta($job_id, '_job_closing_date', true);
        if ($closing_date && strtotime($closing_date) < time()) {
            return '<p class="bb-error">' . __('This job vacancy has closed.', 'big-bundle') . '</p>';
        }
        
        ob_start();
        $this->render_application_form_template($job_id);
        return ob_get_clean();
    }
    
    /**
     * Render application form template
     */
    private function render_application_form_template($job_id) {
        $current_user = wp_get_current_user();
        $user_data = array();
        
        // Pre-populate for logged-in users
        if ($current_user->ID > 0) {
            $user_data = array(
                'name' => $current_user->display_name,
                'email' => $current_user->user_email,
                'phone' => get_user_meta($current_user->ID, '_bb_phone', true)
            );
        }
        
        ?>
        <form id="bb-job-application-form" class="bb-application-form" data-job-id="<?php echo esc_attr($job_id); ?>">
            <?php wp_nonce_field('bb_recruitment_public_nonce', 'bb_recruitment_nonce'); ?>
            
            <div class="bb-form-header">
                <h3><?php _e('Apply for this position', 'big-bundle'); ?></h3>
                <p><?php printf(__('Application for: %s', 'big-bundle'), get_the_title($job_id)); ?></p>
            </div>
            
            <div class="bb-form-section">
                <h4><?php _e('Personal Information', 'big-bundle'); ?></h4>
                
                <div class="bb-form-row">
                    <label for="applicant_name"><?php _e('Full Name *', 'big-bundle'); ?></label>
                    <input type="text" id="applicant_name" name="applicant_name" 
                           value="<?php echo esc_attr($user_data['name'] ?? ''); ?>" required>
                </div>
                
                <div class="bb-form-row">
                    <label for="applicant_email"><?php _e('Email Address *', 'big-bundle'); ?></label>
                    <input type="email" id="applicant_email" name="applicant_email" 
                           value="<?php echo esc_attr($user_data['email'] ?? ''); ?>" required>
                </div>
                
                <div class="bb-form-row">
                    <label for="applicant_phone"><?php _e('Phone Number', 'big-bundle'); ?></label>
                    <input type="tel" id="applicant_phone" name="applicant_phone" 
                           value="<?php echo esc_attr($user_data['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="bb-form-section">
                <h4><?php _e('Application Details', 'big-bundle'); ?></h4>
                
                <div class="bb-form-row">
                    <label for="cover_letter"><?php _e('Cover Letter / Why are you interested in this role? *', 'big-bundle'); ?></label>
                    <textarea id="cover_letter" name="cover_letter" rows="6" required></textarea>
                </div>
                
                <div class="bb-form-row">
                    <label for="experience"><?php _e('Relevant Experience', 'big-bundle'); ?></label>
                    <textarea id="experience" name="experience" rows="4"></textarea>
                </div>
                
                <div class="bb-form-row">
                    <label for="availability"><?php _e('Availability / Start Date', 'big-bundle'); ?></label>
                    <input type="text" id="availability" name="availability">
                </div>
            </div>
            
            <div class="bb-form-section">
                <div class="bb-form-row">
                    <label>
                        <input type="checkbox" id="data_consent" name="data_consent" required>
                        <?php _e('I consent to my personal data being stored and processed for the purpose of this job application *', 'big-bundle'); ?>
                    </label>
                </div>
                
                <div class="bb-form-row">
                    <label>
                        <input type="checkbox" id="marketing_consent" name="marketing_consent">
                        <?php _e('I would like to receive updates about future job opportunities', 'big-bundle'); ?>
                    </label>
                </div>
            </div>
            
            <div class="bb-form-actions">
                <button type="button" id="bb-save-application" class="bb-btn bb-btn-outline">
                    <?php _e('Save & Continue Later', 'big-bundle'); ?>
                </button>
                <button type="submit" id="bb-submit-application" class="bb-btn bb-btn-primary">
                    <?php _e('Submit Application', 'big-bundle'); ?>
                </button>
            </div>
            
            <div id="bb-application-messages"></div>
        </form>
        <?php
    }
    
    /**
     * Handle application submission
     */
    public function handle_application_submission() {
        check_ajax_referer('bb_recruitment_public_nonce', 'bb_recruitment_nonce');
        
        $job_id = intval($_POST['job_id']);
        $application_data = $this->sanitize_application_data($_POST);
        
        // Validate required fields
        $validation = $this->validate_application_data($application_data);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        
        // Save application to database
        $application_id = $this->save_application($job_id, $application_data, 'submitted');
        
        if ($application_id) {
            // Send email notifications
            $this->send_application_notifications($application_id, $job_id, $application_data);
            
            wp_send_json_success(array(
                'message' => __('Application submitted successfully!', 'big-bundle'),
                'application_id' => $application_id
            ));
        } else {
            wp_send_json_error(__('Failed to submit application. Please try again.', 'big-bundle'));
        }
    }
    
    /**
     * Handle application save (for later)
     */
    public function handle_application_save() {
        check_ajax_referer('bb_recruitment_public_nonce', 'bb_recruitment_nonce');
        
        $job_id = intval($_POST['job_id']);
        $application_data = $this->sanitize_application_data($_POST);
        
        // Generate save token
        $save_token = wp_generate_uuid4();
        
        // Save application as draft
        $application_id = $this->save_application($job_id, $application_data, 'draft', $save_token);
        
        if ($application_id) {
            wp_send_json_success(array(
                'message' => __('Application saved successfully!', 'big-bundle'),
                'save_token' => $save_token,
                'continue_url' => add_query_arg(array(
                    'continue_application' => $save_token,
                    'job_id' => $job_id
                ), get_permalink($job_id))
            ));
        } else {
            wp_send_json_error(__('Failed to save application. Please try again.', 'big-bundle'));
        }
    }
    
    /**
     * Check for expired jobs and update their status
     */
    public function check_expired_jobs() {
        $args = array(
            'post_type' => 'job_vacancy',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_job_closing_date',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                )
            )
        );
        
        $expired_jobs = new WP_Query($args);
        
        if ($expired_jobs->have_posts()) {
            while ($expired_jobs->have_posts()) {
                $expired_jobs->the_post();
                // Update post status to expired
                wp_update_post(array(
                    'ID' => get_the_ID(),
                    'post_status' => 'expired'
                ));
            }
            wp_reset_postdata();
        }
    }
    
    /**
     * Sanitize application data
     */
    private function sanitize_application_data($data) {
        return array(
            'applicant_name' => sanitize_text_field($data['applicant_name'] ?? ''),
            'applicant_email' => sanitize_email($data['applicant_email'] ?? ''),
            'applicant_phone' => sanitize_text_field($data['applicant_phone'] ?? ''),
            'cover_letter' => sanitize_textarea_field($data['cover_letter'] ?? ''),
            'experience' => sanitize_textarea_field($data['experience'] ?? ''),
            'availability' => sanitize_text_field($data['availability'] ?? ''),
            'data_consent' => isset($data['data_consent']) ? 1 : 0,
            'marketing_consent' => isset($data['marketing_consent']) ? 1 : 0
        );
    }
    
    /**
     * Validate application data
     */
    private function validate_application_data($data) {
        if (empty($data['applicant_name'])) {
            return array('valid' => false, 'message' => __('Name is required.', 'big-bundle'));
        }
        
        if (empty($data['applicant_email']) || !is_email($data['applicant_email'])) {
            return array('valid' => false, 'message' => __('Valid email address is required.', 'big-bundle'));
        }
        
        if (empty($data['cover_letter'])) {
            return array('valid' => false, 'message' => __('Cover letter is required.', 'big-bundle'));
        }
        
        if (!$data['data_consent']) {
            return array('valid' => false, 'message' => __('Data consent is required.', 'big-bundle'));
        }
        
        return array('valid' => true);
    }
    
    /**
     * Save application to database
     */
    private function save_application($job_id, $application_data, $status = 'submitted', $save_token = null) {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        
        $data = array(
            'job_id' => $job_id,
            'user_id' => $current_user_id > 0 ? $current_user_id : null,
            'applicant_name' => $application_data['applicant_name'],
            'applicant_email' => $application_data['applicant_email'],
            'applicant_phone' => $application_data['applicant_phone'],
            'application_data' => json_encode($application_data),
            'application_status' => $status,
            'save_token' => $save_token
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'recruitment_applications',
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Send email notifications
     */
    private function send_application_notifications($application_id, $job_id, $application_data) {
        // Send to applicant
        $applicant_subject = sprintf(__('Application Received - %s', 'big-bundle'), get_the_title($job_id));
        $applicant_message = sprintf(
            __("Dear %s,\n\nThank you for your application for the position of %s.\n\nWe have received your application and will review it shortly. We will be in touch if your application is successful.\n\nThank you for your interest in our organization.\n\nBest regards,\nHR Team", 'big-bundle'),
            $application_data['applicant_name'],
            get_the_title($job_id)
        );
        
        wp_mail($application_data['applicant_email'], $applicant_subject, $applicant_message);
        
        // Send to HR/Admin
        $admin_emails = $this->get_admin_notification_emails();
        if (!empty($admin_emails)) {
            $admin_subject = sprintf(__('New Job Application - %s', 'big-bundle'), get_the_title($job_id));
            $admin_message = sprintf(
                __("A new job application has been received.\n\nPosition: %s\nApplicant: %s\nEmail: %s\nPhone: %s\n\nView all applications: %s", 'big-bundle'),
                get_the_title($job_id),
                $application_data['applicant_name'],
                $application_data['applicant_email'],
                $application_data['applicant_phone'],
                admin_url('admin.php?page=bb-recruitment-applications')
            );
            
            wp_mail($admin_emails, $admin_subject, $admin_message);
        }
    }
    
    /**
     * Get admin notification email addresses
     */
    private function get_admin_notification_emails() {
        $emails = get_option('bb_recruitment_notification_emails', array());
        
        if (empty($emails)) {
            // Fallback to admin email
            $emails = array(get_option('admin_email'));
        }
        
        return $emails;
    }
}