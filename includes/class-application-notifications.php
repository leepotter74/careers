<?php
/**
 * Application Notifications Class
 * 
 * Handles email notifications for new job applications
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Application_Notifications {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Listen for new applications
        add_action('bb_application_received', array($this, 'send_notification'), 10, 3);
        
        // Settings hooks
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register notification settings
     */
    public function register_settings() {
        register_setting('bb_recruitment_notifications', 'bb_recruitment_notification_email');
        register_setting('bb_recruitment_notifications', 'bb_recruitment_enable_notifications');
    }
    
    /**
     * Send notification when application is received
     */
    public function send_notification($job_id, $applicant_email, $form_type) {
        if (!$this->notifications_enabled()) {
            return;
        }
        
        $notification_email = $this->get_notification_email();
        if (!$notification_email) {
            return;
        }
        
        $job_post = get_post($job_id);
        if (!$job_post) {
            return;
        }
        
        $job_title = $job_post->post_title;
        $job_url = get_permalink($job_id);
        $admin_url = admin_url('admin.php?page=bb-recruitment-applications&job_id=' . $job_id);
        
        // Email subject
        $subject = sprintf(
            __('[%s] New Job Application: %s', 'recruitment-manager'),
            get_bloginfo('name'),
            $job_title
        );
        
        // Email message
        $message = $this->get_notification_message($job_title, $applicant_email, $job_url, $admin_url, $form_type);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Send the email
        $sent = wp_mail($notification_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log("BB Recruitment: Notification sent for application to job {$job_id}");
        } else {
            error_log("BB Recruitment: Failed to send notification for job {$job_id}");
        }
    }
    
    /**
     * Get notification message HTML
     */
    private function get_notification_message($job_title, $applicant_email, $job_url, $admin_url, $form_type) {
        $site_name = get_bloginfo('name');
        $site_url = get_home_url();
        
        $form_type_labels = array(
            'gravity_forms' => 'Gravity Forms',
            'contact_form_7' => 'Contact Form 7',
            'wpforms' => 'WPForms',
            'ninja_forms' => 'Ninja Forms',
            'custom' => 'Custom Form'
        );
        
        $form_label = $form_type_labels[$form_type] ?? ucfirst(str_replace('_', ' ', $form_type));
        
        $message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .details { background: white; padding: 20px; border-radius: 6px; margin: 20px 0; }
                .button { background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px; }
                .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
                .highlight { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>New Job Application Received</h1>
                </div>
                
                <div class="content">
                    <div class="highlight">
                        <strong>A new application has been submitted for: ' . esc_html($job_title) . '</strong>
                    </div>
                    
                    <div class="details">
                        <h3>Application Details:</h3>
                        <p><strong>Job Position:</strong> ' . esc_html($job_title) . '</p>
                        <p><strong>Applicant Email:</strong> <a href="mailto:' . esc_attr($applicant_email) . '">' . esc_html($applicant_email) . '</a></p>
                        <p><strong>Form Type:</strong> ' . esc_html($form_label) . '</p>
                        <p><strong>Date Received:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . esc_url($admin_url) . '" class="button">View Application Dashboard</a>
                        <a href="' . esc_url($job_url) . '" class="button" style="background: #28a745;">View Job Posting</a>
                    </div>
                    
                    <p>You can manage this application and all others through your recruitment dashboard. Use the status workflow to track the applicant through your hiring process:</p>
                    
                    <ul>
                        <li><strong>Pending</strong> - Newly received application</li>
                        <li><strong>Reviewed</strong> - Application has been reviewed</li>
                        <li><strong>Shortlisted</strong> - Candidate selected for interview</li>
                        <li><strong>Rejected</strong> - Application not successful</li>
                    </ul>
                </div>
                
                <div class="footer">
                    <p>This notification was sent by the Recruitment Manager on <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>
                    <p>To manage notification settings, visit your <a href="' . esc_url(admin_url('admin.php?page=bb-recruitment-settings')) . '">Recruitment Settings</a> page.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $message;
    }
    
    /**
     * Check if notifications are enabled
     */
    private function notifications_enabled() {
        return get_option('bb_recruitment_enable_notifications', true);
    }
    
    /**
     * Get notification email address
     */
    private function get_notification_email() {
        $email = get_option('bb_recruitment_notification_email');
        if (empty($email)) {
            $email = get_option('admin_email');
        }
        return is_email($email) ? $email : false;
    }
    
    /**
     * Add notification settings to settings page
     */
    public function render_notification_settings() {
        $notification_email = get_option('bb_recruitment_notification_email', get_option('admin_email'));
        $notifications_enabled = get_option('bb_recruitment_enable_notifications', true);
        
        ?>
        <div class="notification-settings">
            <h3><?php _e('Email Notifications', 'recruitment-manager'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bb_recruitment_enable_notifications"><?php _e('Enable Notifications', 'recruitment-manager'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="bb_recruitment_enable_notifications" 
                               name="bb_recruitment_enable_notifications" value="1" 
                               <?php checked($notifications_enabled, true); ?> />
                        <label for="bb_recruitment_enable_notifications">
                            <?php _e('Send email notifications when new applications are received', 'recruitment-manager'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="bb_recruitment_notification_email"><?php _e('Notification Email', 'recruitment-manager'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="bb_recruitment_notification_email" 
                               name="bb_recruitment_notification_email" 
                               value="<?php echo esc_attr($notification_email); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Email address to receive application notifications. Leave blank to use the site admin email.', 'recruitment-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}