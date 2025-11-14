<?php
/**
 * Recruitment Post Type Class
 * 
 * Handles the registration and management of the job_vacancy custom post type
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Recruitment_Post_Type {
    
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
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('manage_job_vacancy_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_job_vacancy_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-job_vacancy_sortable_columns', array($this, 'sortable_columns'));
        add_action('restrict_manage_posts', array($this, 'add_admin_filters'));
        add_filter('parse_query', array($this, 'filter_jobs'));
        add_filter('post_row_actions', array($this, 'remove_quick_edit'), 10, 2);
        add_filter('template_include', array($this, 'load_templates'));
        
        // Flush rewrite rules once
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
    }
    
    /**
     * Register the job_vacancy custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Job Vacancies', 'Post type general name', 'recruitment-manager'),
            'singular_name'         => _x('Job Vacancy', 'Post type singular name', 'recruitment-manager'),
            'menu_name'             => _x('Jobs', 'Admin Menu text', 'recruitment-manager'),
            'name_admin_bar'        => _x('Job Vacancy', 'Add New on Toolbar', 'recruitment-manager'),
            'add_new'               => __('Add New', 'recruitment-manager'),
            'add_new_item'          => __('Add New Job', 'recruitment-manager'),
            'new_item'              => __('New Job', 'recruitment-manager'),
            'edit_item'             => __('Edit Job', 'recruitment-manager'),
            'view_item'             => __('View Job', 'recruitment-manager'),
            'all_items'             => __('All Jobs', 'recruitment-manager'),
            'search_items'          => __('Search Jobs', 'recruitment-manager'),
            'parent_item_colon'     => __('Parent Jobs:', 'recruitment-manager'),
            'not_found'             => __('No jobs found.', 'recruitment-manager'),
            'not_found_in_trash'    => __('No jobs found in Trash.', 'recruitment-manager'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it manually to Big Bundle
            'query_var'          => true,
            'rewrite'            => array('slug' => 'jobs'),
            'capability_type'    => 'post',
            'capabilities'       => array(
                'edit_post'          => 'edit_posts',
                'read_post'          => 'read_posts',
                'delete_post'        => 'delete_posts',
                'edit_posts'         => 'edit_posts',
                'edit_others_posts'  => 'edit_others_posts',
                'delete_posts'       => 'delete_posts',
                'publish_posts'      => 'publish_posts',
                'read_private_posts' => 'read_private_posts',
            ),
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-businessman',
            'supports'           => array('title', 'editor', 'excerpt', 'thumbnail'),
            'show_in_rest'       => true,
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
            'name' => __('Job Categories', 'recruitment-manager'),
            'singular_name' => __('Job Category', 'recruitment-manager'),
            'search_items' => __('Search Job Categories', 'recruitment-manager'),
            'all_items' => __('All Job Categories', 'recruitment-manager'),
            'parent_item' => __('Parent Job Category', 'recruitment-manager'),
            'parent_item_colon' => __('Parent Job Category:', 'recruitment-manager'),
            'edit_item' => __('Edit Job Category', 'recruitment-manager'),
            'update_item' => __('Update Job Category', 'recruitment-manager'),
            'add_new_item' => __('Add New Job Category', 'recruitment-manager'),
            'new_item_name' => __('New Job Category Name', 'recruitment-manager'),
            'menu_name' => __('Job Categories', 'recruitment-manager'),
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
            'name' => __('Job Locations', 'recruitment-manager'),
            'singular_name' => __('Job Location', 'recruitment-manager'),
            'search_items' => __('Search Job Locations', 'recruitment-manager'),
            'all_items' => __('All Job Locations', 'recruitment-manager'),
            'edit_item' => __('Edit Job Location', 'recruitment-manager'),
            'update_item' => __('Update Job Location', 'recruitment-manager'),
            'add_new_item' => __('Add New Job Location', 'recruitment-manager'),
            'new_item_name' => __('New Job Location Name', 'recruitment-manager'),
            'menu_name' => __('Job Locations', 'recruitment-manager'),
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
     * Add meta boxes for job vacancy fields
     */
    public function add_meta_boxes() {
        add_meta_box(
            'job_details',
            __('Job Details', 'recruitment-manager'),
            array($this, 'job_details_meta_box'),
            'job_vacancy',
            'normal',
            'high'
        );
        
        add_meta_box(
            'job_requirements',
            __('Job Requirements', 'recruitment-manager'),
            array($this, 'job_requirements_meta_box'),
            'job_vacancy',
            'normal',
            'high'
        );
        
        add_meta_box(
            'application_settings',
            __('Application Settings', 'recruitment-manager'),
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
        $job_type = get_post_meta($post->ID, '_job_type', true);
        $salary = get_post_meta($post->ID, '_job_salary', true);
        $hours = get_post_meta($post->ID, '_job_hours', true);
        $department = get_post_meta($post->ID, '_job_department', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="job_closing_date"><?php _e('Closing Date', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="date" id="job_closing_date" name="_job_closing_date" 
                           value="<?php echo esc_attr($closing_date); ?>" class="regular-text" />
                    <p class="description"><?php _e('Applications will close at midnight on this date.', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_type"><?php _e('Job Type', 'recruitment-manager'); ?></label></th>
                <td>
                    <select id="job_type" name="_job_type">
                        <option value=""><?php _e('Select Type', 'recruitment-manager'); ?></option>
                        <option value="full-time" <?php selected($job_type, 'full-time'); ?>><?php _e('Full Time', 'recruitment-manager'); ?></option>
                        <option value="part-time" <?php selected($job_type, 'part-time'); ?>><?php _e('Part Time', 'recruitment-manager'); ?></option>
                        <option value="contract" <?php selected($job_type, 'contract'); ?>><?php _e('Contract', 'recruitment-manager'); ?></option>
                        <option value="temporary" <?php selected($job_type, 'temporary'); ?>><?php _e('Temporary', 'recruitment-manager'); ?></option>
                        <option value="internship" <?php selected($job_type, 'internship'); ?>><?php _e('Internship', 'recruitment-manager'); ?></option>
                        <option value="volunteer" <?php selected($job_type, 'volunteer'); ?>><?php _e('Volunteer', 'recruitment-manager'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="job_salary"><?php _e('Salary/Rate', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="text" id="job_salary" name="_job_salary" 
                           value="<?php echo esc_attr($salary); ?>" class="regular-text" />
                    <p class="description"><?php _e('e.g. £25,000 per annum, £10.50 per hour, Competitive', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_hours"><?php _e('Working Hours', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="text" id="job_hours" name="_job_hours" 
                           value="<?php echo esc_attr($hours); ?>" class="regular-text" />
                    <p class="description"><?php _e('e.g. 37.5 hours per week, Flexible hours, Shift work', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_department"><?php _e('Department', 'recruitment-manager'); ?></label></th>
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
                <th><label for="job_qualifications"><?php _e('Required Qualifications', 'recruitment-manager'); ?></label></th>
                <td>
                    <textarea id="job_qualifications" name="_job_qualifications" 
                              rows="4" class="large-text"><?php echo esc_textarea($qualifications); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="job_experience"><?php _e('Required Experience', 'recruitment-manager'); ?></label></th>
                <td>
                    <textarea id="job_experience" name="_job_experience" 
                              rows="4" class="large-text"><?php echo esc_textarea($experience); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="job_skills"><?php _e('Required Skills', 'recruitment-manager'); ?></label></th>
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
        $form_shortcode = get_post_meta($post->ID, '_application_form_shortcode', true);
        $external_url = get_post_meta($post->ID, '_external_application_url', true);
        $contact_email = get_post_meta($post->ID, '_application_contact_email', true);
        $featured = get_post_meta($post->ID, '_job_featured', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="application_method"><?php _e('Application Method', 'recruitment-manager'); ?></label></th>
                <td>
                    <select id="application_method" name="_application_method">
                        <option value="internal" <?php selected($application_method, 'internal'); ?>>
                            <?php _e('Internal Form', 'recruitment-manager'); ?>
                        </option>
                        <option value="external" <?php selected($application_method, 'external'); ?>>
                            <?php _e('External Website', 'recruitment-manager'); ?>
                        </option>
                        <option value="email" <?php selected($application_method, 'email'); ?>>
                            <?php _e('Email Application', 'recruitment-manager'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr id="form_shortcode_row" style="<?php echo ($application_method !== 'internal') ? 'display:none;' : ''; ?>">
                <th><label for="application_form_shortcode"><?php _e('Form Shortcode', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="text" id="application_form_shortcode" name="_application_form_shortcode" 
                           value="<?php echo esc_attr($form_shortcode); ?>" class="regular-text" 
                           placeholder="[gravityform id=&quot;1&quot; title=&quot;false&quot;]" />
                    <p class="description"><?php _e('Enter the shortcode for your form (Gravity Forms, Contact Form 7, etc.)', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr id="external_url_row" style="<?php echo ($application_method !== 'external') ? 'display:none;' : ''; ?>">
                <th><label for="external_application_url"><?php _e('External URL', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="url" id="external_application_url" name="_external_application_url" 
                           value="<?php echo esc_attr($external_url); ?>" class="regular-text" 
                           placeholder="https://example.com/apply" />
                    <p class="description"><?php _e('URL to external application platform', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr id="contact_email_row" style="<?php echo ($application_method !== 'email') ? 'display:none;' : ''; ?>">
                <th><label for="application_contact_email"><?php _e('Contact Email', 'recruitment-manager'); ?></label></th>
                <td>
                    <input type="email" id="application_contact_email" name="_application_contact_email" 
                           value="<?php echo esc_attr($contact_email); ?>" class="regular-text" 
                           placeholder="hr@company.com" />
                    <p class="description"><?php _e('Email address for job applications', 'recruitment-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="job_featured"><?php _e('Featured Job', 'recruitment-manager'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="job_featured" name="_job_featured" value="1" 
                               <?php checked($featured, 1); ?> />
                        <?php _e('Mark as featured job', 'recruitment-manager'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#application_method').change(function() {
                var method = $(this).val();
                $('#form_shortcode_row').toggle(method === 'internal');
                $('#external_url_row').toggle(method === 'external');
                $('#contact_email_row').toggle(method === 'email');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
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
            '_job_type',
            '_job_salary',
            '_job_hours',
            '_job_department',
            '_job_qualifications',
            '_job_experience',
            '_job_skills',
            '_application_method',
            '_application_form_shortcode',
            '_external_application_url',
            '_application_contact_email'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, $field, $value);
                
                // Extract numeric value for salary filtering
                if ($field === '_job_salary' && class_exists('BB_Job_Search')) {
                    BB_Job_Search::extract_salary_numeric($post_id, $value);
                }
            }
        }
        
        // Handle checkbox fields
        $featured = isset($_POST['_job_featured']) ? 1 : 0;
        update_post_meta($post_id, '_job_featured', $featured);
    }
    
    /**
     * Set custom columns for job vacancy list
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            // Insert custom columns after title
            if ($key === 'title') {
                $new_columns['job_type'] = __('Type', 'recruitment-manager');
                $new_columns['job_location'] = __('Location', 'recruitment-manager');
                $new_columns['closing_date'] = __('Closing Date', 'recruitment-manager');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'job_type':
                $type = get_post_meta($post_id, '_job_type', true);
                echo $type ? esc_html(ucwords(str_replace('-', ' ', $type))) : '—';
                break;
                
            case 'job_location':
                $locations = get_the_terms($post_id, 'job_location');
                if ($locations && !is_wp_error($locations)) {
                    $location_names = array_map(function($term) { return $term->name; }, $locations);
                    echo esc_html(implode(', ', $location_names));
                } else {
                    echo '—';
                }
                break;
                
            case 'closing_date':
                $closing_date = get_post_meta($post_id, '_job_closing_date', true);
                if ($closing_date) {
                    $formatted_date = date('d/m/Y', strtotime($closing_date));
                    $is_expired = strtotime($closing_date) < time();
                    $class = $is_expired ? 'bb-expired-date' : '';
                    echo '<span class="' . esc_attr($class) . '">' . esc_html($formatted_date) . '</span>';
                    if ($is_expired) {
                        echo ' <span class="bb-expired-label">' . __('(Expired)', 'recruitment-manager') . '</span>';
                    }
                } else {
                    echo '—';
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
     * Add admin filters
     */
    public function add_admin_filters() {
        global $typenow;
        
        if ($typenow === 'job_vacancy') {
            $selected = isset($_GET['job_status']) ? $_GET['job_status'] : '';
            ?>
            <select name="job_status">
                <option value=""><?php _e('All Statuses', 'recruitment-manager'); ?></option>
                <option value="publish" <?php selected($selected, 'publish'); ?>><?php _e('Active', 'recruitment-manager'); ?></option>
                <option value="draft" <?php selected($selected, 'draft'); ?>><?php _e('Draft', 'recruitment-manager'); ?></option>
            </select>
            <?php
        }
    }
    
    /**
     * Filter jobs by status
     */
    public function filter_jobs($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'job_vacancy' && isset($_GET['job_status']) && $_GET['job_status'] !== '') {
            $query->query_vars['post_status'] = sanitize_text_field($_GET['job_status']);
        }
    }
    
    /**
     * Remove quick edit
     */
    public function remove_quick_edit($actions, $post) {
        if ($post->post_type === 'job_vacancy') {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }
    
    /**
     * Load custom templates
     */
    public function load_templates($template) {
        // Single job vacancy template
        if (is_singular('job_vacancy')) {
            $plugin_template = BB_RECRUITMENT_MANAGER_PLUGIN_DIR . 'templates/single-job_vacancy.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        // Job vacancy archive template
        if (is_post_type_archive('job_vacancy')) {
            $plugin_template = BB_RECRUITMENT_MANAGER_PLUGIN_DIR . 'templates/archive-job_vacancy.php';
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