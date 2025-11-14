<?php
/**
 * Recruitment Admin Class
 * 
 * Handles admin interface and menu integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Recruitment_Admin {
    
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
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menus'));
        
        // Add to Big Bundle admin bar
        add_action('admin_bar_menu', array($this, 'add_admin_bar_items'), 100);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        
        // Custom admin columns CSS
        add_action('admin_head', array($this, 'admin_column_styles'));
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Debug logging
        error_log('BB Recruitment: add_admin_menus called');
        // Main Recruitment page under Big Bundle
        add_submenu_page(
            'big-bundle',
            __('Recruitment Manager', 'big-bundle'),
            __('Recruitment', 'big-bundle'),
            'manage_options',
            'bb-recruitment-dashboard',
            array($this, 'dashboard_page')
        );
        
        // Sub-items visible in the main Big Bundle menu
        add_submenu_page(
            'big-bundle',
            __('Job Vacancies', 'big-bundle'),
            __('↳ Job Vacancies', 'big-bundle'),
            'manage_options',
            'bb-job-vacancies',
            array($this, 'job_vacancies_redirect')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Add New Job', 'big-bundle'),
            __('↳ Add New Job', 'big-bundle'),
            'manage_options',
            'bb-add-new-job',
            array($this, 'add_new_job_redirect')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Applications', 'big-bundle'),
            __('↳ Applications', 'big-bundle'),
            'manage_options',
            'bb-recruitment-applications',
            array($this, 'applications_page')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Job Categories', 'big-bundle'),
            __('↳ Job Categories', 'big-bundle'),
            'manage_options',
            'bb-job-categories',
            array($this, 'job_categories_redirect')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Job Locations', 'big-bundle'),
            __('↳ Job Locations', 'big-bundle'),
            'manage_options',
            'bb-job-locations',
            array($this, 'job_locations_redirect')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Recruitment Settings', 'big-bundle'),
            __('↳ Recruitment Settings', 'big-bundle'),
            'manage_options',
            'bb-recruitment-settings',
            array($this, 'settings_page')
        );
        
        // Remove the standalone job vacancy menu
        remove_menu_page('edit.php?post_type=job_vacancy');
    }
    
    /**
     * Add items to admin bar
     */
    public function add_admin_bar_items($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add to Big Bundle parent menu
        $parent_id = 'big-bundle';
        
        // Check if Big Bundle parent exists
        $parent_node = $wp_admin_bar->get_node($parent_id);
        if (!$parent_node) {
            return;
        }
        
        $wp_admin_bar->add_menu(array(
            'parent' => $parent_id,
            'id' => 'bb-recruitment',
            'title' => __('Recruitment', 'big-bundle'),
            'href' => admin_url('admin.php?page=bb-recruitment-dashboard'),
            'meta' => array('class' => 'bb-admin-bar-recruitment')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'bb-recruitment',
            'id' => 'bb-recruitment-jobs',
            'title' => __('All Jobs', 'big-bundle'),
            'href' => admin_url('edit.php?post_type=job_vacancy')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'bb-recruitment',
            'id' => 'bb-recruitment-add-job',
            'title' => __('Add New Job', 'big-bundle'),
            'href' => admin_url('post-new.php?post_type=job_vacancy')
        ));
        
        $wp_admin_bar->add_menu(array(
            'parent' => 'bb-recruitment',
            'id' => 'bb-recruitment-applications',
            'title' => __('Applications', 'big-bundle'),
            'href' => admin_url('admin.php?page=bb-recruitment-applications')
        ));
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'bb_recruitment_stats',
            __('Recruitment Overview', 'big-bundle'),
            array($this, 'dashboard_widget_stats')
        );
        
        wp_add_dashboard_widget(
            'bb_recent_applications',
            __('Recent Applications', 'big-bundle'),
            array($this, 'dashboard_widget_recent_applications')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'admin/dashboard-page.php';
    }
    
    /**
     * Applications page
     */
    public function applications_page() {
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'admin/applications-page.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * Redirect to job vacancies list
     */
    public function job_vacancies_redirect() {
        wp_redirect(admin_url('edit.php?post_type=job_vacancy'));
        exit;
    }
    
    /**
     * Redirect to add new job
     */
    public function add_new_job_redirect() {
        wp_redirect(admin_url('post-new.php?post_type=job_vacancy'));
        exit;
    }
    
    /**
     * Redirect to job categories
     */
    public function job_categories_redirect() {
        wp_redirect(admin_url('edit-tags.php?taxonomy=job_category&post_type=job_vacancy'));
        exit;
    }
    
    /**
     * Redirect to job locations
     */
    public function job_locations_redirect() {
        wp_redirect(admin_url('edit-tags.php?taxonomy=job_location&post_type=job_vacancy'));
        exit;
    }
    
    /**
     * Dashboard widget - recruitment stats
     */
    public function dashboard_widget_stats() {
        $application_handler = Application_Handler::get_instance();
        $stats = $application_handler->get_stats();
        
        // Get job stats
        $job_counts = wp_count_posts('job_vacancy');
        $active_jobs = $job_counts->publish ?? 0;
        $expired_jobs = $job_counts->expired ?? 0;
        $draft_jobs = $job_counts->draft ?? 0;
        
        ?>
        <div class="bb-dashboard-stats">
            <div class="bb-stat-grid">
                <div class="bb-stat-item">
                    <div class="bb-stat-number"><?php echo intval($active_jobs); ?></div>
                    <div class="bb-stat-label"><?php _e('Active Jobs', 'big-bundle'); ?></div>
                </div>
                <div class="bb-stat-item">
                    <div class="bb-stat-number"><?php echo intval($stats['total']); ?></div>
                    <div class="bb-stat-label"><?php _e('Total Applications', 'big-bundle'); ?></div>
                </div>
                <div class="bb-stat-item">
                    <div class="bb-stat-number"><?php echo intval($stats['submitted']); ?></div>
                    <div class="bb-stat-label"><?php _e('New Applications', 'big-bundle'); ?></div>
                </div>
                <div class="bb-stat-item">
                    <div class="bb-stat-number"><?php echo intval($stats['shortlisted']); ?></div>
                    <div class="bb-stat-label"><?php _e('Shortlisted', 'big-bundle'); ?></div>
                </div>
            </div>
            
            <div class="bb-quick-actions">
                <h4><?php _e('Quick Actions', 'big-bundle'); ?></h4>
                <p>
                    <a href="<?php echo admin_url('post-new.php?post_type=job_vacancy'); ?>" class="button button-primary">
                        <?php _e('Add New Job', 'big-bundle'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button">
                        <?php _e('View Applications', 'big-bundle'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=job_vacancy'); ?>" class="button">
                        <?php _e('Manage Jobs', 'big-bundle'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <style>
        .bb-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .bb-stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .bb-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .bb-stat-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .bb-quick-actions h4 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Dashboard widget - recent applications
     */
    public function dashboard_widget_recent_applications() {
        $application_handler = Application_Handler::get_instance();
        $recent_applications = $application_handler->get_recent_applications(5);
        
        if (empty($recent_applications)) {
            echo '<p>' . __('No applications received yet.', 'big-bundle') . '</p>';
            return;
        }
        
        ?>
        <div class="bb-recent-applications">
            <ul>
                <?php foreach ($recent_applications as $application): ?>
                <li class="bb-application-item">
                    <div class="bb-application-details">
                        <strong><?php echo esc_html($application->applicant_name); ?></strong>
                        <br>
                        <small><?php echo esc_html($application->job_title ?: __('Job not found', 'big-bundle')); ?></small>
                        <br>
                        <span class="bb-application-date">
                            <?php echo human_time_diff(strtotime($application->created_date), current_time('timestamp')) . ' ' . __('ago', 'big-bundle'); ?>
                        </span>
                        <span class="bb-application-status bb-status-<?php echo esc_attr($application->application_status); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $application->application_status))); ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <p class="bb-view-all">
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button">
                    <?php _e('View All Applications', 'big-bundle'); ?>
                </a>
            </p>
        </div>
        
        <style>
        .bb-recent-applications ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .bb-application-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .bb-application-item:last-child {
            border-bottom: none;
        }
        .bb-application-details strong {
            color: #2c3e50;
        }
        .bb-application-date {
            color: #7f8c8d;
            font-size: 12px;
        }
        .bb-application-status {
            float: right;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .bb-status-submitted { background: #3498db; color: white; }
        .bb-status-under_review { background: #f39c12; color: white; }
        .bb-status-shortlisted { background: #27ae60; color: white; }
        .bb-status-rejected { background: #e74c3c; color: white; }
        .bb-view-all { 
            text-align: center; 
            margin-top: 15px; 
        }
        </style>
        <?php
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show setup reminders for new installations
        if (!get_option('bb_recruitment_setup_complete')) {
            $this->show_setup_notice();
        }
        
        // Show expiring jobs notice
        $this->show_expiring_jobs_notice();
    }
    
    /**
     * Setup notice for new installations
     */
    private function show_setup_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'recruitment') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible bb-recruitment-setup-notice">
            <h3><?php _e('Welcome to Recruitment Manager!', 'big-bundle'); ?></h3>
            <p>
                <?php _e('Get started by creating your first job vacancy and configuring your recruitment settings.', 'big-bundle'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('post-new.php?post_type=job_vacancy'); ?>" class="button button-primary">
                    <?php _e('Create First Job', 'big-bundle'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-settings'); ?>" class="button">
                    <?php _e('Configure Settings', 'big-bundle'); ?>
                </a>
                <a href="#" class="bb-dismiss-setup-notice"><?php _e('Dismiss', 'big-bundle'); ?></a>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.bb-dismiss-setup-notice').on('click', function(e) {
                e.preventDefault();
                $.post(ajaxurl, {
                    action: 'bb_dismiss_recruitment_setup_notice',
                    nonce: '<?php echo wp_create_nonce('bb_recruitment_setup_dismiss'); ?>'
                });
                $('.bb-recruitment-setup-notice').fadeOut();
            });
        });
        </script>
        <?php
        
        // AJAX handler for dismissing notice
        add_action('wp_ajax_bb_dismiss_recruitment_setup_notice', function() {
            check_ajax_referer('bb_recruitment_setup_dismiss', 'nonce');
            update_option('bb_recruitment_setup_complete', true);
            wp_die();
        });
    }
    
    /**
     * Show expiring jobs notice
     */
    private function show_expiring_jobs_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show on recruitment pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'recruitment') === false) {
            return;
        }
        
        // Check for jobs expiring in the next 7 days
        $expiring_jobs = new WP_Query(array(
            'post_type' => 'job_vacancy',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_job_closing_date',
                    'value' => array(date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            ),
            'posts_per_page' => -1
        ));
        
        if ($expiring_jobs->have_posts()) {
            ?>
            <div class="notice notice-warning">
                <h4><?php _e('Jobs Expiring Soon', 'big-bundle'); ?></h4>
                <p><?php printf(_n('%d job vacancy is expiring within the next week:', '%d job vacancies are expiring within the next week:', $expiring_jobs->post_count, 'big-bundle'), $expiring_jobs->post_count); ?></p>
                <ul>
                    <?php while ($expiring_jobs->have_posts()): $expiring_jobs->the_post(); ?>
                    <li>
                        <strong><?php the_title(); ?></strong> - 
                        <?php 
                        $closing_date = get_post_meta(get_the_ID(), '_job_closing_date', true);
                        echo date('d/m/Y', strtotime($closing_date));
                        ?>
                        <a href="<?php echo get_edit_post_link(); ?>" class="button button-small"><?php _e('Edit', 'big-bundle'); ?></a>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php
            wp_reset_postdata();
        }
    }
    
    /**
     * Admin column styles
     */
    public function admin_column_styles() {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'job_vacancy') {
            ?>
            <style>
            .bb-expired-date { color: #dc3232; }
            .bb-expired-label { 
                color: #dc3232; 
                font-weight: bold;
                font-size: 11px;
            }
            .column-applications { width: 100px; }
            .column-closing_date { width: 120px; }
            .column-job_type { width: 100px; }
            .column-job_location { width: 150px; }
            </style>
            <?php
        }
    }
    
    /**
     * Get admin capability
     */
    public function get_admin_capability() {
        return apply_filters('bb_recruitment_admin_capability', 'manage_options');
    }
    
    /**
     * Check if current user can manage recruitment
     */
    public function current_user_can_manage() {
        return current_user_can($this->get_admin_capability());
    }
}