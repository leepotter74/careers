<?php
/**
 * Recruitment Manager Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if ($_POST && wp_verify_nonce($_POST['bb_recruitment_settings_nonce'] ?? '', 'bb_recruitment_settings')) {
    // Email branding settings
    update_option('bb_recruitment_sender_name', sanitize_text_field($_POST['bb_recruitment_sender_name'] ?? 'HR Team'));
    update_option('bb_recruitment_sender_email', sanitize_email($_POST['bb_recruitment_sender_email'] ?? ''));
    update_option('bb_recruitment_bcc_email', sanitize_email($_POST['bb_recruitment_bcc_email'] ?? ''));
    update_option('bb_recruitment_email_footer', wp_kses_post($_POST['bb_recruitment_email_footer'] ?? ''));

    // Email templates
    if (isset($_POST['email_templates']) && is_array($_POST['email_templates'])) {
        $templates = array();
        foreach ($_POST['email_templates'] as $key => $template) {
            $templates[sanitize_key($key)] = array(
                'subject' => sanitize_text_field($template['subject'] ?? ''),
                'body' => wp_kses_post($template['body'] ?? ''),
                'enabled' => isset($template['enabled']) ? true : false
            );
        }
        update_option('bb_recruitment_email_templates', $templates);
    }

    // Email notifications
    update_option('bb_recruitment_email_notifications', intval($_POST['email_notifications'] ?? 0));
    update_option('bb_recruitment_notification_emails', array_map('sanitize_email', array_filter(explode("\n", $_POST['notification_emails'] ?? ''))));

    // Application settings
    update_option('bb_recruitment_allow_guest_applications', intval($_POST['allow_guest_applications'] ?? 0));
    update_option('bb_recruitment_save_return_enabled', intval($_POST['save_return_enabled'] ?? 0));
    update_option('bb_recruitment_auto_expire_jobs', intval($_POST['auto_expire_jobs'] ?? 0));
    update_option('bb_recruitment_prevent_duplicate_applications', intval($_POST['prevent_duplicate_applications'] ?? 0));
    update_option('bb_recruitment_application_limit_per_job', intval($_POST['application_limit_per_job'] ?? 0));

    // Social sharing
    update_option('bb_recruitment_social_sharing', intval($_POST['social_sharing'] ?? 0));
    update_option('bb_recruitment_twitter_handle', sanitize_text_field($_POST['twitter_handle'] ?? ''));

    // Application fields
    $application_fields = array();
    $field_names = array('name', 'email', 'phone', 'cover_letter', 'experience', 'availability', 'cv_upload');

    foreach ($field_names as $field) {
        $application_fields[$field] = array(
            'enabled' => isset($_POST['fields'][$field]['enabled']) ? 1 : 0,
            'required' => isset($_POST['fields'][$field]['required']) ? 1 : 0
        );
    }

    update_option('bb_recruitment_application_fields', $application_fields);

    // Form customization
    update_option('bb_recruitment_success_message', wp_kses_post($_POST['bb_recruitment_success_message'] ?? ''));
    update_option('bb_recruitment_success_redirect_url', esc_url_raw($_POST['bb_recruitment_success_redirect_url'] ?? ''));

    // Page settings
    update_option('bb_recruitment_jobs_page_id', intval($_POST['jobs_page_id'] ?? 0));
    update_option('bb_recruitment_privacy_policy_url', esc_url_raw($_POST['privacy_policy_url'] ?? ''));

    // Archive slug
    $old_slug = get_option('bb_recruitment_archive_slug', 'jobs');
    $new_slug = sanitize_title($_POST['archive_slug'] ?? 'jobs');
    if (empty($new_slug)) {
        $new_slug = 'jobs';
    }
    update_option('bb_recruitment_archive_slug', $new_slug);

    // Flush rewrite rules if slug changed
    if ($old_slug !== $new_slug) {
        update_option('bb_recruitment_flush_rewrite_rules', true);
    }

    // Data retention
    update_option('bb_recruitment_data_retention_days', intval($_POST['data_retention_days'] ?? 365));

    // Mark setup as complete
    update_option('bb_recruitment_setup_complete', true);

    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'big-bundle') . '</p></div>';
}

// Get current settings
$email_notifications = get_option('bb_recruitment_email_notifications', 1);
$notification_emails = get_option('bb_recruitment_notification_emails', array());
$allow_guest_applications = get_option('bb_recruitment_allow_guest_applications', 1);
$save_return_enabled = get_option('bb_recruitment_save_return_enabled', 1);
$auto_expire_jobs = get_option('bb_recruitment_auto_expire_jobs', 1);
$social_sharing = get_option('bb_recruitment_social_sharing', 1);
$twitter_handle = get_option('bb_recruitment_twitter_handle', '');
$application_fields = get_option('bb_recruitment_application_fields', array());
$jobs_page_id = get_option('bb_recruitment_jobs_page_id', 0);
$privacy_policy_url = get_option('bb_recruitment_privacy_policy_url', '');
$archive_slug = get_option('bb_recruitment_archive_slug', 'jobs');
$data_retention_days = get_option('bb_recruitment_data_retention_days', 365);

// Default field settings
$default_fields = array(
    'name' => array('enabled' => 1, 'required' => 1),
    'email' => array('enabled' => 1, 'required' => 1),
    'phone' => array('enabled' => 1, 'required' => 0),
    'cover_letter' => array('enabled' => 1, 'required' => 1),
    'experience' => array('enabled' => 1, 'required' => 0),
    'availability' => array('enabled' => 1, 'required' => 0),
    'cv_upload' => array('enabled' => 0, 'required' => 0)
);

foreach ($default_fields as $field => $defaults) {
    if (!isset($application_fields[$field])) {
        $application_fields[$field] = $defaults;
    }
}

?>
<div class="wrap bb-recruitment-settings">
    <h1><?php _e('Recruitment Manager Settings', 'big-bundle'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('bb_recruitment_settings', 'bb_recruitment_settings_nonce'); ?>
        
        <div class="bb-settings-tabs">
            <nav class="bb-tab-nav">
                <a href="#general" class="bb-tab-link active"><?php _e('General', 'big-bundle'); ?></a>
                <a href="#applications" class="bb-tab-link"><?php _e('Applications', 'big-bundle'); ?></a>
                <a href="#email-templates" class="bb-tab-link"><?php _e('Email Templates', 'big-bundle'); ?></a>
                <a href="#notifications" class="bb-tab-link"><?php _e('Notifications', 'big-bundle'); ?></a>
                <a href="#social" class="bb-tab-link"><?php _e('Social Sharing', 'big-bundle'); ?></a>
                <a href="#privacy" class="bb-tab-link"><?php _e('Privacy', 'big-bundle'); ?></a>
            </nav>
            
            <!-- General Settings -->
            <div id="general" class="bb-tab-content active">
                <h2><?php _e('General Settings', 'big-bundle'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Jobs Archive Page', 'big-bundle'); ?></th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'jobs_page_id',
                                'selected' => $jobs_page_id,
                                'show_option_none' => __('Select a page...', 'big-bundle'),
                                'option_none_value' => 0
                            ));
                            ?>
                            <p class="description">
                                <?php _e('Select a page to display job listings. Use the [job_listings] shortcode on this page.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Jobs Archive URL Slug', 'big-bundle'); ?></th>
                        <td>
                            <input type="text" name="archive_slug" value="<?php echo esc_attr($archive_slug); ?>" class="regular-text" pattern="[a-z0-9\-]+" />
                            <p class="description">
                                <?php
                                $site_url = trailingslashit(get_site_url());
                                printf(
                                    __('The URL slug for the jobs archive. Current URL: <code>%s<strong>%s</strong></code><br>Use lowercase letters, numbers, and hyphens only. Example: "jobs", "careers", or "vacancies". Changes require permalinks to be refreshed.', 'big-bundle'),
                                    esc_html($site_url),
                                    esc_html($archive_slug)
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-expire Jobs', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_expire_jobs" value="1" <?php checked($auto_expire_jobs, 1); ?> />
                                <?php _e('Automatically expire jobs after their closing date', 'big-bundle'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, jobs will automatically change to "expired" status after their closing date.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Privacy Policy URL', 'big-bundle'); ?></th>
                        <td>
                            <input type="url" name="privacy_policy_url" value="<?php echo esc_attr($privacy_policy_url); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Link to your privacy policy. Will be shown on application forms.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Application Settings -->
            <div id="applications" class="bb-tab-content">
                <h2><?php _e('Application Settings', 'big-bundle'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Guest Applications', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_guest_applications" value="1" <?php checked($allow_guest_applications, 1); ?> />
                                <?php _e('Allow applications from non-registered users', 'big-bundle'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, visitors can apply for jobs without creating an account.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Save & Return', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="save_return_enabled" value="1" <?php checked($save_return_enabled, 1); ?> />
                                <?php _e('Enable save and return later functionality', 'big-bundle'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Allows applicants to save their progress and complete applications later.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Prevent Duplicate Applications', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="prevent_duplicate_applications" value="1"
                                       <?php checked(get_option('bb_recruitment_prevent_duplicate_applications', 0), 1); ?> />
                                <?php _e('Prevent same person from applying twice to the same job', 'big-bundle'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Checks email address to detect duplicate applications for the same job posting.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Application Limit Per Job', 'big-bundle'); ?></th>
                        <td>
                            <input type="number" name="application_limit_per_job"
                                   value="<?php echo esc_attr(get_option('bb_recruitment_application_limit_per_job', 0)); ?>"
                                   min="0" max="10000" style="width: 100px;" />
                            <p class="description">
                                <?php _e('Maximum number of applications allowed per job (0 = unlimited). Job will automatically close when limit is reached.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e('Form Customization', 'big-bundle'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Success Message', 'big-bundle'); ?></th>
                        <td>
                            <textarea name="bb_recruitment_success_message" rows="4" class="large-text"><?php
                                echo esc_textarea(get_option('bb_recruitment_success_message',
                                    'Thank you for your application! We have received your submission and will review it shortly.'));
                            ?></textarea>
                            <p class="description">
                                <?php _e('Message shown to applicants after successful submission', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Success Redirect URL', 'big-bundle'); ?></th>
                        <td>
                            <input type="url" name="bb_recruitment_success_redirect_url"
                                   value="<?php echo esc_attr(get_option('bb_recruitment_success_redirect_url', '')); ?>"
                                   class="regular-text" placeholder="https://" />
                            <p class="description">
                                <?php _e('Optional: Redirect applicants to this URL after submission (leave blank to show success message only)', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Application Form Fields', 'big-bundle'); ?></h3>
                <p class="description"><?php _e('Configure which fields are shown in application forms and whether they are required.', 'big-bundle'); ?></p>
                
                <table class="bb-fields-table">
                    <thead>
                        <tr>
                            <th><?php _e('Field', 'big-bundle'); ?></th>
                            <th><?php _e('Enabled', 'big-bundle'); ?></th>
                            <th><?php _e('Required', 'big-bundle'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Full Name', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[name][enabled]" value="1" 
                                       <?php checked($application_fields['name']['enabled'], 1); ?> disabled />
                                <span class="description"><?php _e('Always enabled', 'big-bundle'); ?></span>
                            </td>
                            <td>
                                <input type="checkbox" name="fields[name][required]" value="1" 
                                       <?php checked($application_fields['name']['required'], 1); ?> disabled />
                                <span class="description"><?php _e('Always required', 'big-bundle'); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('Email Address', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[email][enabled]" value="1" 
                                       <?php checked($application_fields['email']['enabled'], 1); ?> disabled />
                                <span class="description"><?php _e('Always enabled', 'big-bundle'); ?></span>
                            </td>
                            <td>
                                <input type="checkbox" name="fields[email][required]" value="1" 
                                       <?php checked($application_fields['email']['required'], 1); ?> disabled />
                                <span class="description"><?php _e('Always required', 'big-bundle'); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('Phone Number', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[phone][enabled]" value="1" 
                                       <?php checked($application_fields['phone']['enabled'], 1); ?> />
                            </td>
                            <td>
                                <input type="checkbox" name="fields[phone][required]" value="1" 
                                       <?php checked($application_fields['phone']['required'], 1); ?> />
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('Cover Letter', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[cover_letter][enabled]" value="1" 
                                       <?php checked($application_fields['cover_letter']['enabled'], 1); ?> />
                            </td>
                            <td>
                                <input type="checkbox" name="fields[cover_letter][required]" value="1" 
                                       <?php checked($application_fields['cover_letter']['required'], 1); ?> />
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('Relevant Experience', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[experience][enabled]" value="1" 
                                       <?php checked($application_fields['experience']['enabled'], 1); ?> />
                            </td>
                            <td>
                                <input type="checkbox" name="fields[experience][required]" value="1" 
                                       <?php checked($application_fields['experience']['required'], 1); ?> />
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('Availability', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[availability][enabled]" value="1" 
                                       <?php checked($application_fields['availability']['enabled'], 1); ?> />
                            </td>
                            <td>
                                <input type="checkbox" name="fields[availability][required]" value="1" 
                                       <?php checked($application_fields['availability']['required'], 1); ?> />
                            </td>
                        </tr>
                        
                        <tr>
                            <td><strong><?php _e('CV Upload', 'big-bundle'); ?></strong></td>
                            <td>
                                <input type="checkbox" name="fields[cv_upload][enabled]" value="1" 
                                       <?php checked($application_fields['cv_upload']['enabled'], 1); ?> />
                                <span class="description"><?php _e('Feature coming soon', 'big-bundle'); ?></span>
                            </td>
                            <td>
                                <input type="checkbox" name="fields[cv_upload][required]" value="1" 
                                       <?php checked($application_fields['cv_upload']['required'], 1); ?> disabled />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Email Templates Settings -->
            <div id="email-templates" class="bb-tab-content">
                <h2><?php _e('Email Templates', 'big-bundle'); ?></h2>
                <p class="description"><?php _e('Customize the email messages sent to applicants at different stages of the recruitment process.', 'big-bundle'); ?></p>

                <?php
                $template_manager = BB_Email_Template_Manager::get_instance();
                $all_templates = $template_manager->get_all_templates();
                $template_variables = $template_manager->get_template_variables();
                $saved_templates = get_option('bb_recruitment_email_templates', array());
                ?>

                <!-- Email Branding Settings -->
                <h3><?php _e('Email Branding', 'big-bundle'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Sender Name', 'big-bundle'); ?></th>
                        <td>
                            <input type="text" name="bb_recruitment_sender_name"
                                   value="<?php echo esc_attr(get_option('bb_recruitment_sender_name', 'HR Team')); ?>"
                                   class="regular-text" />
                            <p class="description"><?php _e('Name that appears in "From" field of emails', 'big-bundle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Sender Email', 'big-bundle'); ?></th>
                        <td>
                            <input type="email" name="bb_recruitment_sender_email"
                                   value="<?php echo esc_attr(get_option('bb_recruitment_sender_email', get_option('admin_email'))); ?>"
                                   class="regular-text" />
                            <p class="description"><?php _e('Email address used to send notifications', 'big-bundle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('BCC Email (Compliance)', 'big-bundle'); ?></th>
                        <td>
                            <input type="email" name="bb_recruitment_bcc_email"
                                   value="<?php echo esc_attr(get_option('bb_recruitment_bcc_email', '')); ?>"
                                   class="regular-text" />
                            <p class="description"><?php _e('Send blind copy of all emails to this address for compliance/record-keeping', 'big-bundle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email Footer', 'big-bundle'); ?></th>
                        <td>
                            <textarea name="bb_recruitment_email_footer" rows="4" class="large-text"><?php
                                echo esc_textarea(get_option('bb_recruitment_email_footer', ''));
                            ?></textarea>
                            <p class="description"><?php _e('Custom footer text added to all emails (e.g., company address, unsubscribe info)', 'big-bundle'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr style="margin: 30px 0;">

                <!-- Template Variables Reference -->
                <div class="bb-template-variables-box">
                    <h4><?php _e('Available Template Variables', 'big-bundle'); ?></h4>
                    <p class="description"><?php _e('Use these variables in your subject and body - they will be replaced with actual values:', 'big-bundle'); ?></p>
                    <div class="bb-variables-grid">
                        <?php foreach ($template_variables as $var => $desc): ?>
                            <div class="bb-variable-item">
                                <code><?php echo esc_html($var); ?></code>
                                <span><?php echo esc_html($desc); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <!-- Email Templates -->
                <h3><?php _e('Email Templates', 'big-bundle'); ?></h3>

                <div class="bb-email-templates-container">
                    <?php foreach ($all_templates as $template_key => $template_label): ?>
                        <?php
                        $template = $template_manager->get_template($template_key);
                        $is_custom = isset($saved_templates[$template_key]);
                        ?>

                        <div class="bb-email-template-editor" data-template-key="<?php echo esc_attr($template_key); ?>">
                            <div class="bb-template-header">
                                <h4>
                                    <?php echo esc_html($template_label); ?>
                                    <?php if ($is_custom): ?>
                                        <span class="bb-custom-badge"><?php _e('Customized', 'big-bundle'); ?></span>
                                    <?php endif; ?>
                                </h4>
                                <div class="bb-template-actions">
                                    <button type="button" class="button bb-preview-template" data-template-key="<?php echo esc_attr($template_key); ?>">
                                        <?php _e('Preview', 'big-bundle'); ?>
                                    </button>
                                    <button type="button" class="button bb-test-email" data-template-key="<?php echo esc_attr($template_key); ?>">
                                        <?php _e('Send Test', 'big-bundle'); ?>
                                    </button>
                                    <?php if ($is_custom): ?>
                                        <button type="button" class="button bb-reset-template" data-template-key="<?php echo esc_attr($template_key); ?>">
                                            <?php _e('Reset to Default', 'big-bundle'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="bb-template-fields">
                                <label>
                                    <input type="checkbox" name="email_templates[<?php echo esc_attr($template_key); ?>][enabled]" value="1"
                                           <?php checked($template['enabled'], true); ?> />
                                    <?php _e('Enable this email template', 'big-bundle'); ?>
                                </label>

                                <div class="bb-template-field">
                                    <label><?php _e('Subject Line', 'big-bundle'); ?></label>
                                    <input type="text" name="email_templates[<?php echo esc_attr($template_key); ?>][subject]"
                                           value="<?php echo esc_attr($template['subject']); ?>"
                                           class="large-text bb-template-subject" />
                                </div>

                                <div class="bb-template-field">
                                    <label><?php _e('Email Body', 'big-bundle'); ?></label>
                                    <textarea name="email_templates[<?php echo esc_attr($template_key); ?>][body]"
                                              rows="10" class="large-text bb-template-body"><?php echo esc_textarea($template['body']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Notification Settings -->
            <div id="notifications" class="bb-tab-content">
                <h2><?php _e('Email Notification Settings', 'big-bundle'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Email Notifications', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked($email_notifications, 1); ?> />
                                <?php _e('Send email notifications for new applications', 'big-bundle'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Notification Recipients', 'big-bundle'); ?></th>
                        <td>
                            <textarea name="notification_emails" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", $notification_emails)); ?></textarea>
                            <p class="description">
                                <?php _e('Enter email addresses to receive notifications about new applications (one per line). If left empty, notifications will be sent to the site admin email.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Email Templates', 'big-bundle'); ?></h3>
                <p class="description"><?php _e('Default email templates are used automatically. Custom templates will be available in a future update.', 'big-bundle'); ?></p>
                
                <div class="bb-email-preview">
                    <h4><?php _e('Applicant Confirmation Email Preview', 'big-bundle'); ?></h4>
                    <div class="bb-email-template">
                        <strong><?php _e('Subject:', 'big-bundle'); ?></strong> <?php _e('Application Received - [Job Title]', 'big-bundle'); ?><br><br>
                        <strong><?php _e('Body:', 'big-bundle'); ?></strong><br>
                        <?php _e('Dear [Applicant Name],', 'big-bundle'); ?><br><br>
                        <?php _e('Thank you for your application for the position of [Job Title].', 'big-bundle'); ?><br><br>
                        <?php _e('We have received your application and will review it shortly. We will be in touch if your application is successful.', 'big-bundle'); ?><br><br>
                        <?php _e('Thank you for your interest in our organization.', 'big-bundle'); ?><br><br>
                        <?php _e('Best regards,', 'big-bundle'); ?><br>
                        <?php _e('HR Team', 'big-bundle'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Social Sharing Settings -->
            <div id="social" class="bb-tab-content">
                <h2><?php _e('Social Sharing Settings', 'big-bundle'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Social Sharing', 'big-bundle'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="social_sharing" value="1" <?php checked($social_sharing, 1); ?> />
                                <?php _e('Show social sharing buttons on job posts', 'big-bundle'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Adds share buttons for Facebook, LinkedIn, Twitter, WhatsApp, and email.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Twitter Handle', 'big-bundle'); ?></th>
                        <td>
                            <input type="text" name="twitter_handle" value="<?php echo esc_attr($twitter_handle); ?>" class="regular-text" placeholder="@yourhandle" />
                            <p class="description">
                                <?php _e('Your Twitter handle (optional). Used in Twitter card metadata.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Sharing Analytics', 'big-bundle'); ?></h3>
                <p><?php _e('Social sharing statistics are tracked automatically and can be viewed in the dashboard.', 'big-bundle'); ?></p>
            </div>
            
            <!-- Privacy Settings -->
            <div id="privacy" class="bb-tab-content">
                <h2><?php _e('Privacy & Data Settings', 'big-bundle'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'big-bundle'); ?></th>
                        <td>
                            <input type="number" name="data_retention_days" value="<?php echo esc_attr($data_retention_days); ?>" min="30" max="3650" />
                            <?php _e('days', 'big-bundle'); ?>
                            <p class="description">
                                <?php _e('How long to keep application data after job closure. Minimum 30 days, maximum 10 years.', 'big-bundle'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('GDPR Compliance', 'big-bundle'); ?></h3>
                <div class="bb-gdpr-info">
                    <p><strong><?php _e('Data Collection:', 'big-bundle'); ?></strong> <?php _e('Application forms include consent checkboxes as required.', 'big-bundle'); ?></p>
                    <p><strong><?php _e('Data Access:', 'big-bundle'); ?></strong> <?php _e('Users can view their application history in their profile.', 'big-bundle'); ?></p>
                    <p><strong><?php _e('Data Export:', 'big-bundle'); ?></strong> <?php _e('Application data can be exported via the admin interface.', 'big-bundle'); ?></p>
                    <p><strong><?php _e('Data Deletion:', 'big-bundle'); ?></strong> <?php _e('Applications can be deleted from the admin interface.', 'big-bundle'); ?></p>
                </div>
                
                <div class="bb-cleanup-tools">
                    <h3><?php _e('Data Cleanup Tools', 'big-bundle'); ?></h3>
                    <p class="description"><?php _e('Tools for managing and cleaning up application data.', 'big-bundle'); ?></p>
                    
                    <div class="bb-cleanup-actions">
                        <button type="button" class="button" onclick="alert('Feature coming soon')">
                            <?php _e('Delete Expired Applications', 'big-bundle'); ?>
                        </button>
                        <button type="button" class="button" onclick="alert('Feature coming soon')">
                            <?php _e('Export All Data', 'big-bundle'); ?>
                        </button>
                        <button type="button" class="button button-link-delete" onclick="alert('Feature coming soon')">
                            <?php _e('Delete All Data', 'big-bundle'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Save Settings', 'big-bundle'); ?>" />
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.bb-tab-link').on('click', function(e) {
        e.preventDefault();
        
        const targetTab = $(this).attr('href');
        
        // Update active states
        $('.bb-tab-link').removeClass('active');
        $(this).addClass('active');
        
        $('.bb-tab-content').removeClass('active');
        $(targetTab).addClass('active');
    });
    
    // Field dependency logic
    $('input[name="fields[phone][enabled]"]').on('change', function() {
        const $required = $('input[name="fields[phone][required]"]');
        if (!this.checked) {
            $required.prop('checked', false).prop('disabled', true);
        } else {
            $required.prop('disabled', false);
        }
    }).trigger('change');
    
    $('input[name="fields[cover_letter][enabled]"]').on('change', function() {
        const $required = $('input[name="fields[cover_letter][required]"]');
        if (!this.checked) {
            $required.prop('checked', false).prop('disabled', true);
        } else {
            $required.prop('disabled', false);
        }
    }).trigger('change');
    
    $('input[name="fields[experience][enabled]"]').on('change', function() {
        const $required = $('input[name="fields[experience][required]"]');
        if (!this.checked) {
            $required.prop('checked', false).prop('disabled', true);
        } else {
            $required.prop('disabled', false);
        }
    }).trigger('change');
    
    $('input[name="fields[availability][enabled]"]').on('change', function() {
        const $required = $('input[name="fields[availability][required]"]');
        if (!this.checked) {
            $required.prop('checked', false).prop('disabled', true);
        } else {
            $required.prop('disabled', false);
        }
    }).trigger('change');

    // Email template preview
    $('.bb-preview-template').on('click', function() {
        const $editor = $(this).closest('.bb-email-template-editor');
        const subject = $editor.find('.bb-template-subject').val();
        const body = $editor.find('.bb-template-body').val();

        $.post(ajaxurl, {
            action: 'bb_preview_email_template',
            nonce: '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>',
            subject: subject,
            body: body
        }, function(response) {
            if (response.success) {
                const modal = $('<div class="bb-modal-overlay">' +
                    '<div class="bb-modal-content">' +
                    '<div class="bb-modal-header">' +
                    '<h3><?php _e('Email Preview', 'big-bundle'); ?></h3>' +
                    '<span class="bb-modal-close">&times;</span>' +
                    '</div>' +
                    '<div class="bb-modal-body">' +
                    '<div class="bb-preview-section">' +
                    '<strong><?php _e('Subject:', 'big-bundle'); ?></strong> ' + response.data.subject +
                    '</div>' +
                    '<div class="bb-preview-section">' +
                    '<strong><?php _e('Body:', 'big-bundle'); ?></strong><br>' +
                    '<div class="bb-preview-body">' + response.data.body + '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>');

                $('body').append(modal);
                modal.fadeIn(200);

                modal.find('.bb-modal-close, .bb-modal-overlay').on('click', function(e) {
                    if (e.target === this) {
                        modal.fadeOut(200, function() { $(this).remove(); });
                    }
                });
            } else {
                alert('<?php _e('Failed to generate preview', 'big-bundle'); ?>');
            }
        });
    });

    // Send test email
    $('.bb-test-email').on('click', function() {
        const templateKey = $(this).data('template-key');
        const testEmail = prompt('<?php _e('Enter email address to send test to:', 'big-bundle'); ?>', '<?php echo esc_js(wp_get_current_user()->user_email); ?>');

        if (!testEmail) return;

        $(this).prop('disabled', true).text('<?php _e('Sending...', 'big-bundle'); ?>');

        const $btn = $(this);

        $.post(ajaxurl, {
            action: 'bb_send_test_email',
            nonce: '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>',
            template_key: templateKey,
            test_email: testEmail
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Test email sent successfully!', 'big-bundle'); ?>');
            } else {
                alert('<?php _e('Failed to send test email:', 'big-bundle'); ?> ' + (response.data || ''));
            }
            $btn.prop('disabled', false).text('<?php _e('Send Test', 'big-bundle'); ?>');
        });
    });

    // Reset template to default
    $('.bb-reset-template').on('click', function() {
        if (!confirm('<?php _e('Reset this template to default? Your custom changes will be lost.', 'big-bundle'); ?>')) {
            return;
        }

        const templateKey = $(this).data('template-key');
        $(this).closest('.bb-email-template-editor').find('input[type="checkbox"]').prop('checked', true);

        // Template will reset on save - just reload for now
        location.reload();
    });
});
</script>

<style>
.bb-recruitment-settings {
    margin-right: 20px;
}

/* Tabs */
.bb-settings-tabs {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.bb-tab-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    margin: 0;
    padding: 0;
}

.bb-tab-link {
    display: block;
    padding: 15px 20px;
    text-decoration: none;
    color: #666;
    border-right: 1px solid #ddd;
    transition: all 0.2s ease;
}

.bb-tab-link:hover {
    background: #e9ecef;
    color: #333;
}

.bb-tab-link.active {
    background: white;
    color: #0073aa;
    font-weight: 600;
    position: relative;
}

.bb-tab-link.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: #0073aa;
}

.bb-tab-content {
    display: none;
    padding: 30px;
}

.bb-tab-content.active {
    display: block;
}

.bb-tab-content h2 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 25px;
}

/* Fields Table */
.bb-fields-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.bb-fields-table th,
.bb-fields-table td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.bb-fields-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.bb-fields-table td {
    vertical-align: middle;
}

.bb-fields-table .description {
    font-size: 12px;
    color: #666;
    font-style: italic;
    margin-left: 5px;
}

/* Email Preview */
.bb-email-preview {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-top: 20px;
}

.bb-email-template {
    background: white;
    padding: 20px;
    border-radius: 3px;
    border-left: 4px solid #0073aa;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
}

/* GDPR Info */
.bb-gdpr-info {
    background: #f0f8ff;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
}

.bb-gdpr-info p {
    margin: 8px 0;
}

/* Cleanup Tools */
.bb-cleanup-tools {
    background: #fff5f5;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #dc3232;
    margin-top: 20px;
}

.bb-cleanup-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Email Templates */
.bb-template-variables-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
    margin-bottom: 30px;
}

.bb-variables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.bb-variable-item {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.bb-variable-item code {
    background: #0073aa;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.bb-variable-item span {
    font-size: 13px;
    color: #666;
}

.bb-email-templates-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.bb-email-template-editor {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.bb-template-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bb-template-header h4 {
    margin: 0;
    font-size: 16px;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bb-custom-badge {
    background: #0073aa;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.bb-template-actions {
    display: flex;
    gap: 8px;
}

.bb-template-fields {
    padding: 20px;
}

.bb-template-field {
    margin-top: 15px;
}

.bb-template-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #2c3e50;
}

.bb-template-body {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

/* Modal */
.bb-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.bb-modal-content {
    background: white;
    border-radius: 5px;
    max-width: 700px;
    width: 100%;
    max-height: 80vh;
    overflow: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.bb-modal-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bb-modal-header h3 {
    margin: 0;
}

.bb-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #999;
    line-height: 1;
}

.bb-modal-close:hover {
    color: #333;
}

.bb-modal-body {
    padding: 20px;
}

.bb-preview-section {
    margin-bottom: 20px;
}

.bb-preview-body {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 3px;
    border-left: 4px solid #0073aa;
    margin-top: 10px;
    white-space: pre-wrap;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 768px) {
    .bb-tab-nav {
        flex-direction: column;
    }
    
    .bb-tab-link {
        border-right: none;
        border-bottom: 1px solid #ddd;
    }
    
    .bb-tab-content {
        padding: 20px;
    }
    
    .bb-fields-table {
        font-size: 14px;
    }
    
    .bb-fields-table th,
    .bb-fields-table td {
        padding: 8px 6px;
    }
    
    .bb-cleanup-actions {
        flex-direction: column;
    }
}
</style>