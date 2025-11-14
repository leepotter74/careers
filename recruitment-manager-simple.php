<?php
/**
 * Simple Recruitment Manager
 * 
 * Minimal version for testing job post type functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
if (!defined('BB_RECRUITMENT_PLUGIN_DIR')) {
    define('BB_RECRUITMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('BB_RECRUITMENT_PLUGIN_URL')) {
    define('BB_RECRUITMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Simple Recruitment Manager Class
 */
class BB_Simple_Recruitment_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_filter('template_include', array($this, 'load_templates'));
    }
    
    /**
     * Register job vacancy post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => 'Job Vacancies',
            'singular_name' => 'Job Vacancy',
            'menu_name' => 'Job Vacancies',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Job Vacancy',
            'edit_item' => 'Edit Job Vacancy',
            'view_item' => 'View Job Vacancy',
            'all_items' => 'All Job Vacancies',
            'search_items' => 'Search Job Vacancies',
            'not_found' => 'No job vacancies found.',
            'not_found_in_trash' => 'No job vacancies found in Trash.'
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'jobs'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-businessman',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
            'show_in_rest' => true,
        );
        
        register_post_type('job_vacancy', $args);
        
        // Flush rewrite rules once
        if (!get_option('bb_simple_recruitment_flushed')) {
            flush_rewrite_rules();
            update_option('bb_simple_recruitment_flushed', true);
        }
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Job Categories
        register_taxonomy('job_category', 'job_vacancy', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => 'Job Categories',
                'singular_name' => 'Job Category',
                'search_items' => 'Search Job Categories',
                'all_items' => 'All Job Categories',
                'edit_item' => 'Edit Job Category',
                'add_new_item' => 'Add New Job Category',
                'menu_name' => 'Job Categories',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-category'),
            'show_in_rest' => true
        ));
        
        // Job Locations
        register_taxonomy('job_location', 'job_vacancy', array(
            'hierarchical' => false,
            'labels' => array(
                'name' => 'Job Locations',
                'singular_name' => 'Job Location',
                'search_items' => 'Search Job Locations',
                'all_items' => 'All Job Locations',
                'edit_item' => 'Edit Job Location',
                'add_new_item' => 'Add New Job Location',
                'menu_name' => 'Job Locations',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-location'),
            'show_in_rest' => true
        ));
    }
    
    /**
     * Load custom templates
     */
    public function load_templates($template) {
        // Single job vacancy template
        if (is_singular('job_vacancy')) {
            $plugin_template = BB_RECRUITMENT_PLUGIN_DIR . 'templates/single-job_vacancy-simple.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        // Job vacancy archive template (for now just use default template)
        if (is_post_type_archive('job_vacancy')) {
            // Let WordPress use default template for now
        }
        
        return $template;
    }
}

// Initialize the simple recruitment manager
BB_Simple_Recruitment_Manager::get_instance();