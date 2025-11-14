<?php
/**
 * Social Sharing Class
 * 
 * Handles social media sharing functionality for job posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Social_Sharing {
    
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
        // Add sharing buttons to job posts
        add_filter('the_content', array($this, 'add_sharing_buttons_to_content'));
        
        // Add Open Graph meta tags
        add_action('wp_head', array($this, 'add_og_meta_tags'));
        
        // Add Twitter Card meta tags  
        add_action('wp_head', array($this, 'add_twitter_card_tags'));
        
        // Shortcode for sharing buttons
        add_shortcode('job_share_buttons', array($this, 'render_sharing_buttons_shortcode'));
        
        // AJAX handler for share tracking
        add_action('wp_ajax_bb_track_share', array($this, 'track_share_ajax'));
        add_action('wp_ajax_nopriv_bb_track_share', array($this, 'track_share_ajax'));
    }
    
    /**
     * Add sharing buttons to job post content
     */
    public function add_sharing_buttons_to_content($content) {
        if (!is_singular('job_vacancy')) {
            return $content;
        }
        
        // Check if sharing is enabled
        if (!get_option('bb_recruitment_social_sharing', true)) {
            return $content;
        }
        
        $sharing_buttons = $this->render_sharing_buttons();
        
        return $content . $sharing_buttons;
    }
    
    /**
     * Render sharing buttons
     */
    public function render_sharing_buttons($job_id = null) {
        if (!$job_id) {
            $job_id = get_the_ID();
        }
        
        if (!$job_id || get_post_type($job_id) !== 'job_vacancy') {
            return '';
        }
        
        $job_title = get_the_title($job_id);
        $job_url = get_permalink($job_id);
        $job_excerpt = get_the_excerpt($job_id);
        
        // Prepare sharing data
        $share_data = array(
            'url' => urlencode($job_url),
            'title' => urlencode($job_title),
            'text' => urlencode($job_excerpt),
            'hashtags' => urlencode('jobs,career,hiring')
        );
        
        ob_start();
        ?>
        <div class="bb-social-sharing" data-job-id="<?php echo esc_attr($job_id); ?>">
            <h4 class="bb-share-title"><?php _e('Share this job opportunity', 'big-bundle'); ?></h4>
            <div class="bb-share-buttons">
                
                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_data['url']; ?>" 
                   class="bb-share-btn bb-share-facebook" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="facebook">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span><?php _e('Facebook', 'big-bundle'); ?></span>
                </a>
                
                <!-- LinkedIn -->
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share_data['url']; ?>" 
                   class="bb-share-btn bb-share-linkedin" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="linkedin">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                    <span><?php _e('LinkedIn', 'big-bundle'); ?></span>
                </a>
                
                <!-- Twitter/X -->
                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_data['url']; ?>&text=<?php echo $share_data['title']; ?>&hashtags=<?php echo $share_data['hashtags']; ?>" 
                   class="bb-share-btn bb-share-twitter" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="twitter">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                    <span><?php _e('Twitter', 'big-bundle'); ?></span>
                </a>
                
                <!-- WhatsApp -->
                <a href="https://wa.me/?text=<?php echo urlencode($job_title . ' - ' . $job_url); ?>" 
                   class="bb-share-btn bb-share-whatsapp" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   data-platform="whatsapp">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                    </svg>
                    <span><?php _e('WhatsApp', 'big-bundle'); ?></span>
                </a>
                
                <!-- Email -->
                <a href="mailto:?subject=<?php echo urlencode('Job Opportunity: ' . $job_title); ?>&body=<?php echo urlencode('I thought you might be interested in this job opportunity: ' . $job_title . "\n\n" . $job_url); ?>" 
                   class="bb-share-btn bb-share-email"
                   data-platform="email">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-.904.732-1.636 1.636-1.636h3.819l6.545 4.91 6.545-4.91h3.819A1.636 1.636 0 0 1 24 5.457z"/>
                    </svg>
                    <span><?php _e('Email', 'big-bundle'); ?></span>
                </a>
                
                <!-- Copy Link -->
                <button type="button" class="bb-share-btn bb-share-copy" data-platform="copy" data-url="<?php echo esc_attr($job_url); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                    <span><?php _e('Copy Link', 'big-bundle'); ?></span>
                </button>
                
            </div>
            
            <div class="bb-share-stats">
                <?php $share_count = $this->get_share_count($job_id); ?>
                <?php if ($share_count > 0): ?>
                    <span class="bb-share-count"><?php printf(_n('%d share', '%d shares', $share_count, 'big-bundle'), $share_count); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode for sharing buttons
     */
    public function render_sharing_buttons_shortcode($atts) {
        $atts = shortcode_atts(array(
            'job_id' => get_the_ID()
        ), $atts);
        
        return $this->render_sharing_buttons(intval($atts['job_id']));
    }
    
    /**
     * Add Open Graph meta tags
     */
    public function add_og_meta_tags() {
        if (!is_singular('job_vacancy')) {
            return;
        }
        
        global $post;
        
        $job_title = get_the_title();
        $job_description = get_the_excerpt();
        $job_url = get_permalink();
        $job_image = get_the_post_thumbnail_url($post->ID, 'large');
        $site_name = get_bloginfo('name');
        
        // Get job meta
        $job_type = get_post_meta($post->ID, '_job_type', true);
        $location = get_post_meta($post->ID, '_job_location', true);
        $salary = get_post_meta($post->ID, '_job_salary', true);
        
        // Enhanced description with job details
        $enhanced_description = $job_description;
        if ($job_type || $location || $salary) {
            $details = array();
            if ($job_type) $details[] = ucfirst(str_replace('-', ' ', $job_type));
            if ($location) $details[] = $location;
            if ($salary) $details[] = $salary;
            $enhanced_description .= ' | ' . implode(' | ', $details);
        }
        
        ?>
        <!-- Open Graph Meta Tags -->
        <meta property="og:type" content="article" />
        <meta property="og:title" content="<?php echo esc_attr($job_title); ?>" />
        <meta property="og:description" content="<?php echo esc_attr($enhanced_description); ?>" />
        <meta property="og:url" content="<?php echo esc_url($job_url); ?>" />
        <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>" />
        <?php if ($job_image): ?>
        <meta property="og:image" content="<?php echo esc_url($job_image); ?>" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <?php endif; ?>
        
        <!-- Job specific Open Graph -->
        <meta property="og:job_title" content="<?php echo esc_attr($job_title); ?>" />
        <?php if ($location): ?>
        <meta property="og:job_location" content="<?php echo esc_attr($location); ?>" />
        <?php endif; ?>
        <?php if ($job_type): ?>
        <meta property="og:job_type" content="<?php echo esc_attr($job_type); ?>" />
        <?php endif; ?>
        <?php
    }
    
    /**
     * Add Twitter Card meta tags
     */
    public function add_twitter_card_tags() {
        if (!is_singular('job_vacancy')) {
            return;
        }
        
        global $post;
        
        $job_title = get_the_title();
        $job_description = get_the_excerpt();
        $job_image = get_the_post_thumbnail_url($post->ID, 'large');
        $site_handle = get_option('bb_recruitment_twitter_handle', '');
        
        ?>
        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="<?php echo $job_image ? 'summary_large_image' : 'summary'; ?>" />
        <meta name="twitter:title" content="<?php echo esc_attr($job_title); ?>" />
        <meta name="twitter:description" content="<?php echo esc_attr($job_description); ?>" />
        <?php if ($job_image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($job_image); ?>" />
        <?php endif; ?>
        <?php if ($site_handle): ?>
        <meta name="twitter:site" content="<?php echo esc_attr($site_handle); ?>" />
        <?php endif; ?>
        <?php
    }
    
    /**
     * Track share action
     */
    public function track_share_ajax() {
        check_ajax_referer('bb_recruitment_public_nonce', 'nonce');
        
        $job_id = intval($_POST['job_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (!$job_id || !$platform) {
            wp_send_json_error(__('Invalid data.', 'big-bundle'));
        }
        
        // Increment share count
        $current_shares = get_post_meta($job_id, '_bb_share_count', true);
        $platform_shares = get_post_meta($job_id, '_bb_share_' . $platform, true);
        
        update_post_meta($job_id, '_bb_share_count', intval($current_shares) + 1);
        update_post_meta($job_id, '_bb_share_' . $platform, intval($platform_shares) + 1);
        
        // Log the share (for analytics)
        $this->log_share_event($job_id, $platform);
        
        wp_send_json_success(array(
            'message' => __('Share tracked successfully.', 'big-bundle'),
            'total_shares' => intval($current_shares) + 1
        ));
    }
    
    /**
     * Get share count for a job
     */
    public function get_share_count($job_id) {
        return intval(get_post_meta($job_id, '_bb_share_count', true));
    }
    
    /**
     * Get share count by platform
     */
    public function get_platform_share_count($job_id, $platform) {
        return intval(get_post_meta($job_id, '_bb_share_' . $platform, true));
    }
    
    /**
     * Get all sharing stats for a job
     */
    public function get_sharing_stats($job_id) {
        $platforms = array('facebook', 'linkedin', 'twitter', 'whatsapp', 'email', 'copy');
        $stats = array();
        
        foreach ($platforms as $platform) {
            $stats[$platform] = $this->get_platform_share_count($job_id, $platform);
        }
        
        $stats['total'] = $this->get_share_count($job_id);
        
        return $stats;
    }
    
    /**
     * Get top shared jobs
     */
    public function get_top_shared_jobs($limit = 10) {
        $args = array(
            'post_type' => 'job_vacancy',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_bb_share_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_bb_share_count',
                    'value' => 0,
                    'compare' => '>'
                )
            )
        );
        
        return new WP_Query($args);
    }
    
    /**
     * Log share event for analytics
     */
    private function log_share_event($job_id, $platform) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'recruitment_share_logs';
        
        // Create table if it doesn't exist
        $this->maybe_create_share_log_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'job_id' => $job_id,
                'platform' => $platform,
                'user_id' => get_current_user_id() ?: null,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'share_date' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Maybe create share log table
     */
    private function maybe_create_share_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'recruitment_share_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                job_id int(11) NOT NULL,
                platform varchar(50) NOT NULL,
                user_id int(11) NULL,
                ip_address varchar(45),
                user_agent text,
                share_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY job_id (job_id),
                KEY platform (platform),
                KEY share_date (share_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_fields = array(
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ips = explode(',', $_SERVER[$field]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
}