<?php
/**
 * Recruitment Admin Class
 * 
 * Handles admin interface and menu integration for Recruitment Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Recruitment_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_head', array($this, 'admin_column_styles'));
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main Recruitment page under Big Bundle
        add_submenu_page(
            'big-bundle',
            __('Recruitment Manager', 'recruitment-manager'),
            __('Recruitment', 'recruitment-manager'),
            'manage_options',
            'bb-recruitment-dashboard',
            array($this, 'dashboard_page')
        );
        
        // Sub-items visible in the main Big Bundle menu
        add_submenu_page(
            'big-bundle',
            __('Job Vacancies', 'recruitment-manager'),
            '↳ ' . __('Job Vacancies', 'recruitment-manager'),
            'manage_options',
            'edit.php?post_type=job_vacancy'
        );
        
        add_submenu_page(
            'big-bundle',
            __('Add New Job', 'recruitment-manager'),
            '↳ ' . __('Add New Job', 'recruitment-manager'),
            'manage_options',
            'post-new.php?post_type=job_vacancy'
        );
        
        // Applications page is now handled by BB_Application_Manager
        
        add_submenu_page(
            'big-bundle',
            __('Job Categories', 'recruitment-manager'),
            '↳ ' . __('Job Categories', 'recruitment-manager'),
            'manage_options',
            'edit-tags.php?taxonomy=job_category&post_type=job_vacancy'
        );
        
        add_submenu_page(
            'big-bundle',
            __('Job Locations', 'recruitment-manager'),
            '↳ ' . __('Job Locations', 'recruitment-manager'),
            'manage_options',
            'edit-tags.php?taxonomy=job_location&post_type=job_vacancy'
        );
        
        add_submenu_page(
            'big-bundle',
            __('Recruitment Settings', 'recruitment-manager'),
            '↳ ' . __('Recruitment Settings', 'recruitment-manager'),
            'manage_options',
            'bb-recruitment-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'big-bundle',
            __('Form Setup Guide', 'recruitment-manager'),
            '↳ ' . __('Form Setup Guide', 'recruitment-manager'),
            'manage_options',
            'bb-recruitment-form-guide',
            array($this, 'form_guide_page')
        );
        
        // Remove the standalone job vacancy menu
        remove_menu_page('edit.php?post_type=job_vacancy');
    }
    
    /**
     * Dashboard page - redirect to job list like Praise Manager does
     */
    public function dashboard_page() {
        // Use JavaScript redirect since headers are already sent
        $redirect_url = admin_url('edit.php?post_type=job_vacancy');
        ?>
        <div class="wrap">
            <h1><?php _e('Recruitment Manager', 'recruitment-manager'); ?></h1>
            <p><?php _e('Redirecting to job vacancies...', 'recruitment-manager'); ?></p>
            <script type="text/javascript">
                window.location.href = '<?php echo esc_js($redirect_url); ?>';
            </script>
            <noscript>
                <p><a href="<?php echo esc_url($redirect_url); ?>"><?php _e('Click here if you are not redirected automatically', 'recruitment-manager'); ?></a></p>
            </noscript>
        </div>
        <?php
    }
    
    // Applications page removed - now handled by BB_Application_Manager
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Load the comprehensive settings page
        include BB_RECRUITMENT_MANAGER_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * Form setup guide page
     */
    public function form_guide_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Form Setup Guide', 'recruitment-manager'); ?></h1>
            <?php include BB_RECRUITMENT_MANAGER_PLUGIN_DIR . 'admin/form-setup-guide.php'; ?>
        </div>
        <?php
    }


    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'bb-recruitment') !== false) {
            wp_enqueue_style('bb-recruitment-admin', BB_RECRUITMENT_MANAGER_PLUGIN_URL . 'assets/css/admin-style.css', array(), BB_RECRUITMENT_MANAGER_VERSION);
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
            .column-closing_date { width: 120px; }
            .column-job_type { width: 100px; }
            .column-job_location { width: 150px; }
            </style>
            <?php
        }
    }
}