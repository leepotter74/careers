<?php
/**
 * Job Search and Filtering Class
 * 
 * Handles advanced search and filtering functionality for job vacancies
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Job_Search {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('pre_get_posts', array($this, 'modify_job_query'));
        add_filter('posts_join', array($this, 'search_join'), 10, 2);
        add_filter('posts_where', array($this, 'search_where'), 10, 2);
        add_filter('posts_orderby', array($this, 'search_orderby'), 10, 2);
        add_filter('posts_groupby', array($this, 'search_groupby'), 10, 2);
        
        // Update existing jobs with salary numeric values
        add_action('init', array($this, 'maybe_update_salary_numeric'), 20);
    }
    
    /**
     * Modify main query for job archive pages
     */
    public function modify_job_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if (!is_post_type_archive('job_vacancy') && !is_tax(array('job_category', 'job_location'))) {
            return;
        }
        
        // Set post type
        $query->set('post_type', 'job_vacancy');
        
        // Handle taxonomy filters
        $tax_query = array();
        
        if (isset($_GET['job_category']) && !empty($_GET['job_category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_category',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['job_category'])
            );
        }
        
        if (isset($_GET['job_location']) && !empty($_GET['job_location'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_location',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['job_location'])
            );
        }
        
        if (!empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $query->set('tax_query', $tax_query);
        }
        
        // Handle meta query filters
        $meta_query = array();
        
        if (isset($_GET['job_type']) && !empty($_GET['job_type'])) {
            $meta_query[] = array(
                'key'     => '_job_type',
                'value'   => sanitize_text_field($_GET['job_type']),
                'compare' => '='
            );
        }
        
        if (isset($_GET['salary_range']) && !empty($_GET['salary_range'])) {
            $salary_range = sanitize_text_field($_GET['salary_range']);
            
            if (strpos($salary_range, '-') !== false && !in_array($salary_range, array('competitive', 'negotiable'))) {
                // Numeric range like "20000-30000"
                list($min, $max) = explode('-', $salary_range);
                $meta_query[] = array(
                    'key'     => '_job_salary_numeric',
                    'value'   => array(intval($min), intval($max)),
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN'
                );
            } else {
                // Text-based like "competitive" or "negotiable"
                $meta_query[] = array(
                    'key'     => '_job_salary',
                    'value'   => $salary_range,
                    'compare' => 'LIKE'
                );
            }
        }
        
        if (isset($_GET['featured']) && $_GET['featured'] === '1') {
            $meta_query[] = array(
                'key'     => '_job_featured',
                'value'   => '1',
                'compare' => '='
            );
        }
        
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $query->set('meta_query', $meta_query);
        }
        
        // Handle sorting
        $this->handle_sorting($query);
    }
    
    /**
     * Handle sorting options
     */
    private function handle_sorting($query) {
        if (!isset($_GET['orderby']) || empty($_GET['orderby'])) {
            return;
        }
        
        $orderby = sanitize_text_field($_GET['orderby']);
        
        switch ($orderby) {
            case 'date-old':
                $query->set('orderby', 'date');
                $query->set('order', 'ASC');
                break;
                
            case 'title':
                $query->set('orderby', 'title');
                $query->set('order', 'ASC');
                break;
                
            case 'title-desc':
                $query->set('orderby', 'title');
                $query->set('order', 'DESC');
                break;
                
            case 'closing':
                $query->set('meta_key', '_job_closing_date');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'ASC');
                break;
                
            case 'featured':
                $query->set('meta_key', '_job_featured');
                $query->set('orderby', 'meta_value date');
                $query->set('order', 'DESC');
                break;
                
            default:
                // Default: newest first
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
        }
    }
    
    /**
     * Join posts and postmeta tables for salary filtering
     */
    public function search_join($join, $query) {
        global $wpdb;
        
        if ($this->is_job_search_query($query)) {
            if (isset($_GET['salary_range']) && !empty($_GET['salary_range'])) {
                $salary_range = sanitize_text_field($_GET['salary_range']);
                
                if (strpos($salary_range, '-') !== false && !in_array($salary_range, array('competitive', 'negotiable'))) {
                    $join .= " LEFT JOIN $wpdb->postmeta AS salary_meta ON $wpdb->posts.ID = salary_meta.post_id AND salary_meta.meta_key = '_job_salary_numeric' ";
                }
            }
        }
        
        return $join;
    }
    
    /**
     * Modify WHERE clause for advanced filtering
     */
    public function search_where($where, $query) {
        global $wpdb;
        
        if (!$this->is_job_search_query($query)) {
            return $where;
        }
        
        // Enhanced search functionality
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_term = sanitize_text_field($_GET['s']);
            $search_term = '%' . $wpdb->esc_like($search_term) . '%';
            
            // Remove default search where clause
            $where = preg_replace('/AND \(\(\(.*?\)\)\)/s', '', $where);
            
            // Add custom search across title, content, and meta fields
            $where .= " AND (
                ($wpdb->posts.post_title LIKE '$search_term')
                OR ($wpdb->posts.post_content LIKE '$search_term') 
                OR ($wpdb->posts.post_excerpt LIKE '$search_term')
                OR EXISTS (
                    SELECT 1 FROM $wpdb->postmeta 
                    WHERE $wpdb->postmeta.post_id = $wpdb->posts.ID
                    AND (
                        ($wpdb->postmeta.meta_key = '_job_department' AND $wpdb->postmeta.meta_value LIKE '$search_term')
                        OR ($wpdb->postmeta.meta_key = '_job_qualifications' AND $wpdb->postmeta.meta_value LIKE '$search_term')
                        OR ($wpdb->postmeta.meta_key = '_job_experience' AND $wpdb->postmeta.meta_value LIKE '$search_term')
                        OR ($wpdb->postmeta.meta_key = '_job_skills' AND $wpdb->postmeta.meta_value LIKE '$search_term')
                    )
                )
                OR EXISTS (
                    SELECT 1 FROM $wpdb->term_relationships 
                    LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                    LEFT JOIN $wpdb->terms ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
                    WHERE $wpdb->term_relationships.object_id = $wpdb->posts.ID
                    AND $wpdb->term_taxonomy.taxonomy IN ('job_category', 'job_location')
                    AND $wpdb->terms.name LIKE '$search_term'
                )
            )";
        }
        
        return $where;
    }
    
    /**
     * Modify ORDER BY clause for custom sorting
     */
    public function search_orderby($orderby, $query) {
        if (!$this->is_job_search_query($query)) {
            return $orderby;
        }
        
        if (isset($_GET['orderby']) && $_GET['orderby'] === 'featured') {
            global $wpdb;
            
            // Order by featured first, then by date
            $orderby = "
                CASE WHEN salary_meta.meta_value = '1' THEN 0 ELSE 1 END ASC, 
                $wpdb->posts.post_date DESC
            ";
        }
        
        return $orderby;
    }
    
    /**
     * Add GROUP BY to avoid duplicate results
     */
    public function search_groupby($groupby, $query) {
        global $wpdb;
        
        if ($this->is_job_search_query($query)) {
            if (empty($groupby)) {
                $groupby = "$wpdb->posts.ID";
            }
        }
        
        return $groupby;
    }
    
    /**
     * Check if this is a job search query
     */
    private function is_job_search_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return false;
        }
        
        return is_post_type_archive('job_vacancy') || 
               is_tax(array('job_category', 'job_location')) ||
               (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'job_vacancy');
    }
    
    /**
     * Extract salary numbers for filtering
     * Called when saving job posts
     */
    public static function extract_salary_numeric($post_id, $salary_text) {
        if (empty($salary_text)) {
            delete_post_meta($post_id, '_job_salary_numeric');
            return;
        }
        
        $salary_lower = strtolower($salary_text);
        
        // Handle text-based salaries first
        if (strpos($salary_lower, 'competitive') !== false) {
            update_post_meta($post_id, '_job_salary_numeric', 999999);
            return;
        } elseif (strpos($salary_lower, 'negotiable') !== false) {
            update_post_meta($post_id, '_job_salary_numeric', 999998);
            return;
        }
        
        // Remove common currency symbols and text
        $clean_text = preg_replace('/[£$€¥₹]/', '', $salary_text);
        $clean_text = preg_replace('/\b(per|annum|year|annually|pa|p\/a|hour|hr|hourly|week|weekly|month|monthly)\b/i', '', $clean_text);
        
        // Extract all numbers (including those with commas, decimals, and K/k suffixes)
        preg_match_all('/(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+(?:\.\d{2})?)[Kk]?/', $clean_text, $matches);
        
        if (!empty($matches[0])) {
            $numbers = array();
            foreach ($matches[0] as $match) {
                $number_str = str_replace(',', '', $match);
                
                // Handle K/k suffix (thousands)
                if (preg_match('/(\d+(?:\.\d+)?)[Kk]$/', $number_str, $k_matches)) {
                    $numbers[] = intval(floatval($k_matches[1]) * 1000);
                } else {
                    $numbers[] = intval(floatval($number_str));
                }
            }
            
            if (!empty($numbers)) {
                // Use the highest number found (often the main salary figure)
                $primary_salary = max($numbers);
                
                // If it's a very small number, it might be hourly - convert to rough annual
                if ($primary_salary < 100 && $primary_salary > 5) {
                    // Assume hourly rate, convert to approximate annual (37.5 hours * 52 weeks)
                    $primary_salary = $primary_salary * 37.5 * 52;
                }
                
                update_post_meta($post_id, '_job_salary_numeric', $primary_salary);
                return;
            }
        }
        
        // No numbers found, delete the meta
        delete_post_meta($post_id, '_job_salary_numeric');
    }
    
    /**
     * Update existing jobs with salary numeric values (one-time migration)
     */
    public function maybe_update_salary_numeric() {
        // Check if we've already done this update
        if (get_option('bb_recruitment_salary_numeric_updated', false)) {
            return;
        }
        
        // Get all job vacancies that don't have numeric salary values
        $jobs = get_posts(array(
            'post_type' => 'job_vacancy',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_job_salary_numeric',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($jobs as $job) {
            $salary = get_post_meta($job->ID, '_job_salary', true);
            if (!empty($salary)) {
                self::extract_salary_numeric($job->ID, $salary);
            }
        }
        
        // Mark this update as completed
        update_option('bb_recruitment_salary_numeric_updated', true);
    }
}