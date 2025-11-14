<?php
/**
 * Big Bundle Recruitment Manager Module
 * 
 * Complete job posting and application management system with online forms,
 * save-and-return functionality, and social sharing capabilities.
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

// Define module constants
define('BB_RECRUITMENT_VERSION', '1.0.0');
define('BB_RECRUITMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BB_RECRUITMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BB_RECRUITMENT_PLUGIN_FILE', __FILE__);

// Uncomment the line below to enable detailed debug logging for this module
// define('BB_RECRUITMENT_DEBUG', true);

/**
 * Main Recruitment Manager Module Class
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
     * Constructor - private to prevent direct instantiation
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the module
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components immediately for admin functionality
        $this->init_components();
        
        // Additional initialization hooks
        add_action('init', array($this, 'late_init'), 5);
        
        // Admin initialization
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
        }
        
        // Public initialization
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        
        // Module loaded hook
        do_action('bb_recruitment_manager_loaded');
        
        // Debug logging (conditional on both WP_DEBUG and module debug flag)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BB_RECRUITMENT_DEBUG') && BB_RECRUITMENT_DEBUG) {
            error_log('BB Recruitment Manager: Module initialized');
        }
    }
    
    /**
     * Late initialization for hooks that need to run on init
     */
    public function late_init() {
        // Any initialization that specifically needs to happen on the init hook
        // can be added here if needed
    }
    
    /**
     * Load module dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-recruitment-core.php';
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-job-post-type.php';
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-application-handler.php';
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-user-profile.php';
        require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-social-sharing.php';
        
        // Admin classes (only in admin)
        if (is_admin()) {
            require_once BB_RECRUITMENT_PLUGIN_DIR . 'includes/class-admin.php';
        }
    }
    
    /**
     * Initialize module components
     */
    public function init_components() {
        // Initialize core functionality
        Recruitment_Core::get_instance();
        
        // Initialize job post type
        Job_Post_Type::get_instance();
        
        // Initialize application handler
        Application_Handler::get_instance();
        
        // Initialize user profile integration
        User_Profile::get_instance();
        
        // Initialize social sharing
        Social_Sharing::get_instance();
        
        // Initialize admin interface (admin only)
        if (is_admin()) {
            Recruitment_Admin::get_instance();
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our admin pages
        if (strpos($hook_suffix, 'bb-recruitment') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bb-recruitment-admin-style',
            BB_RECRUITMENT_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            BB_RECRUITMENT_VERSION
        );
        
        wp_enqueue_script(
            'bb-recruitment-admin-script',
            BB_RECRUITMENT_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            BB_RECRUITMENT_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('bb-recruitment-admin-script', 'bbRecruitment', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bb_recruitment_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'big-bundle'),
                'loading' => __('Loading...', 'big-bundle'),
                'saved' => __('Saved successfully', 'big-bundle'),
                'error' => __('An error occurred', 'big-bundle')
            )
        ));
    }
    
    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'bb-recruitment-public-style',
            BB_RECRUITMENT_PLUGIN_URL . 'assets/css/public-style.css',
            array(),
            BB_RECRUITMENT_VERSION
        );
        
        wp_enqueue_script(
            'bb-recruitment-public-script',
            BB_RECRUITMENT_PLUGIN_URL . 'assets/js/public-script.js',
            array('jquery'),
            BB_RECRUITMENT_VERSION,
            true
        );
        
        // Localize public script
        wp_localize_script('bb-recruitment-public-script', 'bbRecruitmentPublic', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bb_recruitment_public_nonce'),
            'strings' => array(
                'application_saved' => __('Application saved successfully', 'big-bundle'),
                'application_submitted' => __('Application submitted successfully', 'big-bundle'),
                'error_occurred' => __('An error occurred. Please try again.', 'big-bundle'),
                'confirm_submit' => __('Are you ready to submit your application?', 'big-bundle')
            )
        ));
    }
    
    /**
     * Get module info
     */
    public function get_module_info() {
        return array(
            'name' => 'Recruitment Manager',
            'version' => BB_RECRUITMENT_VERSION,
            'description' => 'Complete job posting and application management system',
            'author' => 'Big Wave Marketing'
        );
    }
    
    /**
     * Module activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules for custom post types
        flush_rewrite_rules();
        
        // Debug logging (conditional)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BB_RECRUITMENT_DEBUG') && BB_RECRUITMENT_DEBUG) {
            error_log('BB Recruitment Manager: Module activated');
        }
    }
    
    /**
     * Module deactivation
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Debug logging (conditional)
        if (defined('WP_DEBUG') && WP_DEBUG && defined('BB_RECRUITMENT_DEBUG') && BB_RECRUITMENT_DEBUG) {
            error_log('BB Recruitment Manager: Module deactivated');
        }
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Applications table
        $applications_table = $wpdb->prefix . 'recruitment_applications';
        
        $applications_sql = "CREATE TABLE $applications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            job_id int(11) NOT NULL,
            user_id int(11) NULL,
            applicant_name varchar(255) NOT NULL,
            applicant_email varchar(255) NOT NULL,
            applicant_phone varchar(50),
            application_data longtext,
            application_status varchar(50) DEFAULT 'submitted',
            save_token varchar(255),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY user_id (user_id),
            KEY save_token (save_token),
            KEY application_status (application_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($applications_sql);
    }
    
    /**
     * Set default module options
     */
    private static function set_default_options() {
        $defaults = array(
            'bb_recruitment_email_notifications' => 1,
            'bb_recruitment_allow_guest_applications' => 1,
            'bb_recruitment_auto_expire_jobs' => 1,
            'bb_recruitment_social_sharing' => 1,
            'bb_recruitment_save_return_enabled' => 1,
            'bb_recruitment_application_fields' => array(
                'name' => array('required' => true, 'enabled' => true),
                'email' => array('required' => true, 'enabled' => true),
                'phone' => array('required' => false, 'enabled' => true),
                'cover_letter' => array('required' => true, 'enabled' => true),
                'cv_upload' => array('required' => false, 'enabled' => true)
            )
        );
        
        foreach ($defaults as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
    }
}

// Initialize the module
BB_Recruitment_Manager::get_instance();

// Hook activation and deactivation
register_activation_hook(__FILE__, array('BB_Recruitment_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('BB_Recruitment_Manager', 'deactivate'));