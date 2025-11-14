<?php
/**
 * Job Post Type Class
 * 
 * Handles custom post type registration and meta fields for job vacancies
 */

if (!defined('ABSPATH')) {
    exit;
}

class Job_Post_Type {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Always register on init hook with high priority
        add_action('init', array($this, 'register_post_type'), 1);
        add_action('init', array($this, 'register_taxonomies'), 1);
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_fields'));
        
        // Load custom templates for frontend display
        add_filter('template_include', array($this, 'load_job_templates'));
        
        // Add custom columns to admin list
        add_filter('manage_job_vacancy_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_job_vacancy_posts_custom_column', array($this, 'populate_admin_columns'), 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-job_vacancy_sortable_columns', array($this, 'sortable_columns'));
        
        // Handle sorting
        add_action('pre_get_posts', array($this, 'handle_column_sorting'));
        
        // Add custom post statuses
        add_action('init', array($this, 'register_custom_post_statuses'));
        
        // Flush rewrite rules when post type is registered for the first time
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
        
        // Filter posts by status in admin
        add_action('restrict_manage_posts', array($this, 'add_status_filter'));
        add_filter('parse_query', array($this, 'filter_by_status'));
    }
    
    /**
     * Register job vacancy post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Job Vacancies', 'big-bundle'),
            'singular_name' => __('Job Vacancy', 'big-bundle'),
            'menu_name' => __('Job Vacancies', 'big-bundle'),
            'name_admin_bar' => __('Job Vacancy', 'big-bundle'),
            'add_new' => __('Add New', 'big-bundle'),
            'add_new_item' => __('Add New Job Vacancy', 'big-bundle'),
            'new_item' => __('New Job Vacancy', 'big-bundle'),
            'edit_item' => __('Edit Job Vacancy', 'big-bundle'),
            'view_item' => __('View Job Vacancy', 'big-bundle'),
            'all_items' => __('All Job Vacancies', 'big-bundle'),
            'search_items' => __('Search Job Vacancies', 'big-bundle'),
            'parent_item_colon' => __('Parent Job Vacancies:', 'big-bundle'),
            'not_found' => __('No job vacancies found.', 'big-bundle'),
            'not_found_in_trash' => __('No job vacancies found in Trash.', 'big-bundle')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it to Big Bundle menu
            'show_in_admin_bar' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'jobs'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-businessman',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
            'show_in_rest' => true, // Enable Gutenberg
        );
        
        register_post_type('job_vacancy', $args);
        
        // Set flag to flush rewrite rules if this is the first time registering
        if (!get_option('bb_recruitment_post_type_registered')) {
            update_option('bb_recruitment_flush_rewrite_rules', true);
            update_option('bb_recruitment_post_type_registered', true);
        }
    }
    
    /**
     * Register taxonomies for job vacancies
     */
    public function register_taxonomies() {
        // Job Categories
        $category_labels = array(
            'name' => __('Job Categories', 'big-bundle'),
            'singular_name' => __('Job Category', 'big-bundle'),
            'search_items' => __('Search Job Categories', 'big-bundle'),
            'all_items' => __('All Job Categories', 'big-bundle'),
            'parent_item' => __('Parent Job Category', 'big-bundle'),
            'parent_item_colon' => __('Parent Job Category:', 'big-bundle'),
            'edit_item' => __('Edit Job Category', 'big-bundle'),
            'update_item' => __('Update Job Category', 'big-bundle'),
            'add_new_item' => __('Add New Job Category', 'big-bundle'),
            'new_item_name' => __('New Job Category Name', 'big-bundle'),
            'menu_name' => __('Job Categories', 'big-bundle'),
        );
        
        register_taxonomy('job_category', 'job_vacancy', array(
            'hierarchical' => true,
            'labels' => $category_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-category'),
            'show_in_rest' => true
        ));
        
        // Job Locations
        $location_labels = array(
            'name' => __('Job Locations', 'big-bundle'),
            'singular_name' => __('Job Location', 'big-bundle'),
            'search_items' => __('Search Job Locations', 'big-bundle'),
            'all_items' => __('All Job Locations', 'big-bundle'),
            'edit_item' => __('Edit Job Location', 'big-bundle'),
            'update_item' => __('Update Job Location', 'big-bundle'),
            'add_new_item' => __('Add New Job Location', 'big-bundle'),
            'new_item_name' => __('New Job Location Name', 'big-bundle'),
            'menu_name' => __('Job Locations', 'big-bundle'),
        );
        
        register_taxonomy('job_location', 'job_vacancy', array(
            'hierarchical' => false,
            'labels' => $location_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-location'),
            'show_in_rest' => true
        ));
    }
    
    /**
     * Register custom post statuses
     */
    public function register_custom_post_statuses() {
        register_post_status('expired', array(
            'label' => __('Expired', 'big-bundle'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'big-bundle')
        ));
        
        register_post_status('on_hold', array(
            'label' => __('On Hold', 'big-bundle'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('On Hold <span class="count">(%s)</span>', 'On Hold <span class="count">(%s)</span>', 'big-bundle')
        ));
    }
    
    /**
     * Add meta boxes for job vacancy fields
     */
    public function add_meta_boxes() {
        add_meta_box(
            'job_details',
            __('Job Details', 'big-bundle'),
            array($this, 'job_details_meta_box'),
            'job_vacancy',
            'normal',
            'high'
        );
        
        add_meta_box(
            'job_requirements',
            __('Job Requirements', 'big-bundle'),
            array($this, 'job_requirements_meta_box'),
            'job_vacancy',
            'normal',
            'high'
        );
        
        add_meta_box(
            'application_settings',
            __('Application Settings', 'big-bundle'),
            array($this, 'application_settings_meta_box'),
            'job_vacancy',
            'side',
            'high'
        );
    }
    
    /**
     * Job details meta box
     */
    public function job_details_meta_box($post) {
        wp_nonce_field('job_vacancy_meta_nonce', 'job_vacancy_meta_nonce_field');
        
        $closing_date = get_post_meta($post->ID, '_job_closing_date', true);
        $location = get_post_meta($post->ID, '_job_location', true);
        $job_type = get_post_meta($post->ID, '_job_type', true);
        $salary = get_post_meta($post->ID, '_job_salary', true);
        $hours = get_post_meta($post->ID, '_job_hours', true);
        $department = get_post_meta($post->ID, '_job_department', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="job_closing_date"><?php _e('Closing Date', 'big-bundle'); ?></label></th>
                <td>
                    <input type="date" id="job_closing_date" name="_job_closing_date" 
                           value="<?php echo esc_attr($closing_date); ?>" class="regular-text" />
                    <p class="description"><?php _e('Applications will close at midnight on this date.', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_location"><?php _e('Location', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" id="job_location" name="_job_location" 
                           value="<?php echo esc_attr($location); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="job_type"><?php _e('Job Type', 'big-bundle'); ?></label></th>
                <td>
                    <select id="job_type" name="_job_type">
                        <option value=""><?php _e('Select Type', 'big-bundle'); ?></option>
                        <option value="full-time" <?php selected($job_type, 'full-time'); ?>><?php _e('Full Time', 'big-bundle'); ?></option>
                        <option value="part-time" <?php selected($job_type, 'part-time'); ?>><?php _e('Part Time', 'big-bundle'); ?></option>
                        <option value="contract" <?php selected($job_type, 'contract'); ?>><?php _e('Contract', 'big-bundle'); ?></option>
                        <option value="temporary" <?php selected($job_type, 'temporary'); ?>><?php _e('Temporary', 'big-bundle'); ?></option>
                        <option value="internship" <?php selected($job_type, 'internship'); ?>><?php _e('Internship', 'big-bundle'); ?></option>
                        <option value="volunteer" <?php selected($job_type, 'volunteer'); ?>><?php _e('Volunteer', 'big-bundle'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="job_salary"><?php _e('Salary/Rate', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" id="job_salary" name="_job_salary" 
                           value="<?php echo esc_attr($salary); ?>" class="regular-text" />
                    <p class="description"><?php _e('e.g. £25,000 per annum, £10.50 per hour, Competitive', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_hours"><?php _e('Working Hours', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" id="job_hours" name="_job_hours" 
                           value="<?php echo esc_attr($hours); ?>" class="regular-text" />
                    <p class="description"><?php _e('e.g. 37.5 hours per week, Flexible hours, Shift work', 'big-bundle'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_department"><?php _e('Department', 'big-bundle'); ?></label></th>
                <td>
                    <input type="text" id="job_department" name="_job_department" 
                           value="<?php echo esc_attr($department); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Job requirements meta box
     */
    public function job_requirements_meta_box($post) {
        $qualifications = get_post_meta($post->ID, '_job_qualifications', true);
        $experience = get_post_meta($post->ID, '_job_experience', true);
        $skills = get_post_meta($post->ID, '_job_skills', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="job_qualifications"><?php _e('Required Qualifications', 'big-bundle'); ?></label></th>
                <td>
                    <textarea id="job_qualifications" name="_job_qualifications" 
                              rows="4" class="large-text"><?php echo esc_textarea($qualifications); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="job_experience"><?php _e('Required Experience', 'big-bundle'); ?></label></th>
                <td>
                    <textarea id="job_experience" name="_job_experience" 
                              rows="4" class="large-text"><?php echo esc_textarea($experience); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="job_skills"><?php _e('Required Skills', 'big-bundle'); ?></label></th>
                <td>
                    <textarea id="job_skills" name="_job_skills" 
                              rows="4" class="large-text"><?php echo esc_textarea($skills); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Application settings meta box
     */
    public function application_settings_meta_box($post) {
        $application_method = get_post_meta($post->ID, '_application_method', true);
        $external_url = get_post_meta($post->ID, '_external_application_url', true);
        $contact_email = get_post_meta($post->ID, '_application_contact_email', true);
        $featured = get_post_meta($post->ID, '_job_featured', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="application_method"><?php _e('Application Method', 'big-bundle'); ?></label></th>
                <td>
                    <select id="application_method" name="_application_method">
                        <option value="internal" <?php selected($application_method, 'internal'); ?>>
                            <?php _e('Internal Form', 'big-bundle'); ?>
                        </option>
                        <option value="external" <?php selected($application_method, 'external'); ?>>
                            <?php _e('External Website', 'big-bundle'); ?>
                        </option>
                        <option value="email" <?php selected($application_method, 'email'); ?>>
                            <?php _e('Email Application', 'big-bundle'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr id="external_url_row" style="<?php echo ($application_method !== 'external') ? 'display:none;' : ''; ?>">
                <th><label for="external_application_url"><?php _e('External URL', 'big-bundle'); ?></label></th>
                <td>
                    <input type="url" id="external_application_url" name="_external_application_url" 
                           value="<?php echo esc_attr($external_url); ?>" class="regular-text" />
                </td>
            </tr>
            <tr id="contact_email_row" style="<?php echo ($application_method !== 'email') ? 'display:none;' : ''; ?>">
                <th><label for="application_contact_email"><?php _e('Contact Email', 'big-bundle'); ?></label></th>
                <td>
                    <input type="email" id="application_contact_email" name="_application_contact_email" 
                           value="<?php echo esc_attr($contact_email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="job_featured"><?php _e('Featured Job', 'big-bundle'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="job_featured" name="_job_featured" value="1" 
                               <?php checked($featured, 1); ?> />
                        <?php _e('Mark as featured job', 'big-bundle'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#application_method').change(function() {
                var method = $(this).val();
                $('#external_url_row').toggle(method === 'external');
                $('#contact_email_row').toggle(method === 'email');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save meta fields
     */
    public function save_meta_fields($post_id) {
        // Check nonce
        if (!isset($_POST['job_vacancy_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['job_vacancy_meta_nonce_field'], 'job_vacancy_meta_nonce')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'job_vacancy') {
            return;
        }
        
        // Save meta fields
        $meta_fields = array(
            '_job_closing_date',
            '_job_location',
            '_job_type',
            '_job_salary',
            '_job_hours',
            '_job_department',
            '_job_qualifications',
            '_job_experience',
            '_job_skills',
            '_application_method',
            '_external_application_url',
            '_application_contact_email'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle checkbox fields
        $featured = isset($_POST['_job_featured']) ? 1 : 0;
        update_post_meta($post_id, '_job_featured', $featured);
        
        // Auto-update post status based on closing date
        $closing_date = $_POST['_job_closing_date'] ?? '';
        if ($closing_date && strtotime($closing_date) < time()) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'expired'
            ));
        }
    }
    
    /**
     * Add custom columns to admin list
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            // Insert custom columns after title
            if ($key === 'title') {
                $new_columns['job_type'] = __('Type', 'big-bundle');
                $new_columns['job_location'] = __('Location', 'big-bundle');
                $new_columns['closing_date'] = __('Closing Date', 'big-bundle');
                $new_columns['applications'] = __('Applications', 'big-bundle');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate admin columns
     */
    public function populate_admin_columns($column, $post_id) {
        switch ($column) {
            case 'job_type':
                $type = get_post_meta($post_id, '_job_type', true);
                echo $type ? esc_html(ucwords(str_replace('-', ' ', $type))) : '—';
                break;
                
            case 'job_location':
                $location = get_post_meta($post_id, '_job_location', true);
                echo $location ? esc_html($location) : '—';
                break;
                
            case 'closing_date':
                $closing_date = get_post_meta($post_id, '_job_closing_date', true);
                if ($closing_date) {
                    $formatted_date = date('d/m/Y', strtotime($closing_date));
                    $is_expired = strtotime($closing_date) < time();
                    $class = $is_expired ? 'bb-expired-date' : '';
                    echo '<span class="' . esc_attr($class) . '">' . esc_html($formatted_date) . '</span>';
                    if ($is_expired) {
                        echo ' <span class="bb-expired-label">' . __('(Expired)', 'big-bundle') . '</span>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'applications':
                global $wpdb;
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}recruitment_applications WHERE job_id = %d",
                    $post_id
                ));
                
                if ($count > 0) {
                    $url = admin_url('admin.php?page=bb-recruitment-applications&job_id=' . $post_id);
                    echo '<a href="' . esc_url($url) . '">' . intval($count) . '</a>';
                } else {
                    echo '0';
                }
                break;
        }
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['job_type'] = 'job_type';
        $columns['job_location'] = 'job_location';
        $columns['closing_date'] = 'closing_date';
        return $columns;
    }
    
    /**
     * Handle column sorting
     */
    public function handle_column_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        switch ($orderby) {
            case 'job_type':
                $query->set('meta_key', '_job_type');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'job_location':
                $query->set('meta_key', '_job_location');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'closing_date':
                $query->set('meta_key', '_job_closing_date');
                $query->set('orderby', 'meta_value');
                $query->set('meta_type', 'DATE');
                break;
        }
    }
    
    /**
     * Add status filter to admin
     */
    public function add_status_filter() {
        global $typenow;
        
        if ($typenow === 'job_vacancy') {
            $selected = isset($_GET['job_status']) ? $_GET['job_status'] : '';
            ?>
            <select name="job_status">
                <option value=""><?php _e('All Statuses', 'big-bundle'); ?></option>
                <option value="publish" <?php selected($selected, 'publish'); ?>><?php _e('Active', 'big-bundle'); ?></option>
                <option value="expired" <?php selected($selected, 'expired'); ?>><?php _e('Expired', 'big-bundle'); ?></option>
                <option value="on_hold" <?php selected($selected, 'on_hold'); ?>><?php _e('On Hold', 'big-bundle'); ?></option>
                <option value="draft" <?php selected($selected, 'draft'); ?>><?php _e('Draft', 'big-bundle'); ?></option>
            </select>
            <?php
        }
    }
    
    /**
     * Filter posts by status
     */
    public function filter_by_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'job_vacancy' && isset($_GET['job_status']) && $_GET['job_status'] !== '') {
            $query->query_vars['post_status'] = sanitize_text_field($_GET['job_status']);
        }
    }
    
    /**
     * Load custom templates for job vacancy posts
     */
    public function load_job_templates($template) {
        global $post;
        
        // Single job vacancy template
        if (is_singular('job_vacancy')) {
            $plugin_template = BB_RECRUITMENT_PLUGIN_DIR . 'templates/single-job_vacancy.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        // Job vacancy archive template
        if (is_post_type_archive('job_vacancy')) {
            $plugin_template = BB_RECRUITMENT_PLUGIN_DIR . 'templates/archive-job_vacancy.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        // Job category archive template
        if (is_tax('job_category') || is_tax('job_location')) {
            $plugin_template = BB_RECRUITMENT_PLUGIN_DIR . 'templates/archive-job_vacancy.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Maybe flush rewrite rules if needed
     */
    public function maybe_flush_rewrite_rules() {
        // Check if we need to flush rewrite rules
        $flush_needed = get_option('bb_recruitment_flush_rewrite_rules', false);
        
        if ($flush_needed) {
            flush_rewrite_rules();
            delete_option('bb_recruitment_flush_rewrite_rules');
        }
    }
}