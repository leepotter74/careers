<?php
/**
 * Big Bundle Recruitment Manager Module
 * 
 * A comprehensive job posting and application management system
 * with nomination management, reporting, and winner displays.
 * 
 * @package BigBundle
 * @subpackage RecruitmentManager
 * @version 1.0.0
 * @author Big Wave Marketing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct module access - must be loaded through Big Bundle
if (!class_exists('Big_Bundle')) {
    return;
}

// Define plugin constants
define('BB_RECRUITMENT_MANAGER_VERSION', '1.0.0');
define('BB_RECRUITMENT_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BB_RECRUITMENT_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BB_RECRUITMENT_MANAGER_PLUGIN_FILE', __FILE__);

// Define mode detection constant - this module is always running in Big Bundle mode
define('RECRUITMENT_MANAGER_IS_BIG_BUNDLE_ACTIVE', true);

// Debug flag (uncomment to enable detailed logging)
// define('BB_RECRUITMENT_DEBUG', true);

/**
 * Main Recruitment Manager Plugin Class
 */
class BB_Recruitment_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 5); // Earlier priority
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Big Bundle module hooks
        add_action('big_bundle_module_loaded', array($this, 'on_module_loaded'), 10, 2);
        add_action('big_bundle_module_activated', array($this, 'activate'), 10, 1);
        add_action('big_bundle_module_deactivated', array($this, 'deactivate'), 10, 1);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes - check if files exist before requiring
        $core_files = array(
            'includes/class-recruitment-post-type.php',
            'includes/class-recruitment-admin.php',
            'includes/class-job-search.php',
            'includes/class-application-handler.php',
            'includes/class-application-manager.php',
            'includes/class-form-integration.php',
            'includes/class-application-notifications.php',
            'includes/class-user-profile.php',
            'includes/class-simple-job-alerts.php'
        );
        
        foreach ($core_files as $file) {
            $file_path = BB_RECRUITMENT_MANAGER_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Admin functionality
        if (is_admin()) {
            $admin_files = array(
                'admin/admin-menu.php'
            );
            
            foreach ($admin_files as $file) {
                $file_path = BB_RECRUITMENT_MANAGER_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
        
        // Public functionality
        $public_files = array(
            'public/shortcodes.php'
        );
        
        foreach ($public_files as $file) {
            $file_path = BB_RECRUITMENT_MANAGER_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize post type
        if (class_exists('BB_Recruitment_Post_Type')) {
            BB_Recruitment_Post_Type::get_instance();
        }
        
        // Initialize search functionality
        if (class_exists('BB_Job_Search')) {
            BB_Job_Search::get_instance();
        }
        
        // Initialize application handler
        if (class_exists('Application_Handler')) {
            Application_Handler::get_instance();
        }
        
        // Initialize application manager
        if (class_exists('BB_Application_Manager')) {
            BB_Application_Manager::get_instance();
        }
        
        // Initialize form integration
        if (class_exists('BB_Form_Integration')) {
            BB_Form_Integration::get_instance();
        }
        
        // Initialize notifications
        if (class_exists('BB_Application_Notifications')) {
            BB_Application_Notifications::get_instance();
        }
        
        // Initialize user profile functionality
        if (class_exists('User_Profile')) {
            User_Profile::get_instance();
        }
        
        // Initialize simple job alerts
        if (class_exists('BB_Simple_Job_Alerts')) {
            BB_Simple_Job_Alerts::get_instance();
        }
        
        
        // Initialize admin functionality
        if (is_admin() && class_exists('BB_Recruitment_Admin')) {
            BB_Recruitment_Admin::get_instance();
        }
        
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('recruitment-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Handle module loaded event
     */
    public function on_module_loaded($module_id, $module_config) {
        if ($module_id === 'recruitment-manager') {
            // Module-specific initialization after loading
        }
    }
    
    /**
     * Handle module activation
     */
    public function activate($module_id) {
        if ($module_id === 'recruitment-manager') {
            $this->create_tables();
            flush_rewrite_rules();
        }
    }
    
    /**
     * Handle module deactivation
     */
    public function deactivate($module_id) {
        if ($module_id === 'recruitment-manager') {
            flush_rewrite_rules();
        }
    }
    
    /**
     * Create plugin tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Applications table
        $applications_table = $wpdb->prefix . 'recruitment_applications';
        $applications_sql = "CREATE TABLE $applications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            job_id int(11) NOT NULL,
            applicant_name varchar(255) NOT NULL,
            applicant_email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT '',
            application_data longtext,
            application_status varchar(50) DEFAULT 'pending',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY applicant_email (applicant_email),
            KEY application_status (application_status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($applications_sql);
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return BB_RECRUITMENT_MANAGER_VERSION;
    }
    
    /**
     * Get plugin directory path
     */
    public function get_plugin_dir() {
        return BB_RECRUITMENT_MANAGER_PLUGIN_DIR;
    }
    
    /**
     * Get plugin directory URL
     */
    public function get_plugin_url() {
        return BB_RECRUITMENT_MANAGER_PLUGIN_URL;
    }
}

// Initialize the plugin
BB_Recruitment_Manager::get_instance();