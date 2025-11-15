<?php
/**
 * Email Template Manager Class
 *
 * Handles customizable email templates with variable replacement
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Email_Template_Manager {

    private static $instance = null;

    /**
     * Available template variables
     */
    private $template_variables = array(
        '{applicant_name}' => 'Applicant\'s full name',
        '{applicant_email}' => 'Applicant\'s email address',
        '{job_title}' => 'Job position title',
        '{job_url}' => 'Link to job posting',
        '{company_name}' => 'Company/site name',
        '{site_url}' => 'Website homepage URL',
        '{date}' => 'Current date',
        '{status}' => 'Application status',
        '{admin_email}' => 'Administrator email',
        '{application_id}' => 'Application ID number'
    );

    /**
     * Available email templates
     */
    private $email_templates = array(
        'application_received' => 'Application Received Confirmation',
        'under_review' => 'Application Under Review',
        'shortlisted' => 'Shortlisted for Interview',
        'interviewed' => 'Interview Completed',
        'offered' => 'Job Offer',
        'hired' => 'Welcome - Hired',
        'rejected' => 'Application Rejected',
        'withdrawn' => 'Application Withdrawn'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX handlers
        add_action('wp_ajax_bb_send_test_email', array($this, 'send_test_email_ajax'));
        add_action('wp_ajax_bb_preview_email_template', array($this, 'preview_email_template_ajax'));
    }

    /**
     * Get default email template
     */
    public function get_default_template($template_key) {
        $defaults = array(
            'application_received' => array(
                'subject' => 'Application Received - {job_title}',
                'body' => "Dear {applicant_name},\n\nThank you for your application for the position of {job_title}.\n\nWe have received your application and will review it shortly. If your skills and experience match our requirements, we will contact you to arrange the next steps.\n\nThank you for your interest in {company_name}.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'under_review' => array(
                'subject' => 'Application Update - {job_title}',
                'body' => "Dear {applicant_name},\n\nYour application for {job_title} is now under review.\n\nOur team is carefully reviewing your application and we will be in touch soon with an update.\n\nThank you for your patience.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'shortlisted' => array(
                'subject' => 'Congratulations - Shortlisted for {job_title}',
                'body' => "Dear {applicant_name},\n\nCongratulations! You have been shortlisted for the position of {job_title}.\n\nWe were impressed by your application and would like to invite you for an interview. We will contact you shortly to arrange a suitable time.\n\nWe look forward to meeting you.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'interviewed' => array(
                'subject' => 'Thank You - Interview for {job_title}',
                'body' => "Dear {applicant_name},\n\nThank you for attending the interview for {job_title}.\n\nWe appreciate the time you took to meet with us and learn more about the role. We will review all candidates and be in touch with our decision soon.\n\nThank you again for your interest in {company_name}.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'offered' => array(
                'subject' => 'Job Offer - {job_title}',
                'body' => "Dear {applicant_name},\n\nCongratulations! We are delighted to offer you the position of {job_title} at {company_name}.\n\nWe were very impressed with your experience and qualifications, and believe you will be a great addition to our team.\n\nWe will contact you separately with the formal offer details and next steps.\n\nWe look forward to welcoming you to the team.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'hired' => array(
                'subject' => 'Welcome to {company_name}!',
                'body' => "Dear {applicant_name},\n\nWelcome to {company_name}! We are excited to have you join our team as {job_title}.\n\nYou will receive separate communications regarding your start date, onboarding process, and other important details.\n\nIf you have any questions in the meantime, please don't hesitate to reach out.\n\nWe look forward to working with you!\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'rejected' => array(
                'subject' => 'Application Update - {job_title}',
                'body' => "Dear {applicant_name},\n\nThank you for your application for {job_title} at {company_name}.\n\nAfter careful consideration, we regret to inform you that we will not be proceeding with your application on this occasion. We received many high-quality applications, and the decision was very difficult.\n\nWe appreciate your interest in {company_name} and encourage you to apply for future positions that match your skills and experience.\n\nWe wish you every success in your job search.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => true
            ),
            'withdrawn' => array(
                'subject' => 'Application Withdrawn - {job_title}',
                'body' => "Dear {applicant_name},\n\nThis is to confirm that your application for {job_title} has been withdrawn as requested.\n\nWe appreciate your interest in {company_name} and hope you will consider applying for future opportunities that match your career goals.\n\nThank you and best wishes for your job search.\n\nBest regards,\n{sender_name}\n{company_name}",
                'enabled' => false
            )
        );

        return isset($defaults[$template_key]) ? $defaults[$template_key] : null;
    }

    /**
     * Get email template
     */
    public function get_template($template_key) {
        $templates = get_option('bb_recruitment_email_templates', array());

        // Return custom template if exists, otherwise default
        if (isset($templates[$template_key])) {
            // Unslash template data to remove excessive escaping
            return array(
                'subject' => wp_unslash($templates[$template_key]['subject'] ?? ''),
                'body' => wp_unslash($templates[$template_key]['body'] ?? ''),
                'enabled' => $templates[$template_key]['enabled'] ?? true
            );
        }

        return $this->get_default_template($template_key);
    }

    /**
     * Get all template keys and labels
     */
    public function get_all_templates() {
        return $this->email_templates;
    }

    /**
     * Get template variables
     */
    public function get_template_variables() {
        return $this->template_variables;
    }

    /**
     * Replace variables in template
     */
    public function replace_variables($content, $data) {
        $replacements = array(
            '{applicant_name}' => $data['applicant_name'] ?? '',
            '{applicant_email}' => $data['applicant_email'] ?? '',
            '{job_title}' => $data['job_title'] ?? '',
            '{job_url}' => $data['job_url'] ?? '',
            '{company_name}' => $data['company_name'] ?? get_bloginfo('name'),
            '{site_url}' => $data['site_url'] ?? get_home_url(),
            '{date}' => date_i18n(get_option('date_format')),
            '{status}' => $data['status'] ?? '',
            '{admin_email}' => get_option('admin_email'),
            '{application_id}' => $data['application_id'] ?? '',
            '{sender_name}' => $this->get_sender_name(),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Send email using template
     */
    public function send_email($template_key, $to_email, $data) {
        $template = $this->get_template($template_key);

        if (!$template || !$template['enabled']) {
            return false;
        }

        // Replace variables
        $subject = $this->replace_variables($template['subject'], $data);
        $body = $this->replace_variables($template['body'], $data);

        // Get email settings
        $sender_name = $this->get_sender_name();
        $sender_email = $this->get_sender_email();
        $bcc = $this->get_bcc_email();

        // Build headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );

        if (!empty($bcc)) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        // Add signature
        $body = $this->add_signature($body);

        // Send email
        return wp_mail($to_email, $subject, $body, $headers);
    }

    /**
     * Add email signature
     */
    private function add_signature($body) {
        $footer = get_option('bb_recruitment_email_footer', '');

        if (!empty($footer)) {
            $body .= "\n\n" . $footer;
        }

        return $body;
    }

    /**
     * Get sender name
     */
    private function get_sender_name() {
        return get_option('bb_recruitment_sender_name', 'HR Team');
    }

    /**
     * Get sender email
     */
    private function get_sender_email() {
        $email = get_option('bb_recruitment_sender_email', '');
        return !empty($email) ? $email : get_option('admin_email');
    }

    /**
     * Get BCC email
     */
    private function get_bcc_email() {
        return get_option('bb_recruitment_bcc_email', '');
    }

    /**
     * AJAX: Send test email
     */
    public function send_test_email_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }

        $template_key = sanitize_text_field($_POST['template_key'] ?? '');
        $test_email = sanitize_email($_POST['test_email'] ?? '');

        if (empty($template_key) || empty($test_email)) {
            wp_send_json_error(__('Missing required parameters.', 'big-bundle'));
        }

        // Sample data for test
        $test_data = array(
            'applicant_name' => 'John Smith',
            'applicant_email' => 'john.smith@example.com',
            'job_title' => 'Senior Marketing Manager',
            'job_url' => home_url('/jobs/senior-marketing-manager'),
            'status' => 'Shortlisted',
            'application_id' => '12345'
        );

        $result = $this->send_email($template_key, $test_email, $test_data);

        if ($result) {
            wp_send_json_success(__('Test email sent successfully!', 'big-bundle'));
        } else {
            wp_send_json_error(__('Failed to send test email. Please check your email settings.', 'big-bundle'));
        }
    }

    /**
     * AJAX: Preview email template
     */
    public function preview_email_template_ajax() {
        check_ajax_referer('bb_recruitment_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'big-bundle'));
        }

        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $body = wp_kses_post($_POST['body'] ?? '');

        // Sample data for preview
        $test_data = array(
            'applicant_name' => 'John Smith',
            'applicant_email' => 'john.smith@example.com',
            'job_title' => 'Senior Marketing Manager',
            'job_url' => home_url('/jobs/senior-marketing-manager'),
            'status' => 'Shortlisted',
            'application_id' => '12345',
            'sender_name' => $this->get_sender_name()
        );

        $preview_subject = $this->replace_variables($subject, $test_data);
        $preview_body = $this->replace_variables($body, $test_data);
        $preview_body = $this->add_signature($preview_body);

        wp_send_json_success(array(
            'subject' => $preview_subject,
            'body' => nl2br(esc_html($preview_body))
        ));
    }
}
