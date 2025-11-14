<?php
/**
 * Single Job Vacancy Template
 * 
 * Template for displaying individual job vacancy posts
 * Simplified for maximum compatibility
 */

get_header(); ?>

<div class="container">
    <div class="content">
        <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('job-vacancy-single'); ?>>
            
            <header class="job-header">
                <h1 class="job-title"><?php the_title(); ?></h1>
                
                <div class="job-meta-basic">
                    <span class="job-date">Posted: <?php echo get_the_date(); ?></span>
                    
                    <?php 
                    $categories = get_the_terms(get_the_ID(), 'job_category');
                    if ($categories && !is_wp_error($categories)) : ?>
                        <span class="job-categories">
                            Category: 
                            <?php 
                            $category_names = array();
                            foreach ($categories as $category) {
                                $category_names[] = '<a href="' . esc_url(get_term_link($category)) . '">' . esc_html($category->name) . '</a>';
                            }
                            echo implode(', ', $category_names);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="job-details">
                <?php
                $closing_date = get_post_meta(get_the_ID(), '_job_closing_date', true);
                $location = get_post_meta(get_the_ID(), '_job_location', true);
                $job_type = get_post_meta(get_the_ID(), '_job_type', true);
                $salary = get_post_meta(get_the_ID(), '_job_salary', true);
                $hours = get_post_meta(get_the_ID(), '_job_hours', true);
                $department = get_post_meta(get_the_ID(), '_job_department', true);
                ?>
                
                <div class="job-info-box">
                    <h3>Job Information</h3>
                    
                    <?php if ($closing_date) : ?>
                        <p><strong>Closing Date:</strong> 
                        <?php 
                        $formatted_date = date('l, j F Y', strtotime($closing_date));
                        $is_expired = strtotime($closing_date) < time();
                        if ($is_expired) {
                            echo '<span style="color: #dc3232; font-weight: bold;">' . esc_html($formatted_date) . ' (Expired)</span>';
                        } else {
                            echo esc_html($formatted_date);
                        }
                        ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($location) : ?>
                        <p><strong>Location:</strong> <?php echo esc_html($location); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($job_type) : ?>
                        <p><strong>Job Type:</strong> <?php echo esc_html(ucwords(str_replace('-', ' ', $job_type))); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($salary) : ?>
                        <p><strong>Salary:</strong> <?php echo esc_html($salary); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($hours) : ?>
                        <p><strong>Working Hours:</strong> <?php echo esc_html($hours); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($department) : ?>
                        <p><strong>Department:</strong> <?php echo esc_html($department); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="job-description">
                <h3>Job Description</h3>
                <?php the_content(); ?>
            </div>
            
            <?php
            // Display additional job details
            $qualifications = get_post_meta(get_the_ID(), '_job_qualifications', true);
            $experience = get_post_meta(get_the_ID(), '_job_experience', true);
            $skills = get_post_meta(get_the_ID(), '_job_skills', true);
            ?>
            
            <?php if ($qualifications) : ?>
                <div class="job-qualifications">
                    <h3>Required Qualifications</h3>
                    <p><?php echo nl2br(esc_html($qualifications)); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($experience) : ?>
                <div class="job-experience">
                    <h3>Required Experience</h3>
                    <p><?php echo nl2br(esc_html($experience)); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($skills) : ?>
                <div class="job-skills">
                    <h3>Required Skills</h3>
                    <p><?php echo nl2br(esc_html($skills)); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="job-actions">
                <p>
                    <a href="<?php echo get_post_type_archive_link('job_vacancy'); ?>" class="btn btn-secondary">
                        ← Back to All Jobs
                    </a>
                    
                    <?php
                    $application_method = get_post_meta(get_the_ID(), '_application_method', true);
                    $form_shortcode = get_post_meta(get_the_ID(), '_application_form_shortcode', true);
                    $external_url = get_post_meta(get_the_ID(), '_external_application_url', true);
                    $contact_email = get_post_meta(get_the_ID(), '_application_contact_email', true);
                    
                    $is_expired = $closing_date && strtotime($closing_date) < time();
                    
                    if (!$is_expired) :
                        if ($application_method === 'external' && $external_url) : ?>
                            <a href="<?php echo esc_url($external_url); ?>" class="btn btn-primary" target="_blank">
                                Apply Now
                            </a>
                        <?php elseif ($application_method === 'email' && $contact_email) : ?>
                            <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="btn btn-primary">
                                Apply by Email
                            </a>
                        <?php elseif ($application_method === 'internal' && $form_shortcode) : ?>
                            <a href="#apply-form" class="btn btn-primary scroll-to-form">
                                Apply Now
                            </a>
                        <?php else : ?>
                            <button onclick="alert('Application form not configured. Please contact us directly.')" class="btn btn-primary">
                                Apply Now
                            </button>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="btn btn-disabled">
                            Applications Closed
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            
            <?php
            // Display application form if using internal method with shortcode
            if (!$is_expired && $application_method === 'internal' && $form_shortcode) : ?>
                <div id="apply-form" class="application-form-section">
                    <h2>Apply for this Position</h2>
                    <div class="form-container">
                        <?php echo do_shortcode($form_shortcode); ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </article>
        <?php endwhile; ?>
    </div>
</div>

<style>
.job-vacancy-single {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

.job-header {
    margin-bottom: 30px;
    border-bottom: 2px solid #eee;
    padding-bottom: 20px;
}

.job-title {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 2em;
}

.job-meta-basic {
    color: #666;
    font-size: 14px;
}

.job-meta-basic span {
    margin-right: 20px;
    display: inline-block;
}

.job-info-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin: 20px 0;
    border-left: 4px solid #2271b1;
}

.job-info-box h3 {
    margin-top: 0;
    color: #2c3e50;
}

.job-info-box p {
    margin: 10px 0;
}

.job-description,
.job-qualifications,
.job-experience,
.job-skills {
    margin: 30px 0;
}

.job-description h3,
.job-qualifications h3,
.job-experience h3,
.job-skills h3 {
    color: #2c3e50;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

.job-actions {
    margin: 40px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    margin: 5px 10px;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #2271b1;
    color: white;
}

.btn-primary:hover {
    background: #1e5a96;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
}

.btn-disabled {
    background: #ccc;
    color: #666;
    cursor: not-allowed;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.content {
    background: white;
    min-height: 400px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 4px;
    margin: 20px 0;
}

@media (max-width: 768px) {
    .job-vacancy-single {
        padding: 10px;
    }
    
    .job-title {
        font-size: 1.5em;
    }
    
    .job-meta-basic span {
        display: block;
        margin: 5px 0;
    }
    
    .btn {
        display: block;
        margin: 10px 0;
    }
    
    .application-form-section {
        padding: 20px;
        margin: 20px 0;
    }
    
    .form-container {
        padding: 15px;
    }
}

/* Application Form Section */
.application-form-section {
    margin: 40px 0;
    padding: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e1e5e9;
}

.application-form-section h2 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 24px;
    text-align: center;
}

.form-container {
    background: white;
    padding: 20px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Smooth scroll behavior for Apply Now button */
html {
    scroll-behavior: smooth;
}

.scroll-to-form {
    position: relative;
}
</style>

<?php get_footer(); ?>