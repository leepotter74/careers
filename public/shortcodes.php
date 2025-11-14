<?php
/**
 * Shortcode functionality for Recruitment Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Job listings shortcode
 */
function bb_recruitment_job_listings_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10,
        'category' => '',
        'location' => ''
    ), $atts);
    
    $args = array(
        'post_type' => 'job_vacancy',
        'post_status' => 'publish',
        'posts_per_page' => intval($atts['limit'])
    );
    
    if (!empty($atts['category'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'job_category',
            'field' => 'slug',
            'terms' => $atts['category']
        );
    }
    
    if (!empty($atts['location'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => $atts['location']
        );
    }
    
    $jobs = new WP_Query($args);
    
    if (!$jobs->have_posts()) {
        return '<p>' . __('No job vacancies available.', 'recruitment-manager') . '</p>';
    }
    
    ob_start();
    ?>
    <div class="recruitment-job-listings">
        <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
            <div class="job-listing-item">
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <?php if (has_excerpt()) : ?>
                    <div class="job-excerpt"><?php the_excerpt(); ?></div>
                <?php endif; ?>
                <div class="job-meta">
                    <?php 
                    $location = get_post_meta(get_the_ID(), '_job_location', true);
                    $job_type = get_post_meta(get_the_ID(), '_job_type', true);
                    ?>
                    <?php if ($location) : ?>
                        <span class="job-location"><?php _e('Location:', 'recruitment-manager'); ?> <?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                    <?php if ($job_type) : ?>
                        <span class="job-type"><?php _e('Type:', 'recruitment-manager'); ?> <?php echo esc_html(ucwords(str_replace('-', ' ', $job_type))); ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?php the_permalink(); ?>" class="job-apply-link"><?php _e('View Details', 'recruitment-manager'); ?></a>
            </div>
        <?php endwhile; ?>
    </div>
    
    <style>
    .recruitment-job-listings {
        margin: 20px 0;
    }
    .job-listing-item {
        margin-bottom: 30px;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
    }
    .job-listing-item h3 {
        margin-top: 0;
    }
    .job-listing-item h3 a {
        text-decoration: none;
        color: #2c3e50;
    }
    .job-listing-item h3 a:hover {
        color: #2271b1;
    }
    .job-excerpt {
        margin: 15px 0;
        line-height: 1.6;
    }
    .job-meta {
        margin: 15px 0;
        font-size: 14px;
        color: #666;
    }
    .job-meta span {
        margin-right: 20px;
        display: inline-block;
    }
    .job-apply-link {
        display: inline-block;
        background: #2271b1;
        color: white;
        padding: 8px 16px;
        text-decoration: none;
        border-radius: 4px;
        transition: background 0.3s ease;
    }
    .job-apply-link:hover {
        background: #1e5a96;
        color: white;
    }
    </style>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

add_shortcode('job_listings', 'bb_recruitment_job_listings_shortcode');