<?php
/**
 * Form Integration Class
 * 
 * Handles integration with various form plugins to capture job applications
 * Supports Gravity Forms, Contact Form 7, WPForms, Ninja Forms, and custom hooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Form_Integration {
    
    private static $instance = null;
    private $application_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize after application manager is available
        add_action('init', array($this, 'init_integrations'), 15);
    }
    
    public function init_integrations() {
        $this->application_manager = BB_Application_Manager::get_instance();
        
        // Gravity Forms Integration
        if (class_exists('GFAPI')) {
            add_action('gform_after_submission', array($this, 'handle_gravity_form_submission'), 10, 2);
        }
        
        // Contact Form 7 Integration
        if (function_exists('wpcf7')) {
            add_action('wpcf7_mail_sent', array($this, 'handle_cf7_submission'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_cf7_scripts'));
        }
        
        // WPForms Integration
        if (function_exists('wpforms')) {
            add_action('wpforms_process_complete', array($this, 'handle_wpforms_submission'), 10, 4);
        }
        
        // Ninja Forms Integration
        if (class_exists('Ninja_Forms')) {
            add_action('ninja_forms_after_submission', array($this, 'handle_ninja_forms_submission'));
        }
        
        // Generic hook for custom integrations
        add_action('bb_job_application_submitted', array($this, 'handle_custom_submission'), 10, 1);
        
        // Add shortcode for job ID detection
        add_shortcode('bb_job_context', array($this, 'job_context_shortcode'));
        
        // Add JavaScript for job ID detection
        add_action('wp_footer', array($this, 'add_job_context_script'));
    }
    
    /**
     * Handle Gravity Forms submissions
     */
    public function handle_gravity_form_submission($entry, $form) {
        $job_id = $this->detect_job_id_from_context();
        
        if (!$job_id) {
            // Try to get job ID from hidden field in form
            $job_id = $this->get_field_value($entry, 'job_id');
            if (!$job_id) {
                // Try to get from form settings or meta
                $job_id = $this->get_job_id_from_form_meta($form['id'], 'gravity');
            }
        }
        
        if (!$job_id) {
            error_log('BB Recruitment: No job ID found for Gravity Form submission');
            return;
        }
        
        // Extract common fields using form structure
        $applicant_name = $this->extract_name_from_gravity_form($entry, $form);
        $applicant_email = $this->extract_email_from_entry($entry);
        $phone = $this->extract_phone_from_entry($entry);
        
        // Debug logging
        error_log("BB Recruitment Debug: Extracted name='$applicant_name', email='$applicant_email', phone='$phone'");
        error_log("BB Recruitment Debug: Form ID={$form['id']}, Entry ID={$entry['id']}, Job ID=$job_id");
        
        if (!$applicant_name || !$applicant_email) {
            error_log('BB Recruitment: Missing required fields (name/email) in Gravity Form submission');
            error_log('BB Recruitment Debug: Entry data: ' . print_r($entry, true));
            return;
        }
        
        // Prepare application data
        $application_data = array(
            'form_type' => 'gravity_forms',
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'fields' => array()
        );
        
        // Extract all form fields - handle both simple and complex fields
        foreach ($form['fields'] as $field) {
            if ($field->type === 'page' || $field->type === 'section') {
                continue;
            }
            
            // For complex fields like address, name, etc., we need to check sub-fields
            if (in_array($field->type, ['address', 'name', 'checkbox', 'radio'])) {
                // Handle complex fields with sub-fields
                $complex_value = $this->extract_complex_field_value($entry, $field);
                if (!empty($complex_value)) {
                    $application_data['fields'][$field->id] = array(
                        'label' => $field->label,
                        'value' => $complex_value,
                        'type' => $field->type
                    );
                }
            } else {
                // Handle simple fields
                $field_value = $this->get_field_value($entry, $field->id);
                if (!empty($field_value)) {
                    $application_data['fields'][$field->id] = array(
                        'label' => $field->label,
                        'value' => $field_value,
                        'type' => $field->type
                    );
                }
            }
        }
        
        // Add the application
        $result = $this->application_manager->add_application(
            $job_id,
            $applicant_name,
            $applicant_email,
            json_encode($application_data),
            $phone
        );
        
        if ($result) {
            error_log("BB Recruitment: Added application from Gravity Form {$form['id']} for job {$job_id}");
            do_action('bb_application_received', $job_id, $applicant_email, 'gravity_forms');
        } else {
            error_log("BB Recruitment: Failed to add application from Gravity Form {$form['id']}");
        }
    }
    
    /**
     * Handle Contact Form 7 submissions
     */
    public function handle_cf7_submission($contact_form) {
        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            return;
        }

        $posted_data = $submission->get_posted_data();
        $uploaded_files = $submission->uploaded_files(); // Get actual file paths

        $job_id = $this->detect_job_id_from_context();

        if (!$job_id && isset($posted_data['job-id'])) {
            $job_id = intval($posted_data['job-id']);
        }

        if (!$job_id) {
            $job_id = $this->get_job_id_from_form_meta($contact_form->id(), 'cf7');
        }

        if (!$job_id) {
            error_log('BB Recruitment: No job ID found for CF7 submission');
            return;
        }

        // Extract applicant details
        $applicant_name = $this->extract_name_from_cf7($posted_data);
        $applicant_email = $posted_data['your-email'] ?? $posted_data['email'] ?? '';
        $phone = $posted_data['your-phone'] ?? $posted_data['phone'] ?? '';

        if (!$applicant_name || !$applicant_email) {
            error_log('BB Recruitment: Missing required fields in CF7 submission');
            return;
        }

        // Replace file hashes with actual file URLs in posted_data
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $field_name => $file_path) {
                if (!empty($file_path) && is_string($file_path)) {
                    // Convert file path to URL
                    $upload_dir = wp_upload_dir();
                    if (strpos($file_path, $upload_dir['basedir']) === 0) {
                        $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
                        $posted_data[$field_name] = $file_url;
                    } else {
                        $posted_data[$field_name] = $file_path;
                    }
                }
            }
        }

        // Prepare application data
        $application_data = array(
            'form_type' => 'contact_form_7',
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'fields' => $posted_data
        );

        // Add the application
        $result = $this->application_manager->add_application(
            $job_id,
            $applicant_name,
            $applicant_email,
            json_encode($application_data),
            $phone
        );

        if ($result) {
            error_log("BB Recruitment: Added application from CF7 form {$contact_form->id()} for job {$job_id}");
            do_action('bb_application_received', $job_id, $applicant_email, 'contact_form_7');
        }
    }
    
    /**
     * Handle WPForms submissions
     */
    public function handle_wpforms_submission($fields, $entry, $form_data, $entry_id) {
        $job_id = $this->detect_job_id_from_context();
        
        if (!$job_id) {
            $job_id = $this->get_job_id_from_form_meta($form_data['id'], 'wpforms');
        }
        
        if (!$job_id) {
            error_log('BB Recruitment: No job ID found for WPForms submission');
            return;
        }
        
        // Extract applicant details
        $applicant_name = $this->extract_name_from_wpforms($fields);
        $applicant_email = $this->extract_email_from_wpforms($fields);
        $phone = $this->extract_phone_from_wpforms($fields);
        
        if (!$applicant_name || !$applicant_email) {
            error_log('BB Recruitment: Missing required fields in WPForms submission');
            return;
        }
        
        // Prepare application data
        $application_data = array(
            'form_type' => 'wpforms',
            'form_id' => $form_data['id'],
            'form_title' => $form_data['settings']['form_title'],
            'entry_id' => $entry_id,
            'fields' => $fields
        );
        
        // Add the application
        $result = $this->application_manager->add_application(
            $job_id,
            $applicant_name,
            $applicant_email,
            json_encode($application_data),
            $phone
        );
        
        if ($result) {
            error_log("BB Recruitment: Added application from WPForms {$form_data['id']} for job {$job_id}");
            do_action('bb_application_received', $job_id, $applicant_email, 'wpforms');
        }
    }
    
    /**
     * Handle Ninja Forms submissions
     */
    public function handle_ninja_forms_submission($form_data) {
        $job_id = $this->detect_job_id_from_context();
        
        if (!$job_id) {
            $job_id = $this->get_job_id_from_form_meta($form_data['id'], 'ninja');
        }
        
        if (!$job_id) {
            error_log('BB Recruitment: No job ID found for Ninja Forms submission');
            return;
        }
        
        $fields = $form_data['fields'];
        
        // Extract applicant details
        $applicant_name = $this->extract_name_from_ninja($fields);
        $applicant_email = $this->extract_email_from_ninja($fields);
        $phone = $this->extract_phone_from_ninja($fields);
        
        if (!$applicant_name || !$applicant_email) {
            error_log('BB Recruitment: Missing required fields in Ninja Forms submission');
            return;
        }
        
        // Prepare application data
        $application_data = array(
            'form_type' => 'ninja_forms',
            'form_id' => $form_data['id'],
            'form_title' => $form_data['settings']['title'],
            'fields' => $fields
        );
        
        // Add the application
        $result = $this->application_manager->add_application(
            $job_id,
            $applicant_name,
            $applicant_email,
            json_encode($application_data),
            $phone
        );
        
        if ($result) {
            error_log("BB Recruitment: Added application from Ninja Forms {$form_data['id']} for job {$job_id}");
            do_action('bb_application_received', $job_id, $applicant_email, 'ninja_forms');
        }
    }
    
    /**
     * Handle custom submissions via action hook
     */
    public function handle_custom_submission($data) {
        if (!isset($data['job_id']) || !isset($data['applicant_name']) || !isset($data['applicant_email'])) {
            error_log('BB Recruitment: Missing required fields in custom submission');
            return;
        }
        
        $result = $this->application_manager->add_application(
            $data['job_id'],
            $data['applicant_name'],
            $data['applicant_email'],
            json_encode($data),
            $data['phone'] ?? ''
        );
        
        if ($result) {
            error_log("BB Recruitment: Added custom application for job {$data['job_id']}");
            do_action('bb_application_received', $data['job_id'], $data['applicant_email'], 'custom');
        }
    }
    
    /**
     * Detect job ID from current page context
     */
    private function detect_job_id_from_context() {
        global $post;
        
        // If we're on a job page, use that ID
        if ($post && $post->post_type === 'job_vacancy') {
            return $post->ID;
        }
        
        // Try to get from URL parameters
        if (isset($_GET['job_id'])) {
            return intval($_GET['job_id']);
        }
        
        // Try to get from referrer
        $referrer = wp_get_referer();
        if ($referrer) {
            $url_parts = parse_url($referrer);
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_vars);
                if (isset($query_vars['job_id'])) {
                    return intval($query_vars['job_id']);
                }
            }
            
            // Check if referrer is a job page
            $post_id = url_to_postid($referrer);
            if ($post_id && get_post_type($post_id) === 'job_vacancy') {
                return $post_id;
            }
        }
        
        // Try to get from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['bb_current_job_id'])) {
            return intval($_SESSION['bb_current_job_id']);
        }
        
        return 0;
    }
    
    /**
     * Get job ID from form meta/settings
     */
    private function get_job_id_from_form_meta($form_id, $form_type) {
        $meta_key = "bb_job_id_{$form_type}_{$form_id}";
        return get_option($meta_key, 0);
    }
    
    /**
     * Shortcode to set job context
     */
    public function job_context_shortcode($atts) {
        $atts = shortcode_atts(array(
            'job_id' => 0
        ), $atts);
        
        $job_id = intval($atts['job_id']);
        if (!$job_id) {
            global $post;
            if ($post && $post->post_type === 'job_vacancy') {
                $job_id = $post->ID;
            }
        }
        
        if ($job_id) {
            return '<input type="hidden" name="job-id" value="' . $job_id . '" class="bb-job-context" />';
        }
        
        return '';
    }
    
    /**
     * Add JavaScript for job context detection
     */
    public function add_job_context_script() {
        if (!is_singular('job_vacancy')) {
            return;
        }
        
        global $post;
        ?>
        <script>
        // Set job context for forms
        (function($) {
            $(document).ready(function() {
                var jobId = <?php echo $post->ID; ?>;
                
                // Store in session
                if (typeof(Storage) !== "undefined") {
                    sessionStorage.setItem('bb_current_job_id', jobId);
                }
                
                // Add hidden field to forms that don't have it
                $('form').each(function() {
                    var $form = $(this);
                    if (!$form.find('input[name="job-id"], input[name="job_id"]').length) {
                        $form.append('<input type="hidden" name="job-id" value="' + jobId + '" />');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Enqueue CF7 specific scripts
     */
    public function enqueue_cf7_scripts() {
        if (is_singular('job_vacancy')) {
            wp_add_inline_script('contact-form-7', '
                document.addEventListener("DOMContentLoaded", function() {
                    var jobId = ' . get_the_ID() . ';
                    var cf7Forms = document.querySelectorAll(".wpcf7-form");
                    cf7Forms.forEach(function(form) {
                        if (!form.querySelector("input[name=\"job-id\"]")) {
                            var hiddenField = document.createElement("input");
                            hiddenField.type = "hidden";
                            hiddenField.name = "job-id";
                            hiddenField.value = jobId;
                            form.appendChild(hiddenField);
                        }
                    });
                });
            ');
        }
    }
    
    // Helper methods for extracting data from different form types
    
    private function get_field_value($entry, $field_identifier) {
        if (is_numeric($field_identifier)) {
            return $entry[$field_identifier] ?? '';
        }
        
        // Try common field names
        $common_names = array(
            'email' => array('email', 'your-email', 'user_email', 'applicant_email'),
            'phone' => array('phone', 'your-phone', 'user_phone', 'applicant_phone'),
            'job_id' => array('job_id', 'job-id', 'jobid')
        );
        
        if (isset($common_names[$field_identifier])) {
            foreach ($common_names[$field_identifier] as $field_name) {
                if (isset($entry[$field_name]) && !empty($entry[$field_name])) {
                    return $entry[$field_name];
                }
            }
        }
        
        return $entry[$field_identifier] ?? '';
    }
    
    private function extract_name_from_entry($entry) {
        // Common values to exclude from name detection
        $exclude_values = [
            'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'Spain', 'Italy',
            'Male', 'Female', 'Other', 'Yes', 'No', 'Mr', 'Mrs', 'Ms', 'Dr', 'Prof', 'NONE', 'None',
            'Other (Specify)', 'Please Specify', 'Specify', 'Please Select', 'Select'
        ];
        
        // Company/organization indicators to exclude
        $company_indicators = ['Ltd', 'Limited', 'Inc', 'Corporation', 'Corp', 'LLC', 'Company', 'Co', 'Group', 'Associates', 'Partnership', 'LLP'];
        
        $first_names = [];
        $last_names = [];
        $full_names = [];
        $possible_names = [];
        
        // For Gravity Forms, iterate through numeric field IDs including decimals (e.g., 7.3, 7.6)
        foreach ($entry as $key => $value) {
            if ((is_numeric($key) || preg_match('/^\d+\.\d+$/', $key)) && !empty($value) && is_string($value)) {
                // Skip system fields
                if (in_array($key, ['id', 'form_id', 'post_id', 'date_created', 'date_updated', 'is_starred', 'is_read', 'ip', 'source_url', 'user_agent'])) {
                    continue;
                }
                
                // Skip excluded values
                if (in_array($value, $exclude_values)) {
                    continue;
                }
                
                // Skip values that look like company names
                $is_company = false;
                foreach ($company_indicators as $indicator) {
                    if (stripos($value, $indicator) !== false) {
                        $is_company = true;
                        break;
                    }
                }
                if ($is_company) {
                    continue;
                }
                
                // Check if this looks like a name (only letters, spaces, hyphens, apostrophes)
                if (preg_match('/^[a-zA-Z\s\'-\.]+$/', $value) && strlen($value) >= 2) {
                    // Prioritize name fields by common patterns
                    
                    // Complex form name fields (7.x pattern)
                    if (strpos($key, '7.3') !== false || strpos($key, '7.1') !== false) {
                        $first_names[] = $value;
                    } else if (strpos($key, '7.6') !== false || strpos($key, '7.2') !== false) {
                        $last_names[] = $value;
                    } else if (preg_match('/^7\.\d+$/', $key)) {
                        $first_names[] = $value; // Other name field components
                    }
                    // Simple form name fields (single digit IDs)
                    else if (strlen($value) >= 2 && strlen($value) <= 30) {
                        // Look for typical name patterns in simple forms
                        if (strpos($value, ' ') !== false) {
                            // Full name with space - high priority
                            $full_names[] = $value;
                        } else {
                            // Single word - could be first or last name
                            $possible_names[] = $value;
                        }
                    }
                }
            }
        }
        
        // Debug: Log what name components were found
        error_log("BB Recruitment Debug: Name extraction - First names: " . print_r($first_names, true));
        error_log("BB Recruitment Debug: Name extraction - Last names: " . print_r($last_names, true));
        error_log("BB Recruitment Debug: Name extraction - Full names: " . print_r($full_names, true));
        error_log("BB Recruitment Debug: Name extraction - Possible names: " . print_r($possible_names, true));
        
        // Return the best name combination
        if (!empty($full_names)) {
            return $full_names[0];
        }
        
        if (!empty($first_names) && !empty($last_names)) {
            return trim($first_names[0] . ' ' . $last_names[0]);
        }
        
        if (!empty($first_names)) {
            return $first_names[0];
        }
        
        if (!empty($possible_names)) {
            return $possible_names[0];
        }
        
        return '';
    }
    
    private function extract_email_from_entry($entry) {
        // First try common email field names
        $email = $this->get_field_value($entry, 'email');
        if ($email && is_email($email)) return $email;
        
        // For Gravity Forms, look through all fields (including decimals like 5.1, 22.1) for email addresses
        foreach ($entry as $key => $value) {
            if ((is_numeric($key) || preg_match('/^\d+(\.\d+)?$/', $key)) && !empty($value) && is_string($value)) {
                // Check if this value is an email address
                if (is_email($value)) {
                    return $value;
                }
                
                // Check if this looks like an email (in case is_email() is too strict)
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
                
                // Check with regex as last resort
                if (preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value)) {
                    return $value;
                }
            }
        }
        
        // Debug: Show all field values to help identify the email field
        error_log('BB Recruitment Debug: No email found. Available fields: ' . print_r(array_filter($entry, function($key) {
            return is_numeric($key) || preg_match('/^\d+(\.\d+)?$/', $key);
        }, ARRAY_FILTER_USE_KEY), true));
        
        return '';
    }
    
    private function extract_phone_from_entry($entry) {
        // First try common phone field names
        $phone = $this->get_field_value($entry, 'phone');
        if ($phone) return $phone;
        
        // For Gravity Forms, look through all fields for phone numbers
        foreach ($entry as $key => $value) {
            if (is_numeric($key) && !empty($value)) {
                // Check if this looks like a phone number
                if (preg_match('/^[\d\s\-\+\(\)\.]+$/', $value) && strlen($value) >= 10) {
                    return $value;
                }
            }
        }
        
        return '';
    }
    
    private function extract_name_from_cf7($data) {
        if (isset($data['your-name'])) return $data['your-name'];
        if (isset($data['name'])) return $data['name'];
        if (isset($data['first-name']) && isset($data['last-name'])) {
            return trim($data['first-name'] . ' ' . $data['last-name']);
        }
        return $data['first-name'] ?? $data['last-name'] ?? '';
    }
    
    private function extract_name_from_wpforms($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'name') {
                if (is_array($field['value'])) {
                    return trim(($field['value']['first'] ?? '') . ' ' . ($field['value']['last'] ?? ''));
                }
                return $field['value'];
            }
        }
        return '';
    }
    
    private function extract_email_from_wpforms($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'email') {
                return $field['value'];
            }
        }
        return '';
    }
    
    private function extract_phone_from_wpforms($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'phone') {
                return $field['value'];
            }
        }
        return '';
    }
    
    private function extract_name_from_ninja($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'firstname' || $field['type'] === 'name') {
                return $field['value'];
            }
        }
        return '';
    }
    
    private function extract_email_from_ninja($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'email') {
                return $field['value'];
            }
        }
        return '';
    }
    
    private function extract_phone_from_ninja($fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'phone') {
                return $field['value'];
            }
        }
        return '';
    }
    
    /**
     * Extract name from Gravity Form using field definitions
     */
    private function extract_name_from_gravity_form($entry, $form) {
        $first_name = '';
        $last_name = '';
        $full_name = '';
        
        // First, look for actual name fields using form field definitions
        foreach ($form['fields'] as $field) {
            if ($field->type === 'name') {
                // Complex name field - extract sub-components
                $name_value = $this->extract_complex_field_value($entry, $field);
                if (!empty($name_value)) {
                    return $name_value; // Return the full constructed name
                }
            }
        }
        
        // If no name field found, look for text fields with name-like labels
        foreach ($form['fields'] as $field) {
            if ($field->type === 'text' && !empty($field->label)) {
                $label_lower = strtolower($field->label);
                $field_value = $this->get_field_value($entry, $field->id);
                
                if (!empty($field_value) && $this->is_valid_name($field_value)) {
                    // Check if this looks like a name field based on label
                    if (strpos($label_lower, 'name') !== false) {
                        if (strpos($label_lower, 'first') !== false || strpos($label_lower, 'your name') !== false) {
                            $first_name = $field_value;
                        } else if (strpos($label_lower, 'last') !== false || strpos($label_lower, 'surname') !== false) {
                            $last_name = $field_value;
                        } else if (strpos($label_lower, 'full') !== false || $label_lower === 'name' || $label_lower === 'your name') {
                            $full_name = $field_value;
                        }
                    }
                }
            }
        }
        
        // Return the best name combination
        if (!empty($full_name)) {
            return $full_name;
        }
        
        if (!empty($first_name) && !empty($last_name)) {
            return trim($first_name . ' ' . $last_name);
        }
        
        if (!empty($first_name)) {
            return $first_name;
        }
        
        if (!empty($last_name)) {
            return $last_name;
        }
        
        // Fallback to old method if nothing found
        return $this->extract_name_from_entry($entry);
    }
    
    /**
     * Check if a value looks like a valid name
     */
    private function is_valid_name($value) {
        if (empty($value) || strlen($value) < 2 || strlen($value) > 50) {
            return false;
        }
        
        // Common exclusions
        $exclude_values = [
            'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'Spain', 'Italy',
            'Male', 'Female', 'Other', 'Yes', 'No', 'Mr', 'Mrs', 'Ms', 'Dr', 'Prof', 'NONE', 'None',
            'Other (Specify)', 'Please Specify', 'Specify', 'Please Select', 'Select'
        ];
        
        if (in_array($value, $exclude_values)) {
            return false;
        }
        
        // Company indicators
        $company_indicators = ['Ltd', 'Limited', 'Inc', 'Corporation', 'Corp', 'LLC', 'Company', 'Co', 'Group'];
        foreach ($company_indicators as $indicator) {
            if (stripos($value, $indicator) !== false) {
                return false;
            }
        }
        
        // Must contain only letters, spaces, hyphens, apostrophes
        return preg_match('/^[a-zA-Z\s\'-\.]+$/', $value);
    }
    
    /**
     * Extract complex field values (address, name, checkbox, etc.)
     */
    private function extract_complex_field_value($entry, $field) {
        $field_id = $field->id;
        $field_type = $field->type;
        
        switch ($field_type) {
            case 'address':
                // Address fields have sub-components like .1, .2, .3, etc.
                $address_parts = [];
                for ($i = 1; $i <= 8; $i++) {
                    $sub_field_id = $field_id . '.' . $i;
                    $value = $this->get_field_value($entry, $sub_field_id);
                    if (!empty($value)) {
                        $address_parts[] = $value;
                    }
                }
                return implode(', ', $address_parts);
                
            case 'name':
                // Name fields have sub-components for title, first, last, etc.
                $name_parts = [];
                $name_components = [
                    '.2' => 'prefix',    // Title (Mr, Mrs, etc.)
                    '.3' => 'first',     // First name
                    '.4' => 'middle',    // Middle name
                    '.6' => 'last',      // Last name
                    '.8' => 'suffix'     // Suffix (Jr, Sr, etc.)
                ];
                
                foreach ($name_components as $sub_id => $component) {
                    $sub_field_id = $field_id . $sub_id;
                    $value = $this->get_field_value($entry, $sub_field_id);
                    if (!empty($value)) {
                        $name_parts[$component] = $value;
                    }
                }
                
                // Build full name
                $full_name = '';
                if (isset($name_parts['prefix'])) $full_name .= $name_parts['prefix'] . ' ';
                if (isset($name_parts['first'])) $full_name .= $name_parts['first'] . ' ';
                if (isset($name_parts['middle'])) $full_name .= $name_parts['middle'] . ' ';
                if (isset($name_parts['last'])) $full_name .= $name_parts['last'];
                if (isset($name_parts['suffix'])) $full_name .= ' ' . $name_parts['suffix'];
                
                return trim($full_name);
                
            case 'checkbox':
                // Checkbox fields can have multiple selected values
                $selected_values = [];
                if (isset($field->choices)) {
                    foreach ($field->choices as $choice) {
                        $choice_field_id = $field_id . '.' . $choice['value'];
                        $value = $this->get_field_value($entry, $choice_field_id);
                        if (!empty($value)) {
                            $selected_values[] = $choice['text'];
                        }
                    }
                }
                return implode(', ', $selected_values);
                
            case 'radio':
                // Radio fields are simpler, just return the selected value
                return $this->get_field_value($entry, $field_id);
                
            default:
                return $this->get_field_value($entry, $field_id);
        }
    }
}