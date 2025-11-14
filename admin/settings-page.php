<?php
/**
 * Recruitment Manager Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if ($_POST && wp_verify_nonce($_POST['bb_recruitment_settings_nonce'] ?? '', 'bb_recruitment_settings')) {
    // Email notifications
    update_option('bb_recruitment_email_notifications', intval($_POST['email_notifications'] ?? 0));
    update_option('bb_recruitment_notification_emails', array_map('sanitize_email', array_filter(explode("\n", $_POST['notification_emails'] ?? ''))));
    
    // Application settings
    update_option('bb_recruitment_allow_guest_applications', intval($_POST['allow_guest_applications'] ?? 0));
    update_option('bb_recruitment_save_return_enabled', intval($_POST['save_return_enabled'] ?? 0));
    update_option('bb_recruitment_auto_expire_jobs', intval($_POST['auto_expire_jobs'] ?? 0));
    
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