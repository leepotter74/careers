<?php
/**
 * User Profile Class
 * 
 * Handles user account integration and profile data for applications
 */

if (!defined('ABSPATH')) {
    exit;
}

class User_Profile {
    
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
        // Add profile fields
        add_action('show_user_profile', array($this, 'add_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_profile_fields'));
        add_action('user_new_form', array($this, 'add_profile_fields'));
        
        // Save profile fields
        add_action('personal_options_update', array($this, 'save_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
        add_action('user_register', array($this, 'save_profile_fields'));
        
        // AJAX handlers
        add_action('wp_ajax_bb_save_user_application_data', array($this, 'save_user_application_data_ajax'));
        add_action('wp_ajax_nopriv_bb_save_user_application_data', array($this, 'save_user_application_data_ajax'));
        add_action('wp_ajax_bb_get_user_application_data', array($this, 'get_user_application_data_ajax'));
        add_action('wp_ajax_nopriv_bb_get_user_application_data', array($this, 'get_user_application_data_ajax'));
        add_action('wp_ajax_bb_get_application_details', 'bb_get_application_details_global');
        add_action('wp_ajax_nopriv_bb_get_application_details', 'bb_get_application_details_global');
        add_action('wp_ajax_bb_test_ajax', array($this, 'test_ajax'));
        
        // Registration form fields (if registration is enabled)
        if (get_option('users_can_register')) {
            add_action('register_form', array($this, 'add_registration_fields'));
            add_filter('registration_errors', array($this, 'validate_registration_fields'), 10, 3);
        }
        
        // Login/logout redirects for job applications
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);
        
        // Gravity Forms auto-population hooks
        if (class_exists('GFAPI')) {
            add_filter('gform_field_value', array($this, 'populate_gravity_form_field'), 10, 3);
            add_filter('gform_pre_render', array($this, 'populate_gravity_form'), 10, 3);
            
            // Add specific field value filters for common field names
            add_filter('gform_field_value_first_name', array($this, 'populate_first_name'));
            add_filter('gform_field_value_last_name', array($this, 'populate_last_name'));
            add_filter('gform_field_value_name', array($this, 'populate_full_name'));
            add_filter('gform_field_value_email', array($this, 'populate_email'));
            add_filter('gform_field_value_phone', array($this, 'populate_phone'));
            
            // Address field components (for complex address fields)
            add_filter('gform_field_value_street_address', array($this, 'populate_address'));
            add_filter('gform_field_value_address_line_2', array($this, 'populate_address_line_2'));
            add_filter('gform_field_value_city', array($this, 'populate_city'));
            add_filter('gform_field_value_state', array($this, 'populate_county'));
            add_filter('gform_field_value_zip', array($this, 'populate_postcode'));
            add_filter('gform_field_value_country', array($this, 'populate_country'));
            
            // Alternative naming conventions
            add_filter('gform_field_value_address', array($this, 'populate_address'));
            add_filter('gform_field_value_county', array($this, 'populate_county'));
            add_filter('gform_field_value_postcode', array($this, 'populate_postcode'));
        }
        
        // Frontend profile management
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('bb_user_profile', array($this, 'user_profile_shortcode'));
        add_action('wp_ajax_bb_update_user_profile', array($this, 'ajax_update_user_profile'));
        add_action('wp_ajax_nopriv_bb_register_user', array($this, 'ajax_register_user'));
    }
    
    /**
     * Add profile fields to user profile page
     */
    public function add_profile_fields($user) {
        $user_id = is_object($user) ? $user->ID : 0;
        
        ?>
        <h3><?php _e('Job Application Information', 'big-bundle'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="bb_phone"><?php _e('Phone Number', 'big-bundle'); ?></label></th>
                <td>
                    <input type="tel" name="bb_phone" id="bb_phone" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_phone', true)); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Used to pre-fill job applications.', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bb_current_position"><?php _e('Current Position', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_current_position" id="bb_current_position" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_current_position', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_experience_summary"><?php _e('Experience Summary', 'big-bundle'); ?></label></th>
                <td>
                    <textarea name="bb_experience_summary" id="bb_experience_summary" 
                              rows="4" class="large-text"><?php echo esc_textarea(get_user_meta($user_id, 'bb_experience_summary', true)); ?></textarea>
                    <p class="description"><?php _e('Brief summary of your professional experience.', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bb_qualifications"><?php _e('Qualifications', 'big-bundle'); ?></label></th>
                <td>
                    <textarea name="bb_qualifications" id="bb_qualifications" 
                              rows="3" class="large-text"><?php echo esc_textarea(get_user_meta($user_id, 'bb_qualifications', true)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="bb_availability"><?php _e('General Availability', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_availability" id="bb_availability" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_availability', true)); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('e.g., "Available immediately", "2 weeks notice required"', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bb_job_alerts"><?php _e('Job Alerts', 'big-bundle'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="bb_job_alerts" id="bb_job_alerts" value="1" 
                               <?php checked(get_user_meta($user_id, 'bb_job_alerts', true), 1); ?> />
                        <?php _e('Email me about new job opportunities', 'big-bundle'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Personal Details (for auto-filling applications)', 'big-bundle'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="bb_profile_first_name"><?php _e('First Name', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_profile_first_name" id="bb_profile_first_name" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_first_name', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_last_name"><?php _e('Last Name', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_profile_last_name" id="bb_profile_last_name" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_last_name', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_address"><?php _e('Address', 'big-bundle'); ?></label></th>
                <td>
                    <textarea name="bb_profile_address" id="bb_profile_address" 
                              rows="3" class="large-text"><?php echo esc_textarea(get_user_meta($user_id, 'bb_profile_address', true)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_city"><?php _e('City', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_profile_city" id="bb_profile_city" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_city', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_county"><?php _e('County', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_profile_county" id="bb_profile_county" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_county', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_postcode"><?php _e('Postcode', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" name="bb_profile_postcode" id="bb_profile_postcode" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_postcode', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="bb_profile_country"><?php _e('Country', 'big-bundle'); ?></label></th>
                <td>
                    <select name="bb_profile_country" id="bb_profile_country" class="regular-text">
                        <option value="">Select Country</option>
                        <?php
                        $countries = array(
                            'United Kingdom' => 'United Kingdom',
                            'Ireland' => 'Ireland', 
                            'United States' => 'United States',
                            'Canada' => 'Canada',
                            'Australia' => 'Australia'
                        );
                        $selected_country = get_user_meta($user_id, 'bb_profile_country', true);
                        foreach ($countries as $value => $label) {
                            echo '<option value="' . esc_attr($value) . '" ' . selected($selected_country, $value, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Application History', 'big-bundle'); ?></h3>
        <?php $this->display_user_applications($user_id); ?>
        <?php
    }
    
    /**
     * Save profile fields
     */
    public function save_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Get user data for WordPress user updates
        $user_updates = array('ID' => $user_id);
        $needs_user_update = false;
        
        // Update WordPress built-in user fields
        if (isset($_POST['bb_profile_first_name']) && !empty($_POST['bb_profile_first_name'])) {
            $first_name = sanitize_text_field($_POST['bb_profile_first_name']);
            $user_updates['first_name'] = $first_name;
            update_user_meta($user_id, 'bb_profile_first_name', $first_name);
            update_user_meta($user_id, 'first_name', $first_name); // WordPress standard field
            $needs_user_update = true;
        }
        
        if (isset($_POST['bb_profile_last_name']) && !empty($_POST['bb_profile_last_name'])) {
            $last_name = sanitize_text_field($_POST['bb_profile_last_name']);
            $user_updates['last_name'] = $last_name;
            update_user_meta($user_id, 'bb_profile_last_name', $last_name);
            update_user_meta($user_id, 'last_name', $last_name); // WordPress standard field
            $needs_user_update = true;
        }
        
        // Update display name if both first and last names are provided
        if (isset($user_updates['first_name']) && isset($user_updates['last_name'])) {
            $display_name = trim($user_updates['first_name'] . ' ' . $user_updates['last_name']);
            if (!empty($display_name)) {
                $user_updates['display_name'] = $display_name;
                $user_updates['nickname'] = $display_name;
            }
        } elseif (isset($user_updates['first_name']) && !empty($user_updates['first_name'])) {
            // If only first name is provided, use that as display name
            $user_updates['display_name'] = $user_updates['first_name'];
        }
        
        // Update WordPress user record if needed
        if ($needs_user_update) {
            wp_update_user($user_updates);
        }
        
        // Update custom profile fields
        $custom_fields = array(
            'bb_phone',
            'bb_current_position', 
            'bb_experience_summary',
            'bb_qualifications',
            'bb_availability',
            'bb_profile_address',
            'bb_profile_city',
            'bb_profile_county',
            'bb_profile_postcode',
            'bb_profile_country'
        );
        
        foreach ($custom_fields as $field) {
            if (isset($_POST[$field])) {
                update_user_meta($user_id, $field, sanitize_textarea_field($_POST[$field]));
            }
        }
        
        // Handle checkbox
        $job_alerts = isset($_POST['bb_job_alerts']) ? 1 : 0;
        update_user_meta($user_id, 'bb_job_alerts', $job_alerts);
    }
    
    /**
     * Add registration form fields
     */
    public function add_registration_fields() {
        ?>
        <p>
            <label for="bb_phone"><?php _e('Phone Number', 'big-bundle'); ?></label>
            <input type="tel" name="bb_phone" id="bb_phone" class="input" 
                   value="<?php echo esc_attr(wp_unslash($_POST['bb_phone'] ?? '')); ?>" size="25" />
        </p>
        <?php
    }
    
    /**
     * Validate registration fields
     */
    public function validate_registration_fields($errors, $sanitized_user_login, $user_email) {
        if (!empty($_POST['bb_phone']) && !$this->validate_phone($_POST['bb_phone'])) {
            $errors->add('bb_phone_error', __('Please enter a valid phone number.', 'big-bundle'));
        }
        
        return $errors;
    }
    
    /**
     * Get user profile data for application pre-filling
     */
    public function get_user_profile_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return array();
        }
        
        $first_name = get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name;
        $last_name = get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name;
        
        return array(
            'name' => trim($first_name . ' ' . $last_name) ?: $user->display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'bb_phone', true),
            'address' => get_user_meta($user_id, 'bb_profile_address', true),
            'city' => get_user_meta($user_id, 'bb_profile_city', true),
            'county' => get_user_meta($user_id, 'bb_profile_county', true),
            'postcode' => get_user_meta($user_id, 'bb_profile_postcode', true),
            'country' => get_user_meta($user_id, 'bb_profile_country', true),
            'current_position' => get_user_meta($user_id, 'bb_current_position', true),
            'experience' => get_user_meta($user_id, 'bb_experience_summary', true),
            'qualifications' => get_user_meta($user_id, 'bb_qualifications', true),
            'availability' => get_user_meta($user_id, 'bb_availability', true)
        );
    }
    
    /**
     * Display user's application history
     */
    public function display_user_applications($user_id) {
        if (!$user_id) {
            echo '<div class="bb-message info">Please log in to view your applications.</div>';
            return;
        }
        
        // Check if Application_Handler class exists
        if (!class_exists('Application_Handler')) {
            echo '<div class="bb-message error">Application history is not available. Please contact support.</div>';
            return;
        }
        
        try {
            // Query database directly since Application_Handler uses wrong column
            global $wpdb;
            $table_name = $wpdb->prefix . 'recruitment_applications';
            
            // Get the correct user (for admin context, get the user being viewed)
            $user = get_userdata($user_id);
            if (!$user) {
                echo '<div class="bb-message error">Invalid user.</div>';
                return;
            }
            
            $applications = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE applicant_email = %s ORDER BY created_date DESC LIMIT 20",
                $user->user_email
            ));
            
            
        } catch (Exception $e) {
            echo '<div class="bb-message error">Application history is temporarily unavailable. Please try again later.</div>';
            return;
        }
        
        if (empty($applications)) {
            echo '<div class="bb-empty-state">';
            echo '<h4>No Applications Yet</h4>';
            echo '<p>You haven\'t submitted any job applications yet. When you apply for jobs, they\'ll appear here.</p>';
            echo '<a href="' . home_url('/jobs/') . '" class="bb-submit-btn">Browse Jobs</a>';
            echo '</div>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Position', 'big-bundle'); ?></th>
                    <th><?php _e('Applied Date', 'big-bundle'); ?></th>
                    <?php if (is_admin()): ?>
                        <th><?php _e('Status', 'big-bundle'); ?></th>
                    <?php endif; ?>
                    <th><?php _e('Actions', 'big-bundle'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $application): ?>
                <tr>
                    <td>
                        <?php 
                        $job_title = get_the_title($application->job_id);
                        echo $job_title ? esc_html($job_title) : __('Job no longer available', 'big-bundle');
                        ?>
                    </td>
                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($application->created_date))); ?></td>
                    <?php if (is_admin()): ?>
                        <td>
                            <span class="bb-status bb-status-<?php echo esc_attr($application->application_status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $application->application_status))); ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php if (is_admin()): ?>
                            <?php 
                            $applications_url = admin_url('admin.php?page=bb-recruitment-applications&search=' . urlencode($application->applicant_email));
                            ?>
                            <a href="<?php echo esc_url($applications_url); ?>" class="button button-small">
                                <?php _e('View Applications', 'big-bundle'); ?>
                            </a>
                        <?php else: ?>
                            <div class="application-actions">
                                <?php if ($job_title && $job_title !== __('Job no longer available', 'big-bundle')): ?>
                                    <a href="<?php echo esc_url(get_permalink($application->job_id)); ?>" 
                                       class="bb-submit-btn" style="font-size: 12px; padding: 6px 12px; margin-right: 5px;">
                                        <?php _e('View Job', 'big-bundle'); ?>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="bb-submit-btn" 
                                        style="font-size: 12px; padding: 6px 12px; background: #28a745;"
                                        data-application-data="<?php echo esc_attr(json_encode(array(
                                            'job_title' => $job_title,
                                            'applicant_name' => $application->applicant_name,
                                            'created_date' => date('d/m/Y H:i', strtotime($application->created_date)),
                                            'application_data' => json_decode($application->application_data, true)
                                        ))); ?>"
                                        onclick="viewMyApplicationDirect(this)">
                                    <?php _e('My Submission', 'big-bundle'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX: Save user application data
     */
    public function save_user_application_data_ajax() {
        check_ajax_referer('bb_recruitment_public_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Please log in to save your data.', 'big-bundle'));
        }
        
        $application_data = array(
            'cover_letter' => sanitize_textarea_field($_POST['cover_letter'] ?? ''),
            'experience' => sanitize_textarea_field($_POST['experience'] ?? ''),
            'availability' => sanitize_text_field($_POST['availability'] ?? '')
        );
        
        // Save to user meta for future applications
        update_user_meta($user_id, 'bb_saved_application_data', $application_data);
        
        wp_send_json_success(__('Application data saved to your profile.', 'big-bundle'));
    }
    
    /**
     * AJAX: Get user application data
     */
    public function get_user_application_data_ajax() {
        check_ajax_referer('bb_recruitment_public_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Please log in first.', 'big-bundle'));
        }
        
        $profile_data = $this->get_user_profile_data($user_id);
        $saved_data = get_user_meta($user_id, 'bb_saved_application_data', true);
        
        if ($saved_data) {
            $profile_data = array_merge($profile_data, $saved_data);
        }
        
        wp_send_json_success($profile_data);
    }
    
    /**
     * AJAX: Get application details
     */
    public function get_application_details_ajax() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BB: get_application_details_ajax called');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in first.', 'big-bundle'));
        }
        
        // Simple nonce check
        if (!wp_verify_nonce($_POST['nonce'], 'bb_user_profile_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BB: Nonce verification failed');
            }
            wp_send_json_error(__('Security check failed.', 'big-bundle'));
        }
        
        $application_id = intval($_POST['application_id']);
        if (!$application_id) {
            wp_send_json_error(__('Invalid application ID.', 'big-bundle'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'recruitment_applications';
        
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $application_id
        ));
        
        if (!$application) {
            wp_send_json_error(__('Application not found.', 'big-bundle'));
        }
        
        // Check permissions - admin can see all, users can only see their own
        if (!current_user_can('manage_options') && is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if ($application->applicant_email !== $current_user->user_email) {
                wp_send_json_error(__('Access denied.', 'big-bundle'));
            }
        }
        
        $job_title = get_the_title($application->job_id) ?: __('Job no longer available', 'big-bundle');
        $application_data = json_decode($application->application_data, true);
        
        $response_data = array(
            'job_title' => $job_title,
            'applicant_name' => $application->applicant_name,
            'applicant_email' => $application->applicant_email,
            'phone' => $application->phone,
            'created_date' => date('d/m/Y H:i', strtotime($application->created_date)),
            'application_data' => $application_data
        );
        
        // Only include status for admin users
        if (current_user_can('manage_options')) {
            $response_data['status'] = $application->application_status;
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Test AJAX handler
     */
    public function test_ajax() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BB: test_ajax called');
        }
        wp_send_json_success('Test successful');
    }
    
    /**
     * Handle login redirect for job applications
     */
    public function login_redirect($redirect_to, $request, $user) {
        // Check if user was trying to apply for a job
        if (isset($_GET['apply_job_id'])) {
            $job_id = intval($_GET['apply_job_id']);
            if ($job_id > 0 && get_post_type($job_id) === 'job_vacancy') {
                return add_query_arg('apply', '1', get_permalink($job_id));
            }
        }
        
        // Check if user has a saved application to continue
        if (isset($_GET['continue_application'])) {
            $save_token = sanitize_text_field($_GET['continue_application']);
            $job_id = intval($_GET['job_id'] ?? 0);
            
            if ($save_token && $job_id > 0) {
                return add_query_arg(array(
                    'continue_application' => $save_token,
                    'job_id' => $job_id
                ), get_permalink($job_id));
            }
        }
        
        return $redirect_to;
    }
    
    /**
     * Get users who want job alerts
     */
    public function get_job_alert_subscribers() {
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'bb_job_alerts',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => array('ID', 'user_email', 'display_name')
        ));
        
        return $users;
    }
    
    /**
     * Send job alerts to subscribers
     */
    public function send_job_alert($job_id) {
        $subscribers = $this->get_job_alert_subscribers();
        
        if (empty($subscribers)) {
            return;
        }
        
        $job_title = get_the_title($job_id);
        $job_url = get_permalink($job_id);
        $job_excerpt = get_the_excerpt($job_id);
        
        $subject = sprintf(__('New Job Opportunity: %s', 'big-bundle'), $job_title);
        
        $message = sprintf(
            __("Hello,\n\nA new job opportunity has been posted that may interest you:\n\n%s\n\n%s\n\nView and apply: %s\n\nTo unsubscribe from job alerts, please update your profile.\n\nBest regards,\nHR Team", 'big-bundle'),
            $job_title,
            $job_excerpt,
            $job_url
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        foreach ($subscribers as $subscriber) {
            wp_mail($subscriber->user_email, $subject, $message, $headers);
        }
    }
    
    /**
     * Validate phone number
     */
    private function validate_phone($phone) {
        // Basic phone validation - can be enhanced
        $phone = preg_replace('/[^\d+\-\s\(\)]/', '', $phone);
        return strlen($phone) >= 10;
    }
    
    /**
     * Get application status color class
     */
    public function get_status_class($status) {
        $classes = array(
            'draft' => 'bb-status-draft',
            'submitted' => 'bb-status-submitted', 
            'under_review' => 'bb-status-review',
            'shortlisted' => 'bb-status-shortlisted',
            'interviewed' => 'bb-status-interviewed',
            'offered' => 'bb-status-offered',
            'hired' => 'bb-status-hired',
            'rejected' => 'bb-status-rejected',
            'withdrawn' => 'bb-status-withdrawn'
        );
        
        return $classes[$status] ?? 'bb-status-default';
    }
    
    /**
     * Populate Gravity Forms with user profile data
     */
    public function populate_gravity_form($form, $is_ajax, $field_values) {
        if (!is_user_logged_in()) {
            return $form;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BB User Profile: Populating Gravity Form ID ' . $form['id'] . ' for user ' . $user_id);
        }
        
        // Get profile data
        $profile_data = array(
            'first_name' => get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name,
            'last_name' => get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'bb_phone', true),
            'address' => get_user_meta($user_id, 'bb_profile_address', true),
            'city' => get_user_meta($user_id, 'bb_profile_city', true),
            'county' => get_user_meta($user_id, 'bb_profile_county', true),
            'postcode' => get_user_meta($user_id, 'bb_profile_postcode', true),
            'country' => get_user_meta($user_id, 'bb_profile_country', true),
            'current_position' => get_user_meta($user_id, 'bb_current_position', true),
            'experience' => get_user_meta($user_id, 'bb_experience_summary', true),
            'qualifications' => get_user_meta($user_id, 'bb_qualifications', true),
            'availability' => get_user_meta($user_id, 'bb_availability', true)
        );
        
        // Debug: Log profile data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BB User Profile: Profile data: ' . print_r($profile_data, true));
        }
        
        // Auto-populate form fields based on field labels or admin labels
        foreach ($form['fields'] as &$field) {
            $original_value = $field->defaultValue;
            if (empty($field->defaultValue)) {
                $this->set_field_default_value($field, $profile_data);
                
                // Debug: Log field updates
                if (defined('WP_DEBUG') && WP_DEBUG && $field->defaultValue !== $original_value) {
                    error_log('BB User Profile: Updated field ID ' . $field->id . ' (' . $field->label . ') from "' . $original_value . '" to "' . $field->defaultValue . '"');
                }
            }
        }
        
        return $form;
    }
    
    /**
     * Populate individual Gravity Form field values
     */
    public function populate_gravity_form_field($value, $lead, $field) {
        if (!is_user_logged_in() || !empty($value)) {
            return $value;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        // Map field types/labels to profile data
        $field_mappings = array(
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'bb_phone', true),
            'first_name' => get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name,
            'last_name' => get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name,
            'address' => get_user_meta($user_id, 'bb_profile_address', true),
            'city' => get_user_meta($user_id, 'bb_profile_city', true),
            'county' => get_user_meta($user_id, 'bb_profile_county', true),
            'postcode' => get_user_meta($user_id, 'bb_profile_postcode', true),
            'country' => get_user_meta($user_id, 'bb_profile_country', true)
        );
        
        // Try to match by parameter name first (set in form settings)
        if (isset($field->inputName)) {
            $input_name = strtolower($field->inputName);
            if (isset($field_mappings[$input_name])) {
                return $field_mappings[$input_name];
            }
        }
        
        // Then try to match by field label
        if (isset($field->label)) {
            $label = strtolower($field->label);
            foreach ($field_mappings as $key => $data) {
                if (strpos($label, $key) !== false) {
                    return $data;
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Set field default value based on profile data
     */
    private function set_field_default_value(&$field, $profile_data) {
        $label = strtolower($field->label);
        $type = $field->type;
        
        // Handle different field types
        switch ($type) {
            case 'name':
                if (strpos($label, 'first') !== false || strpos($label, 'name') !== false) {
                    $field->defaultValue = $profile_data['first_name'];
                } elseif (strpos($label, 'last') !== false || strpos($label, 'surname') !== false) {
                    $field->defaultValue = $profile_data['last_name'];
                } else {
                    // Full name field
                    $field->defaultValue = trim($profile_data['first_name'] . ' ' . $profile_data['last_name']);
                }
                break;
                
            case 'email':
                $field->defaultValue = $profile_data['email'];
                break;
                
            case 'phone':
                $field->defaultValue = $profile_data['phone'];
                break;
                
            case 'address':
                if (strpos($label, 'city') !== false) {
                    $field->defaultValue = $profile_data['city'];
                } elseif (strpos($label, 'county') !== false || strpos($label, 'state') !== false) {
                    $field->defaultValue = $profile_data['county'];
                } elseif (strpos($label, 'postcode') !== false || strpos($label, 'zip') !== false) {
                    $field->defaultValue = $profile_data['postcode'];
                } elseif (strpos($label, 'country') !== false) {
                    $field->defaultValue = $profile_data['country'];
                } else {
                    $field->defaultValue = $profile_data['address'];
                }
                break;
                
            case 'text':
            case 'textarea':
                // Match by label keywords
                if (strpos($label, 'phone') !== false) {
                    $field->defaultValue = $profile_data['phone'];
                } elseif (strpos($label, 'email') !== false) {
                    $field->defaultValue = $profile_data['email'];
                } elseif (strpos($label, 'first') !== false && strpos($label, 'name') !== false) {
                    $field->defaultValue = $profile_data['first_name'];
                } elseif (strpos($label, 'last') !== false && strpos($label, 'name') !== false) {
                    $field->defaultValue = $profile_data['last_name'];
                } elseif (strpos($label, 'address') !== false) {
                    $field->defaultValue = $profile_data['address'];
                } elseif (strpos($label, 'city') !== false) {
                    $field->defaultValue = $profile_data['city'];
                } elseif (strpos($label, 'county') !== false) {
                    $field->defaultValue = $profile_data['county'];
                } elseif (strpos($label, 'postcode') !== false || strpos($label, 'zip') !== false) {
                    $field->defaultValue = $profile_data['postcode'];
                } elseif (strpos($label, 'country') !== false) {
                    $field->defaultValue = $profile_data['country'];
                } elseif (strpos($label, 'position') !== false || strpos($label, 'job') !== false) {
                    $field->defaultValue = $profile_data['current_position'];
                } elseif (strpos($label, 'experience') !== false) {
                    $field->defaultValue = $profile_data['experience'];
                } elseif (strpos($label, 'qualification') !== false) {
                    $field->defaultValue = $profile_data['qualifications'];
                } elseif (strpos($label, 'availab') !== false) {
                    $field->defaultValue = $profile_data['availability'];
                }
                break;
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_page() || is_singular('job_vacancy')) {
            wp_enqueue_script('bb-user-profile', plugins_url('assets/user-profile.js', dirname(__FILE__)), array('jquery'), '1.0.0', true);
            wp_enqueue_style('bb-user-profile', plugins_url('assets/user-profile.css', dirname(__FILE__)), array(), '1.0.0');
            
            wp_localize_script('bb-user-profile', 'bbUserProfile', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bb_user_profile_nonce'),
                'alert_nonce' => wp_create_nonce('bb_alert_action_' . get_current_user_id()),
                'is_logged_in' => is_user_logged_in(),
                'login_url' => wp_login_url(),
                'register_url' => wp_registration_url()
            ));
        }
    }
    
    /**
     * User profile shortcode for frontend management
     */
    public function user_profile_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_registration' => 'true',
            'redirect_after_login' => '',
            'redirect_after_register' => ''
        ), $atts);
        
        if (!is_user_logged_in()) {
            return $this->render_login_registration_form($atts);
        } else {
            return $this->render_user_profile_form();
        }
    }
    
    /**
     * Render login and registration form for non-logged-in users
     */
    private function render_login_registration_form($atts) {
        ob_start();
        ?>
        <div class="bb-user-auth-container">
            <div class="bb-auth-tabs">
                <button type="button" class="bb-tab-button active" data-tab="login">Login</button>
                <?php if ($atts['show_registration'] === 'true' && (get_option('users_can_register') || current_user_can('manage_options'))): ?>
                <button type="button" class="bb-tab-button" data-tab="register">Register</button>
                <?php endif; ?>
            </div>
            
            <!-- Login Form -->
            <div class="bb-auth-form bb-login-form active">
                <form id="bb-login-form" method="post">
                    <div class="form-group">
                        <label for="user_login">Username or Email</label>
                        <input type="text" name="log" id="user_login" required>
                    </div>
                    <div class="form-group">
                        <label for="user_pass">Password</label>
                        <input type="password" name="pwd" id="user_pass" required>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="rememberme" value="forever">
                            Remember Me
                        </label>
                    </div>
                    <button type="submit" class="bb-submit-btn">Login</button>
                    <p><a href="<?php echo wp_lostpassword_url(); ?>">Forgot Password?</a></p>
                </form>
            </div>
            
            <!-- Registration Form -->
            <?php if ($atts['show_registration'] === 'true' && (get_option('users_can_register') || current_user_can('manage_options'))): ?>
            <div class="bb-auth-form bb-register-form">
                <form id="bb-register-form" method="post">
                    <div class="form-group">
                        <label for="user_login_reg">Username</label>
                        <input type="text" name="user_login" id="user_login_reg" required>
                    </div>
                    <div class="form-group">
                        <label for="user_email_reg">Email</label>
                        <input type="email" name="user_email" id="user_email_reg" required>
                    </div>
                    <div class="form-group">
                        <label for="bb_profile_first_name_reg">First Name</label>
                        <input type="text" name="bb_profile_first_name" id="bb_profile_first_name_reg">
                    </div>
                    <div class="form-group">
                        <label for="bb_profile_last_name_reg">Last Name</label>
                        <input type="text" name="bb_profile_last_name" id="bb_profile_last_name_reg">
                    </div>
                    <div class="form-group">
                        <label for="bb_phone_reg">Phone</label>
                        <input type="tel" name="bb_phone" id="bb_phone_reg">
                    </div>
                    <p class="bb-password-info">A password will be sent to your email address.</p>
                    <button type="submit" class="bb-submit-btn">Register</button>
                    <?php wp_nonce_field('bb_user_profile_nonce', 'nonce'); ?>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="bb-auth-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render user profile form for logged-in users
     */
    private function render_user_profile_form() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        // Get current section from URL
        $current_section = $_GET['section'] ?? 'profile';
        
        ob_start();
        ?>
        <div class="bb-user-dashboard-container">
            <div class="bb-dashboard-wrapper">
                <!-- Sidebar Navigation -->
                <nav class="bb-dashboard-nav">
                    <h3>My Account</h3>
                    <ul class="bb-nav-menu">
                        <li class="<?php echo $current_section === 'profile' ? 'active' : ''; ?>">
                            <a href="<?php echo add_query_arg('section', 'profile'); ?>">
                                👤 Profile
                            </a>
                        </li>
                        <li class="<?php echo $current_section === 'job-alerts' ? 'active' : ''; ?>">
                            <a href="<?php echo add_query_arg('section', 'job-alerts'); ?>">
                                🔔 Job Alerts
                            </a>
                        </li>
                        <li class="<?php echo $current_section === 'applications' ? 'active' : ''; ?>">
                            <a href="<?php echo add_query_arg('section', 'applications'); ?>">
                                📋 My Applications
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo wp_logout_url(get_permalink()); ?>">
                                🚪 Logout
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- Main Content Area -->
                <div class="bb-dashboard-content">
                    <?php
                    switch ($current_section) {
                        case 'job-alerts':
                            $this->render_job_alerts_section($user_id);
                            break;
                        case 'applications':
                            $this->render_applications_section($user_id);
                            break;
                        case 'profile':
                        default:
                            $this->render_profile_section($user_id, $user);
                            break;
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render profile section
     */
    private function render_profile_section($user_id, $user) {
        ?>
        <div class="bb-section-header">
            <h3>Profile Information</h3>
            <p>Update your personal information and job application details.</p>
        </div>
        
        <form id="bb-user-profile-form" method="post">
            <div class="profile-section">
                <h4>Personal Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bb_profile_first_name">First Name</label>
                        <input type="text" name="bb_profile_first_name" id="bb_profile_first_name" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bb_profile_last_name">Last Name</label>
                        <input type="text" name="bb_profile_last_name" id="bb_profile_last_name" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="user_email">Email (cannot be changed here)</label>
                    <input type="email" id="user_email" value="<?php echo esc_attr($user->user_email); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="bb_phone">Phone Number</label>
                    <input type="tel" name="bb_phone" id="bb_phone" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'bb_phone', true)); ?>">
                </div>
            </div>
            
            <div class="profile-section">
                <h4>Address</h4>
                <div class="form-group">
                    <label for="bb_profile_address">Address</label>
                    <textarea name="bb_profile_address" id="bb_profile_address" rows="3"><?php echo esc_textarea(get_user_meta($user_id, 'bb_profile_address', true)); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bb_profile_city">City</label>
                        <input type="text" name="bb_profile_city" id="bb_profile_city" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_city', true)); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bb_profile_county">County</label>
                        <input type="text" name="bb_profile_county" id="bb_profile_county" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_county', true)); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bb_profile_postcode">Postcode</label>
                        <input type="text" name="bb_profile_postcode" id="bb_profile_postcode" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_profile_postcode', true)); ?>">
                    </div>
                </div>
                <div class="form-group">
                        <label for="bb_profile_country">Country</label>
                        <select name="bb_profile_country" id="bb_profile_country">
                            <option value="">Select Country</option>
                            <?php
                            $countries = array(
                                'United Kingdom' => 'United Kingdom',
                                'Ireland' => 'Ireland', 
                                'United States' => 'United States',
                                'Canada' => 'Canada',
                                'Australia' => 'Australia'
                            );
                            $selected_country = get_user_meta($user_id, 'bb_profile_country', true);
                            foreach ($countries as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($selected_country, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h4>Professional Information</h4>
                    <div class="form-group">
                        <label for="bb_current_position">Current Position</label>
                        <input type="text" name="bb_current_position" id="bb_current_position" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_current_position', true)); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bb_experience_summary">Experience Summary</label>
                        <textarea name="bb_experience_summary" id="bb_experience_summary" rows="4"><?php echo esc_textarea(get_user_meta($user_id, 'bb_experience_summary', true)); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bb_qualifications">Qualifications</label>
                        <textarea name="bb_qualifications" id="bb_qualifications" rows="3"><?php echo esc_textarea(get_user_meta($user_id, 'bb_qualifications', true)); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bb_availability">Availability</label>
                        <input type="text" name="bb_availability" id="bb_availability" 
                               value="<?php echo esc_attr(get_user_meta($user_id, 'bb_availability', true)); ?>"
                               placeholder='e.g., "Available immediately", "2 weeks notice required"'>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h4>Job Alert Preferences</h4>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="bb_job_alerts" value="1" 
                                   <?php checked(get_user_meta($user_id, 'bb_job_alerts', true), 1); ?>>
                            Enable job alerts (receive email notifications for new jobs)
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="bb-submit-btn" name="update_profile" value="1">Update Profile</button>
                <?php wp_nonce_field('bb_user_profile_nonce', 'nonce'); ?>
            </form>
            
            <div class="bb-profile-messages"></div>
        <?php
    }
    
    /**
     * Render job alerts section
     */
    private function render_job_alerts_section($user_id) {
        ?>
        <div class="bb-section-header">
            <h3>Job Alerts</h3>
            <p>Create and manage your job alert notifications.</p>
        </div>
        
        <?php $this->render_simple_job_alerts($user_id); ?>
        <?php
    }
    
    /**
     * Render applications section
     */
    private function render_applications_section($user_id) {
        ?>
        <div class="bb-section-header">
            <h3>My Applications</h3>
            <p>View and track your job applications.</p>
        </div>
        
        <?php $this->display_user_applications($user_id); ?>
        <?php
    }
    
    /**
     * AJAX: Update user profile
     */
    public function ajax_update_user_profile() {
        check_ajax_referer('bb_user_profile_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in to update your profile.', 'big-bundle'));
        }
        
        // Only process if this is actually a profile update request
        if (!isset($_POST['update_profile'])) {
            wp_send_json_error(__('Invalid request.', 'big-bundle'));
        }
        
        $user_id = get_current_user_id();
        
        // Update profile fields
        $this->save_profile_fields($user_id);
        
        wp_send_json_success(__('Profile updated successfully!', 'big-bundle'));
    }
    
    /**
     * AJAX: Register new user
     */
    public function ajax_register_user() {
        check_ajax_referer('bb_user_profile_nonce', 'nonce');
        
        if (!get_option('users_can_register')) {
            wp_send_json_error(__('Registration is currently disabled.', 'big-bundle'));
        }
        
        $username = sanitize_user($_POST['user_login'] ?? '');
        $email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($username) || empty($email)) {
            wp_send_json_error(__('Username and email are required.', 'big-bundle'));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(__('Username already exists.', 'big-bundle'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(__('Email already exists.', 'big-bundle'));
        }
        
        $user_id = wp_create_user($username, wp_generate_password(), $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Save additional profile fields
        $this->save_profile_fields($user_id);
        
        // Send notification email
        wp_new_user_notification($user_id, null, 'both');
        
        wp_send_json_success(__('Registration successful! Please check your email for login details.', 'big-bundle'));
    }
    
    /**
     * Render simple job alerts interface
     */
    private function render_simple_job_alerts($user_id) {
        if (!class_exists('BB_Simple_Job_Alerts')) {
            return;
        }
        
        $alerts_manager = BB_Simple_Job_Alerts::get_instance();
        $user_alerts = $alerts_manager->get_user_alerts($user_id);
        
        // Check if creating alert for specific job
        $create_for_job = intval($_GET['create_alert_for'] ?? 0);
        $source_job = null;
        if ($create_for_job) {
            $source_job = get_post($create_for_job);
        }
        
        // Show status messages
        if (isset($_GET['alert_created'])) {
            echo '<div class="bb-message success">✅ Job alert created successfully!</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . remove_query_arg(array('alert_created', 'alert_error', 'create_alert_for')) . '";
                }, 3000);
            </script>';
        } elseif (isset($_GET['alert_error'])) {
            echo '<div class="bb-message error">❌ Failed to create job alert. Please try again.</div>';
        } elseif (isset($_GET['alert_deleted'])) {
            echo '<div class="bb-message success">✅ Job alert deleted successfully!</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . remove_query_arg(array('alert_deleted', 'alert_delete_error')) . '";
                }, 2000);
            </script>';
        } elseif (isset($_GET['alert_toggled'])) {
            echo '<div class="bb-message success">✅ Job alert status updated!</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . remove_query_arg(array('alert_toggled', 'alert_toggle_error')) . '";
                }, 2000);
            </script>';
        } elseif (isset($_GET['alert_updated'])) {
            echo '<div class="bb-message success">✅ Job alert updated successfully!</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . remove_query_arg(array('alert_updated', 'alert_update_error')) . '";
                }, 2000);
            </script>';
        }
        ?>
        
        <div class="bb-simple-alerts">
            <?php if ($source_job): ?>
            <div class="bb-contextual-notice">
                <h5>🔔 Create Alert for Similar Jobs</h5>
                <p>Creating an alert based on: <strong><?php echo esc_html($source_job->post_title); ?></strong></p>
            </div>
            <?php endif; ?>
            
            <!-- Existing Alerts -->
            <?php if (!empty($user_alerts)): ?>
                <div class="bb-existing-alerts">
                    <h5>Your Active Alerts</h5>
                    <?php foreach ($user_alerts as $alert): ?>
                    <div class="bb-alert-card <?php echo $alert->is_active ? 'active' : 'inactive'; ?>">
                        <div class="alert-details">
                            <h6><?php echo esc_html($alert->post_title); ?></h6>
                            <?php if ($alert->keywords): ?>
                                <p><strong>Keywords:</strong> <?php echo esc_html($alert->keywords); ?></p>
                            <?php endif; ?>
                            <p><strong>Frequency:</strong> <?php echo ucfirst($alert->frequency); ?></p>
                            <p><small>Created: <?php echo date('M j, Y', strtotime($alert->post_date)); ?></small></p>
                        </div>
                        <div class="alert-actions">
                            <button type="button" class="btn-toggle bb-alert-action-btn" 
                                    data-alert-id="<?php echo $alert->ID; ?>" 
                                    data-action="toggle">
                                <?php echo $alert->is_active ? 'Disable' : 'Enable'; ?>
                            </button>
                            <button type="button" class="btn-delete bb-alert-action-btn" 
                                    data-alert-id="<?php echo $alert->ID; ?>" 
                                    data-action="delete">
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Create New Alert Form -->
            <div class="bb-create-simple-alert">
                <h5>➕ Create New Job Alert</h5>
                <form id="bb-create-alert-form" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="alert_name">Alert Name</label>
                            <input type="text" id="alert_name" name="alert_name" 
                                   placeholder="e.g., Marketing Manager Jobs"
                                   value="<?php echo $source_job ? esc_attr('Jobs like: ' . $source_job->post_title) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="alert_frequency">Notification Frequency</label>
                            <select id="alert_frequency" name="alert_frequency">
                                <option value="immediate">Immediate</option>
                                <option value="daily">Daily Digest</option>
                                <option value="weekly">Weekly Digest</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert_keywords">Keywords (comma-separated)</label>
                        <input type="text" id="alert_keywords" name="alert_keywords" 
                               placeholder="e.g., manager, marketing, digital"
                               value="<?php echo $source_job ? esc_attr($alerts_manager->extract_keywords_from_job($source_job)) : ''; ?>">
                        <small>Leave blank to get alerts for all jobs in selected categories/locations</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="alert_categories">Job Categories (optional)</label>
                            <select id="alert_categories" name="alert_categories[]" multiple>
                                <?php
                                $job_categories = get_terms(array('taxonomy' => 'job_category', 'hide_empty' => false));
                                $source_categories = $source_job ? wp_get_post_terms($source_job->ID, 'job_category', array('fields' => 'ids')) : array();
                                foreach ($job_categories as $category):
                                    $selected = in_array($category->term_id, $source_categories) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="alert_locations">Job Locations (optional)</label>
                            <select id="alert_locations" name="alert_locations[]" multiple>
                                <?php
                                $job_locations = get_terms(array('taxonomy' => 'job_location', 'hide_empty' => false));
                                $source_locations = $source_job ? wp_get_post_terms($source_job->ID, 'job_location', array('fields' => 'ids')) : array();
                                foreach ($job_locations as $location):
                                    $selected = in_array($location->term_id, $source_locations) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $location->term_id; ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($location->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <input type="hidden" name="alert_type" value="<?php echo $source_job ? 'contextual' : 'profile'; ?>">
                    <input type="hidden" name="bb_create_alert" value="1">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('bb_create_alert_' . $user_id); ?>">
                    <?php wp_nonce_field('bb_create_alert_' . $user_id, '_bb_alert_nonce'); ?>
                    
                    <button type="submit" class="bb-submit-btn">Create Job Alert</button>
                </form>
                
                <div class="bb-alert-messages" style="margin-top: 15px;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Specific field population methods for Gravity Forms parameter names
     */
    public function populate_first_name($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        return get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name;
    }
    
    public function populate_last_name($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        return get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name;
    }
    
    public function populate_full_name($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $first_name = get_user_meta($user_id, 'bb_profile_first_name', true) ?: $user->first_name;
        $last_name = get_user_meta($user_id, 'bb_profile_last_name', true) ?: $user->last_name;
        return trim($first_name . ' ' . $last_name) ?: $user->display_name;
    }
    
    public function populate_email($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        $user = get_userdata(get_current_user_id());
        return $user->user_email;
    }
    
    public function populate_phone($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_phone', true);
    }
    
    public function populate_address($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_profile_address', true);
    }
    
    public function populate_address_line_2($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        // We don't store address line 2 separately, so return empty
        return '';
    }
    
    public function populate_city($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_profile_city', true);
    }
    
    public function populate_county($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_profile_county', true);
    }
    
    public function populate_postcode($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_profile_postcode', true);
    }
    
    public function populate_country($value) {
        if (!is_user_logged_in() || !empty($value)) return $value;
        return get_user_meta(get_current_user_id(), 'bb_profile_country', true);
    }
}

/**
 * Global AJAX handler for application details
 */
function bb_get_application_details_global() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('BB: bb_get_application_details_global called');
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error(__('Please log in first.', 'big-bundle'));
    }
    
    $application_id = intval($_POST['application_id']);
    if (!$application_id) {
        wp_send_json_error(__('Invalid application ID.', 'big-bundle'));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitment_applications';
    
    $application = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $application_id
    ));
    
    if (!$application) {
        wp_send_json_error(__('Application not found.', 'big-bundle'));
    }
    
    // Check permissions - admin can see all, users can only see their own
    if (!current_user_can('manage_options') && is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if ($application->applicant_email !== $current_user->user_email) {
            wp_send_json_error(__('Access denied.', 'big-bundle'));
        }
    }
    
    $job_title = get_the_title($application->job_id) ?: __('Job no longer available', 'big-bundle');
    $application_data = json_decode($application->application_data, true);
    
    $response_data = array(
        'job_title' => $job_title,
        'applicant_name' => $application->applicant_name,
        'applicant_email' => $application->applicant_email,
        'phone' => $application->phone,
        'created_date' => date('d/m/Y H:i', strtotime($application->created_date)),
        'application_data' => $application_data
    );
    
    // Only include status for admin users
    if (current_user_can('manage_options')) {
        $response_data['status'] = $application->application_status;
    }
    
    wp_send_json_success($response_data);
}