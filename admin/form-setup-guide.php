<?php
/**
 * Form Setup Guide
 * 
 * Provides instructions for setting up job application forms
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="bb-form-guide">
    <h2><?php _e('Setting Up Job Application Forms', 'recruitment-manager'); ?></h2>
    
    <div class="guide-sections">
        
        <!-- Gravity Forms Section -->
        <div class="guide-section">
            <h3><span class="dashicons dashicons-forms"></span> Gravity Forms Setup</h3>
            
            <div class="setup-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4><?php _e('Create Your Form', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Create a new Gravity Form with the following recommended fields:', 'recruitment-manager'); ?></p>
                        <ul>
                            <li><strong><?php _e('Name (Required)', 'recruitment-manager'); ?></strong> - Single Line Text or Name field</li>
                            <li><strong><?php _e('Email (Required)', 'recruitment-manager'); ?></strong> - Email field</li>
                            <li><strong><?php _e('Phone', 'recruitment-manager'); ?></strong> - Phone field</li>
                            <li><strong><?php _e('CV/Resume', 'recruitment-manager'); ?></strong> - File Upload field</li>
                            <li><strong><?php _e('Cover Letter', 'recruitment-manager'); ?></strong> - Paragraph Text field</li>
                            <li><strong><?php _e('Additional Questions', 'recruitment-manager'); ?></strong> - Any custom fields</li>
                        </ul>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4><?php _e('Add the Shortcode', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Copy your form shortcode and paste it into the "Form Shortcode" field when editing a job:', 'recruitment-manager'); ?></p>
                        <code>[gravityform id="1" title="false" description="false"]</code>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4><?php _e('Automatic Integration', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Applications will automatically appear in the Application Management dashboard. Job context is detected automatically when forms are submitted from job pages.', 'recruitment-manager'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form 7 Section -->
        <div class="guide-section">
            <h3><span class="dashicons dashicons-email-alt"></span> Contact Form 7 Setup</h3>
            
            <div class="setup-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4><?php _e('Create Your Form', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Create a CF7 form with these fields:', 'recruitment-manager'); ?></p>
                        <textarea readonly class="code-sample">
[text* your-name placeholder "Full Name"]
[email* your-email placeholder "Email Address"]
[tel your-phone placeholder "Phone Number"]
[file cv-upload filetypes:pdf|doc|docx]
[textarea* cover-letter placeholder "Cover Letter"]
[submit "Submit Application"]
                        </textarea>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4><?php _e('Add Job Context', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Add this hidden field to your form to ensure proper job association:', 'recruitment-manager'); ?></p>
                        <code>[hidden job-id]</code>
                        <p><small><?php _e('The job ID will be automatically populated when the form is displayed on a job page.', 'recruitment-manager'); ?></small></p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4><?php _e('Use the Shortcode', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Add your CF7 shortcode to the job\'s "Form Shortcode" field:', 'recruitment-manager'); ?></p>
                        <code>[contact-form-7 id="123" title="Job Application"]</code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- WPForms Section -->
        <div class="guide-section">
            <h3><span class="dashicons dashicons-feedback"></span> WPForms Setup</h3>
            
            <div class="setup-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4><?php _e('Create Form Fields', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Use WPForms builder to add:', 'recruitment-manager'); ?></p>
                        <ul>
                            <li>Name field (required)</li>
                            <li>Email field (required)</li>
                            <li>Phone field</li>
                            <li>File Upload field (for CV)</li>
                            <li>Paragraph Text (for cover letter)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4><?php _e('Add Shortcode to Job', 'recruitment-manager'); ?></h4>
                        <code>[wpforms id="123"]</code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Custom Integration Section -->
        <div class="guide-section">
            <h3><span class="dashicons dashicons-admin-tools"></span> Custom Integration</h3>
            
            <div class="setup-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4><?php _e('Using Action Hooks', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Developers can integrate any form system using this action hook:', 'recruitment-manager'); ?></p>
                        <pre class="code-sample">
do_action('bb_job_application_submitted', array(
    'job_id' => 123,
    'applicant_name' => 'John Doe',
    'applicant_email' => 'john@example.com',
    'phone' => '555-1234',
    'application_data' => $form_data, // Array or JSON
    'form_type' => 'custom_form'
));
                        </pre>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4><?php _e('Job Context Shortcode', 'recruitment-manager'); ?></h4>
                        <p><?php _e('Add this shortcode to any form to automatically include job context:', 'recruitment-manager'); ?></p>
                        <code>[bb_job_context]</code>
                        <p><small><?php _e('This adds a hidden field with the current job ID.', 'recruitment-manager'); ?></small></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Troubleshooting Section -->
        <div class="guide-section troubleshooting">
            <h3><span class="dashicons dashicons-sos"></span> Troubleshooting</h3>
            
            <div class="trouble-item">
                <h4><?php _e('Applications not appearing in dashboard?', 'recruitment-manager'); ?></h4>
                <ul>
                    <li><?php _e('Ensure your form has Name and Email fields', 'recruitment-manager'); ?></li>
                    <li><?php _e('Check that the form is submitted from a job page', 'recruitment-manager'); ?></li>
                    <li><?php _e('Verify the form shortcode is correct in the job settings', 'recruitment-manager'); ?></li>
                    <li><?php _e('Enable WordPress debug logging to see error messages', 'recruitment-manager'); ?></li>
                </ul>
            </div>
            
            <div class="trouble-item">
                <h4><?php _e('Wrong job associated with application?', 'recruitment-manager'); ?></h4>
                <ul>
                    <li><?php _e('Add a hidden "job-id" field to your form', 'recruitment-manager'); ?></li>
                    <li><?php _e('Use the [bb_job_context] shortcode in your form', 'recruitment-manager'); ?></li>
                    <li><?php _e('Ensure users are applying directly from the job page', 'recruitment-manager'); ?></li>
                </ul>
            </div>
            
            <div class="trouble-item">
                <h4><?php _e('Need to test the integration?', 'recruitment-manager'); ?></h4>
                <ul>
                    <li><?php _e('Create a test job with your form', 'recruitment-manager'); ?></li>
                    <li><?php _e('Submit a test application', 'recruitment-manager'); ?></li>
                    <li><?php _e('Check the Applications dashboard', 'recruitment-manager'); ?></li>
                    <li><?php _e('Look for entries in debug.log if issues occur', 'recruitment-manager'); ?></li>
                </ul>
            </div>
        </div>
        
    </div>
</div>

<style>
.bb-form-guide {
    max-width: 1000px;
    margin: 20px 0;
}

.guide-sections {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.guide-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.guide-section h3 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.guide-section h3 .dashicons {
    color: #0073aa;
    font-size: 20px;
}

.setup-steps {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.step {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}

.step-number {
    background: #0073aa;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.step-content p {
    margin: 10px 0;
    line-height: 1.6;
}

.step-content ul {
    margin: 10px 0 0 20px;
}

.step-content li {
    margin: 5px 0;
}

.code-sample {
    background: #f4f4f4;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    font-family: monospace;
    font-size: 13px;
    display: block;
    width: 100%;
    box-sizing: border-box;
    white-space: pre-wrap;
    margin: 10px 0;
}

code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 13px;
}

.troubleshooting {
    background: #fff8dc;
    border-color: #f0ad4e;
}

.trouble-item {
    margin: 15px 0;
}

.trouble-item h4 {
    color: #d97919;
    margin: 0 0 10px 0;
}

.trouble-item ul {
    margin: 10px 0 0 20px;
}

@media (max-width: 768px) {
    .step {
        flex-direction: column;
    }
    
    .step-number {
        align-self: flex-start;
    }
}
</style>