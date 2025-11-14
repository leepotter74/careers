<?php
/**
 * Recruitment Manager Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get application handler
$application_handler = Application_Handler::get_instance();
$stats = $application_handler->get_stats();

// Get job statistics
$job_counts = wp_count_posts('job_vacancy');
$active_jobs = intval($job_counts->publish ?? 0);
$expired_jobs = intval($job_counts->expired ?? 0);
$draft_jobs = intval($job_counts->draft ?? 0);

// Get recent applications
$recent_applications = $application_handler->get_recent_applications(10);

// Get social sharing stats if enabled
$social_sharing = Social_Sharing::get_instance();
$top_shared_jobs = $social_sharing->get_top_shared_jobs(5);

?>
<div class="wrap bb-recruitment-dashboard">
    <h1>
        <?php _e('Recruitment Manager Dashboard', 'big-bundle'); ?>
        <a href="<?php echo admin_url('post-new.php?post_type=job_vacancy'); ?>" class="page-title-action">
            <?php _e('Add New Job', 'big-bundle'); ?>
        </a>
    </h1>
    
    <div class="bb-dashboard-grid">
        <!-- Overview Stats -->
        <div class="bb-dashboard-section bb-stats-overview">
            <h2><?php _e('Overview', 'big-bundle'); ?></h2>
            
            <div class="bb-stats-grid">
                <div class="bb-stat-card bb-stat-jobs">
                    <div class="bb-stat-icon">📋</div>
                    <div class="bb-stat-content">
                        <div class="bb-stat-number"><?php echo $active_jobs; ?></div>
                        <div class="bb-stat-label"><?php _e('Active Jobs', 'big-bundle'); ?></div>
                        <div class="bb-stat-detail">
                            <?php if ($draft_jobs > 0): ?>
                                <small><?php printf(__('%d drafts', 'big-bundle'), $draft_jobs); ?></small>
                            <?php endif; ?>
                            <?php if ($expired_jobs > 0): ?>
                                <small><?php printf(__('%d expired', 'big-bundle'), $expired_jobs); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="bb-stat-card bb-stat-applications">
                    <div class="bb-stat-icon">👥</div>
                    <div class="bb-stat-content">
                        <div class="bb-stat-number"><?php echo $stats['total']; ?></div>
                        <div class="bb-stat-label"><?php _e('Total Applications', 'big-bundle'); ?></div>
                        <div class="bb-stat-detail">
                            <small><?php printf(__('%d new', 'big-bundle'), $stats['submitted']); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="bb-stat-card bb-stat-shortlisted">
                    <div class="bb-stat-icon">⭐</div>
                    <div class="bb-stat-content">
                        <div class="bb-stat-number"><?php echo $stats['shortlisted']; ?></div>
                        <div class="bb-stat-label"><?php _e('Shortlisted', 'big-bundle'); ?></div>
                        <div class="bb-stat-detail">
                            <small><?php printf(__('%d interviewed', 'big-bundle'), $stats['interviewed']); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="bb-stat-card bb-stat-hired">
                    <div class="bb-stat-icon">✅</div>
                    <div class="bb-stat-content">
                        <div class="bb-stat-number"><?php echo $stats['hired']; ?></div>
                        <div class="bb-stat-label"><?php _e('Hired', 'big-bundle'); ?></div>
                        <div class="bb-stat-detail">
                            <small><?php printf(__('%d offered', 'big-bundle'), $stats['offered']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Application Status Breakdown -->
        <div class="bb-dashboard-section bb-application-breakdown">
            <h2><?php _e('Application Status Breakdown', 'big-bundle'); ?></h2>
            
            <div class="bb-status-list">
                <?php 
                $status_labels = array(
                    'submitted' => __('New Applications', 'big-bundle'),
                    'under_review' => __('Under Review', 'big-bundle'),
                    'shortlisted' => __('Shortlisted', 'big-bundle'),
                    'interviewed' => __('Interviewed', 'big-bundle'),
                    'offered' => __('Offered', 'big-bundle'),
                    'hired' => __('Hired', 'big-bundle'),
                    'rejected' => __('Rejected', 'big-bundle'),
                    'withdrawn' => __('Withdrawn', 'big-bundle')
                );
                
                foreach ($status_labels as $status => $label):
                    $count = $stats[$status] ?? 0;
                    if ($count > 0):
                ?>
                <div class="bb-status-item">
                    <span class="bb-status-badge bb-status-<?php echo esc_attr($status); ?>">
                        <?php echo $count; ?>
                    </span>
                    <span class="bb-status-name"><?php echo esc_html($label); ?></span>
                    <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications&status=' . $status); ?>" class="bb-status-link">
                        <?php _e('View', 'big-bundle'); ?>
                    </a>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="bb-dashboard-section bb-recent-applications">
            <h2>
                <?php _e('Recent Applications', 'big-bundle'); ?>
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="bb-section-link">
                    <?php _e('View All', 'big-bundle'); ?>
                </a>
            </h2>
            
            <?php if (!empty($recent_applications)): ?>
            <div class="bb-applications-list">
                <?php foreach ($recent_applications as $application): ?>
                <div class="bb-application-row">
                    <div class="bb-application-info">
                        <strong class="bb-applicant-name"><?php echo esc_html($application->applicant_name); ?></strong>
                        <div class="bb-application-meta">
                            <span class="bb-job-title"><?php echo esc_html($application->job_title ?: __('Job not found', 'big-bundle')); ?></span>
                            <span class="bb-application-date">
                                <?php echo human_time_diff(strtotime($application->created_date), current_time('timestamp')) . ' ' . __('ago', 'big-bundle'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="bb-application-actions">
                        <span class="bb-status-badge bb-status-<?php echo esc_attr($application->application_status); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $application->application_status))); ?>
                        </span>
                        <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications&application_id=' . $application->id); ?>" class="button button-small">
                            <?php _e('View', 'big-bundle'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="bb-no-data"><?php _e('No applications received yet.', 'big-bundle'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Top Shared Jobs -->
        <?php if ($top_shared_jobs->have_posts()): ?>
        <div class="bb-dashboard-section bb-top-shared">
            <h2><?php _e('Most Shared Jobs', 'big-bundle'); ?></h2>
            
            <div class="bb-shared-jobs-list">
                <?php while ($top_shared_jobs->have_posts()): $top_shared_jobs->the_post(); ?>
                <?php $share_count = $social_sharing->get_share_count(get_the_ID()); ?>
                <div class="bb-shared-job-row">
                    <div class="bb-job-info">
                        <strong><a href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a></strong>
                        <div class="bb-job-meta">
                            <?php 
                            $location = get_post_meta(get_the_ID(), '_job_location', true);
                            if ($location) echo esc_html($location);
                            ?>
                        </div>
                    </div>
                    <div class="bb-share-count">
                        <span class="bb-share-number"><?php echo $share_count; ?></span>
                        <span class="bb-share-label"><?php _e('shares', 'big-bundle'); ?></span>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="bb-dashboard-section bb-quick-actions">
            <h2><?php _e('Quick Actions', 'big-bundle'); ?></h2>
            
            <div class="bb-action-buttons">
                <a href="<?php echo admin_url('post-new.php?post_type=job_vacancy'); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Create New Job', 'big-bundle'); ?>
                </a>
                
                <a href="<?php echo admin_url('edit.php?post_type=job_vacancy'); ?>" class="button button-hero">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php _e('Manage Jobs', 'big-bundle'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button button-hero">
                    <span class="dashicons dashicons-groups"></span>
                    <?php _e('View Applications', 'big-bundle'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-settings'); ?>" class="button button-hero">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'big-bundle'); ?>
                </a>
            </div>
        </div>
        
        <!-- Tips & Getting Started -->
        <div class="bb-dashboard-section bb-tips">
            <h2><?php _e('Tips & Getting Started', 'big-bundle'); ?></h2>
            
            <div class="bb-tips-list">
                <div class="bb-tip-item">
                    <h4><?php _e('Create Job Categories', 'big-bundle'); ?></h4>
                    <p><?php _e('Organize your jobs with categories like "Full Time", "Part Time", "Management" etc.', 'big-bundle'); ?></p>
                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=job_category&post_type=job_vacancy'); ?>" class="button button-small">
                        <?php _e('Manage Categories', 'big-bundle'); ?>
                    </a>
                </div>
                
                <div class="bb-tip-item">
                    <h4><?php _e('Set Up Email Notifications', 'big-bundle'); ?></h4>
                    <p><?php _e('Configure who receives email notifications when new applications are submitted.', 'big-bundle'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=bb-recruitment-settings'); ?>" class="button button-small">
                        <?php _e('Configure Email', 'big-bundle'); ?>
                    </a>
                </div>
                
                <div class="bb-tip-item">
                    <h4><?php _e('Display Jobs on Your Site', 'big-bundle'); ?></h4>
                    <p><?php _e('Use the [job_listings] shortcode to display job vacancies on any page or post.', 'big-bundle'); ?></p>
                    <code>[job_listings limit="5"]</code>
                </div>
                
                <div class="bb-tip-item">
                    <h4><?php _e('Application Forms', 'big-bundle'); ?></h4>
                    <p><?php _e('Add [job_application_form] shortcode to job posts or use the built-in application system.', 'big-bundle'); ?></p>
                    <code>[job_application_form job_id="123"]</code>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bb-recruitment-dashboard {
    background: #f1f1f1;
    margin: -20px -20px 0 -12px;
    padding: 20px;
}

.bb-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.bb-dashboard-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.bb-dashboard-section h2 {
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #2c3e50;
}

.bb-section-link {
    font-size: 13px;
    text-decoration: none;
    color: #0073aa;
}

/* Stats Overview */
.bb-stats-overview {
    grid-column: 1 / -1;
}

.bb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.bb-stat-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    color: white;
    text-align: left;
}

.bb-stat-card.bb-stat-jobs { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
.bb-stat-card.bb-stat-applications { background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); }
.bb-stat-card.bb-stat-shortlisted { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
.bb-stat-card.bb-stat-hired { background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%); }

.bb-stat-icon {
    font-size: 24px;
    margin-right: 15px;
}

.bb-stat-number {
    font-size: 28px;
    font-weight: bold;
    line-height: 1;
}

.bb-stat-label {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 5px;
}

.bb-stat-detail {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 3px;
}

/* Status Breakdown */
.bb-status-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bb-status-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.bb-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 24px;
    padding: 0 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.bb-status-submitted { background: #3498db; }
.bb-status-under_review { background: #f39c12; }
.bb-status-shortlisted { background: #27ae60; }
.bb-status-interviewed { background: #9b59b6; }
.bb-status-offered { background: #16a085; }
.bb-status-hired { background: #2ecc71; }
.bb-status-rejected { background: #e74c3c; }
.bb-status-withdrawn { background: #95a5a6; }

.bb-status-name {
    flex: 1;
    font-weight: 500;
}

.bb-status-link {
    font-size: 12px;
    text-decoration: none;
    color: #0073aa;
}

/* Applications List */
.bb-applications-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bb-application-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 5px;
}

.bb-applicant-name {
    color: #2c3e50;
}

.bb-application-meta {
    font-size: 12px;
    color: #7f8c8d;
    margin-top: 3px;
}

.bb-job-title::after {
    content: " • ";
    margin: 0 5px;
}

.bb-application-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Shared Jobs */
.bb-shared-jobs-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bb-shared-job-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.bb-share-count {
    text-align: center;
}

.bb-share-number {
    display: block;
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
}

.bb-share-label {
    font-size: 11px;
    color: #7f8c8d;
}

/* Quick Actions */
.bb-action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.bb-action-buttons .button-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 15px;
    text-align: center;
    text-decoration: none;
}

/* Tips */
.bb-tips-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.bb-tip-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #3498db;
}

.bb-tip-item h4 {
    margin: 0 0 5px 0;
    color: #2c3e50;
}

.bb-tip-item p {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #555;
}

.bb-tip-item code {
    background: #e8f4fd;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    color: #2c3e50;
}

.bb-no-data {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    margin: 20px 0;
}

@media (max-width: 768px) {
    .bb-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .bb-stats-overview {
        grid-column: 1;
    }
    
    .bb-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .bb-action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>