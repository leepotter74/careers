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
 * Displays jobs with the same professional layout as the /jobs archive page
 */
function bb_recruitment_job_listings_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10,
        'category' => '',
        'location' => '',
        'show_filters' => 'yes',
        'show_search' => 'yes',
        'show_sort' => 'yes'
    ), $atts);

    // Generate unique ID for this shortcode instance
    $shortcode_id = 'job-listings-' . uniqid();

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

    ob_start();
    ?>
    <div class="bb-recruitment-shortcode-wrapper" id="<?php echo esc_attr($shortcode_id); ?>">

        <?php if ($atts['show_filters'] === 'yes') : ?>
        <!-- Advanced Filters -->
        <div class="job-filters-wrapper">
            <div class="job-filters">

                <?php if ($atts['show_search'] === 'yes') : ?>
                <!-- Search Box -->
                <div class="filter-group filter-search">
                    <label for="job-search-input-<?php echo esc_attr($shortcode_id); ?>">Search Jobs:</label>
                    <div class="search-wrapper">
                        <input type="text" id="job-search-input-<?php echo esc_attr($shortcode_id); ?>"
                               class="job-search-input" placeholder="Enter job title, department, or keywords..." />
                        <button type="button" class="search-btn">Search</button>
                        <button type="button" class="clear-search-btn" title="Clear search">×</button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Category Filter -->
                <?php
                $categories = get_terms(array(
                    'taxonomy' => 'job_category',
                    'hide_empty' => true,
                ));
                ?>
                <?php if ($categories && !is_wp_error($categories)) : ?>
                <div class="filter-group">
                    <label for="job-category-select-<?php echo esc_attr($shortcode_id); ?>">Category:</label>
                    <select id="job-category-select-<?php echo esc_attr($shortcode_id); ?>" class="job-category-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->slug); ?>">
                                <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Location Filter -->
                <?php
                $locations = get_terms(array(
                    'taxonomy' => 'job_location',
                    'hide_empty' => true,
                ));
                ?>
                <?php if ($locations && !is_wp_error($locations)) : ?>
                <div class="filter-group">
                    <label for="job-location-select-<?php echo esc_attr($shortcode_id); ?>">Location:</label>
                    <select id="job-location-select-<?php echo esc_attr($shortcode_id); ?>" class="job-location-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location) : ?>
                            <option value="<?php echo esc_attr($location->slug); ?>">
                                <?php echo esc_html($location->name); ?> (<?php echo $location->count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Job Type Filter -->
                <div class="filter-group">
                    <label for="job-type-select-<?php echo esc_attr($shortcode_id); ?>">Job Type:</label>
                    <select id="job-type-select-<?php echo esc_attr($shortcode_id); ?>" class="job-type-select">
                        <option value="">All Types</option>
                        <option value="full-time">Full Time</option>
                        <option value="part-time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="temporary">Temporary</option>
                        <option value="internship">Internship</option>
                        <option value="volunteer">Volunteer</option>
                    </select>
                </div>

                <!-- Salary Range Filter -->
                <div class="filter-group">
                    <label for="salary-range-select-<?php echo esc_attr($shortcode_id); ?>">Salary Range:</label>
                    <select id="salary-range-select-<?php echo esc_attr($shortcode_id); ?>" class="salary-range-select">
                        <option value="">All Salaries</option>
                        <option value="0-20000">Up to £20,000</option>
                        <option value="20000-30000">£20,000 - £30,000</option>
                        <option value="30000-40000">£30,000 - £40,000</option>
                        <option value="40000-50000">£40,000 - £50,000</option>
                        <option value="50000-60000">£50,000 - £60,000</option>
                        <option value="60000-999999">£60,000+</option>
                        <option value="competitive">Competitive</option>
                        <option value="negotiable">Negotiable</option>
                    </select>
                </div>

                <!-- Featured Only Filter -->
                <div class="filter-group filter-checkbox">
                    <label>
                        <input type="checkbox" id="featured-only-<?php echo esc_attr($shortcode_id); ?>" class="featured-only"> Featured Jobs Only
                    </label>
                </div>

                <!-- Clear All Filters -->
                <div class="filter-group filter-actions">
                    <button type="button" class="btn-clear clear-filters-btn">Clear All Filters</button>
                    <span class="filter-count active-filters-count"></span>
                </div>

            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <span class="results-count">
                    <?php echo $jobs->found_posts . ' job' . ($jobs->found_posts !== 1 ? 's' : '') . ' found'; ?>
                </span>

                <?php if ($atts['show_sort'] === 'yes') : ?>
                <!-- Sort Options -->
                <div class="sort-options">
                    <label for="sort-select-<?php echo esc_attr($shortcode_id); ?>">Sort by:</label>
                    <select id="sort-select-<?php echo esc_attr($shortcode_id); ?>" class="sort-select">
                        <option value="date">Date Posted (Newest)</option>
                        <option value="date-old">Date Posted (Oldest)</option>
                        <option value="title">Job Title (A-Z)</option>
                        <option value="title-desc">Job Title (Z-A)</option>
                        <option value="closing">Closing Date (Nearest)</option>
                        <option value="featured">Featured First</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Job Listings -->
        <?php if ($jobs->have_posts()) : ?>
            <div class="job-listings">
                <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
                <div class="job-item" data-job-id="<?php echo get_the_ID(); ?>">
                    <h2 class="job-item-title">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h2>

                    <div class="job-item-meta">
                        <?php
                        // Get job meta data
                        $job_type = get_post_meta(get_the_ID(), '_job_type', true);
                        $salary = get_post_meta(get_the_ID(), '_job_salary', true);
                        $hours = get_post_meta(get_the_ID(), '_job_hours', true);
                        $department = get_post_meta(get_the_ID(), '_job_department', true);
                        $closing_date = get_post_meta(get_the_ID(), '_job_closing_date', true);
                        $featured = get_post_meta(get_the_ID(), '_job_featured', true);

                        // Get taxonomy terms
                        $job_locations = get_the_terms(get_the_ID(), 'job_location');
                        $job_categories = get_the_terms(get_the_ID(), 'job_category');
                        ?>

                        <?php if ($featured) : ?>
                            <span class="job-featured">Featured</span>
                        <?php endif; ?>

                        <?php if ($job_locations && !is_wp_error($job_locations)) : ?>
                            <span class="meta-item">
                                <i class="icon-location"></i>
                                <strong>Location:</strong>
                                <?php
                                $location_names = array_map(function($term) { return $term->name; }, $job_locations);
                                echo esc_html(implode(', ', $location_names));
                                ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($job_categories && !is_wp_error($job_categories)) : ?>
                            <span class="meta-item">
                                <i class="icon-category"></i>
                                <strong>Category:</strong>
                                <?php
                                $category_names = array_map(function($term) { return $term->name; }, $job_categories);
                                echo esc_html(implode(', ', $category_names));
                                ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($job_type) : ?>
                            <span class="meta-item job-type-<?php echo esc_attr($job_type); ?>">
                                <i class="icon-type"></i>
                                <strong>Type:</strong> <?php echo esc_html(ucwords(str_replace('-', ' ', $job_type))); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($salary) : ?>
                            <span class="meta-item">
                                <i class="icon-salary"></i>
                                <strong>Salary:</strong> <?php echo esc_html($salary); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($hours) : ?>
                            <span class="meta-item">
                                <i class="icon-hours"></i>
                                <strong>Hours:</strong> <?php echo esc_html($hours); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($department) : ?>
                            <span class="meta-item">
                                <i class="icon-department"></i>
                                <strong>Department:</strong> <?php echo esc_html($department); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($closing_date) : ?>
                            <span class="meta-item closing-date">
                                <i class="icon-calendar"></i>
                                <strong>Closing:</strong>
                                <?php
                                $formatted_date = date('j M Y', strtotime($closing_date));
                                $is_expired = strtotime($closing_date) < time();
                                $days_left = ceil((strtotime($closing_date) - time()) / (60 * 60 * 24));

                                if ($is_expired) {
                                    echo '<span class="expired">' . esc_html($formatted_date) . ' (Expired)</span>';
                                } else {
                                    echo '<span class="active">' . esc_html($formatted_date);
                                    if ($days_left <= 7) {
                                        echo ' <span class="urgent">(' . $days_left . ' day' . ($days_left !== 1 ? 's' : '') . ' left)</span>';
                                    }
                                    echo '</span>';
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (has_excerpt()) : ?>
                        <div class="job-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>

                    <div class="job-item-actions">
                        <a href="<?php the_permalink(); ?>" class="btn btn-secondary">
                            <i class="icon-view"></i>
                            View Details
                        </a>

                        <?php
                        $application_method = get_post_meta(get_the_ID(), '_application_method', true);
                        $form_shortcode = get_post_meta(get_the_ID(), '_application_form_shortcode', true);
                        $external_url = get_post_meta(get_the_ID(), '_external_application_url', true);
                        $contact_email = get_post_meta(get_the_ID(), '_application_contact_email', true);

                        $is_expired = $closing_date && strtotime($closing_date) < time();

                        if (!$is_expired) :
                            if ($application_method === 'external' && $external_url) : ?>
                                <a href="<?php echo esc_url($external_url); ?>" class="btn btn-primary" target="_blank">
                                    <i class="icon-external"></i>
                                    Apply Now
                                </a>
                            <?php elseif ($application_method === 'email' && $contact_email) : ?>
                                <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="btn btn-primary">
                                    <i class="icon-email"></i>
                                    Apply by Email
                                </a>
                            <?php elseif ($application_method === 'internal' && $form_shortcode) : ?>
                                <a href="<?php the_permalink(); ?>#apply-form" class="btn btn-primary">
                                    <i class="icon-apply"></i>
                                    Apply Online
                                </a>
                            <?php else : ?>
                                <a href="<?php the_permalink(); ?>" class="btn btn-primary">
                                    <i class="icon-view"></i>
                                    View & Apply
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="btn btn-disabled">
                                <i class="icon-closed"></i>
                                Applications Closed
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <div class="no-jobs">
                <div class="no-jobs-icon">🔍</div>
                <h2>No Jobs Found</h2>
                <p>Sorry, no job vacancies match your current search criteria.</p>
                <button type="button" class="btn btn-primary clear-all-filters">
                    Clear All Filters
                </button>
            </div>
        <?php endif; ?>
    </div>

    <style>
    /* Base Styles for Shortcode */
    .bb-recruitment-shortcode-wrapper {
        background: white;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        margin: 20px 0;
    }

    /* Enhanced Filters */
    .bb-recruitment-shortcode-wrapper .job-filters-wrapper {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .bb-recruitment-shortcode-wrapper .job-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .bb-recruitment-shortcode-wrapper .filter-group {
        display: flex;
        flex-direction: column;
    }

    .bb-recruitment-shortcode-wrapper .filter-group.filter-search {
        grid-column: 1 / -1;
    }

    .bb-recruitment-shortcode-wrapper .filter-group.filter-actions {
        justify-content: center;
        align-items: center;
    }

    .bb-recruitment-shortcode-wrapper .filter-group label {
        font-weight: 600;
        margin-bottom: 8px;
        color: #2c3e50;
        font-size: 14px;
    }

    .bb-recruitment-shortcode-wrapper .filter-group select,
    .bb-recruitment-shortcode-wrapper .filter-group input[type="text"] {
        padding: 12px 15px;
        border: 2px solid #e1e5e9;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        transition: all 0.3s ease;
    }

    .bb-recruitment-shortcode-wrapper .filter-group select:focus,
    .bb-recruitment-shortcode-wrapper .filter-group input[type="text"]:focus {
        border-color: #2271b1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
    }

    /* Search Wrapper */
    .bb-recruitment-shortcode-wrapper .search-wrapper {
        display: flex;
        position: relative;
    }

    .bb-recruitment-shortcode-wrapper .search-wrapper input {
        flex: 1;
        border-radius: 6px 0 0 6px;
        border-right: none;
    }

    .bb-recruitment-shortcode-wrapper .search-wrapper .search-btn {
        background: #2271b1;
        color: white;
        border: 2px solid #2271b1;
        padding: 12px 20px;
        border-radius: 0 6px 6px 0;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .bb-recruitment-shortcode-wrapper .search-wrapper .search-btn:hover {
        background: #1e5a96;
        border-color: #1e5a96;
    }

    .bb-recruitment-shortcode-wrapper .search-wrapper .clear-search-btn {
        position: absolute;
        right: 80px;
        top: 50%;
        transform: translateY(-50%);
        background: #dc3545;
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
        display: none;
    }

    .bb-recruitment-shortcode-wrapper .search-wrapper .clear-search-btn:hover {
        background: #c82333;
    }

    /* Checkbox Filter */
    .bb-recruitment-shortcode-wrapper .filter-checkbox label {
        flex-direction: row;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }

    .bb-recruitment-shortcode-wrapper .filter-checkbox input {
        margin-right: 8px;
        transform: scale(1.2);
    }

    /* Filter Actions */
    .bb-recruitment-shortcode-wrapper .btn-clear {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .bb-recruitment-shortcode-wrapper .btn-clear:hover {
        background: #545b62;
    }

    .bb-recruitment-shortcode-wrapper .filter-count {
        font-size: 12px;
        color: #6c757d;
        margin-top: 5px;
    }

    /* Results Summary */
    .bb-recruitment-shortcode-wrapper .results-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 15px 0;
        border-top: 1px solid #e1e5e9;
    }

    .bb-recruitment-shortcode-wrapper .results-count {
        font-weight: 600;
        color: #2c3e50;
    }

    .bb-recruitment-shortcode-wrapper .sort-options {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bb-recruitment-shortcode-wrapper .sort-options label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
    }

    .bb-recruitment-shortcode-wrapper .sort-options select {
        padding: 8px 12px;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        background: white;
    }

    /* Enhanced Job Listings */
    .bb-recruitment-shortcode-wrapper .job-listings {
        display: grid;
        gap: 25px;
    }

    .bb-recruitment-shortcode-wrapper .job-item {
        background: white;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        padding: 25px;
        transition: all 0.3s ease;
        position: relative;
    }

    .bb-recruitment-shortcode-wrapper .job-item:hover {
        border-color: #2271b1;
        box-shadow: 0 8px 25px rgba(34, 113, 177, 0.15);
        transform: translateY(-2px);
    }

    .bb-recruitment-shortcode-wrapper .job-item-title {
        margin: 0 0 15px 0;
        font-size: 22px;
    }

    .bb-recruitment-shortcode-wrapper .job-item-title a {
        text-decoration: none;
        color: #2c3e50;
        transition: color 0.3s ease;
    }

    .bb-recruitment-shortcode-wrapper .job-item-title a:hover {
        color: #2271b1;
    }

    /* Enhanced Meta Display */
    .bb-recruitment-shortcode-wrapper .job-item-meta {
        margin: 15px 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        font-size: 14px;
    }

    .bb-recruitment-shortcode-wrapper .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 20px;
        border: 1px solid #e1e5e9;
    }

    .bb-recruitment-shortcode-wrapper .meta-item strong {
        color: #2c3e50;
    }

    /* Job Type Styling */
    .bb-recruitment-shortcode-wrapper .job-type-full-time { border-left: 4px solid #28a745; }
    .bb-recruitment-shortcode-wrapper .job-type-part-time { border-left: 4px solid #17a2b8; }
    .bb-recruitment-shortcode-wrapper .job-type-contract { border-left: 4px solid #ffc107; }
    .bb-recruitment-shortcode-wrapper .job-type-temporary { border-left: 4px solid #fd7e14; }
    .bb-recruitment-shortcode-wrapper .job-type-internship { border-left: 4px solid #6f42c1; }
    .bb-recruitment-shortcode-wrapper .job-type-volunteer { border-left: 4px solid #20c997; }

    /* Featured Badge */
    .bb-recruitment-shortcode-wrapper .job-featured {
        position: absolute;
        top: -8px;
        right: 20px;
        background: linear-gradient(45deg, #f39c12, #e67e22);
        color: white;
        padding: 6px 15px;
        border-radius: 15px;
        font-size: 12px !important;
        font-weight: bold;
        text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(243, 156, 18, 0.3);
    }

    /* Closing Date Styling */
    .bb-recruitment-shortcode-wrapper .expired {
        color: #dc3545;
        font-weight: bold;
    }

    .bb-recruitment-shortcode-wrapper .urgent {
        color: #fd7e14;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    .bb-recruitment-shortcode-wrapper .active {
        color: #28a745;
    }

    /* Job Excerpt */
    .bb-recruitment-shortcode-wrapper .job-excerpt {
        margin: 15px 0;
        line-height: 1.6;
        color: #555;
    }

    /* Enhanced Buttons */
    .bb-recruitment-shortcode-wrapper .job-item-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .bb-recruitment-shortcode-wrapper .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        text-decoration: none;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
    }

    .bb-recruitment-shortcode-wrapper .btn-primary {
        background: linear-gradient(135deg, #2271b1, #1e5a96);
        color: white;
    }

    .bb-recruitment-shortcode-wrapper .btn-primary:hover {
        background: linear-gradient(135deg, #1e5a96, #1a4f7a);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
    }

    .bb-recruitment-shortcode-wrapper .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .bb-recruitment-shortcode-wrapper .btn-secondary:hover {
        background: #545b62;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    }

    .bb-recruitment-shortcode-wrapper .btn-disabled {
        background: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
    }

    /* No Jobs State */
    .bb-recruitment-shortcode-wrapper .no-jobs {
        text-align: center;
        padding: 80px 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 2px dashed #dee2e6;
    }

    .bb-recruitment-shortcode-wrapper .no-jobs-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.7;
    }

    .bb-recruitment-shortcode-wrapper .no-jobs h2 {
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 28px;
    }

    .bb-recruitment-shortcode-wrapper .no-jobs p {
        color: #6c757d;
        margin-bottom: 25px;
        font-size: 16px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .bb-recruitment-shortcode-wrapper {
            padding: 20px;
        }

        .bb-recruitment-shortcode-wrapper .job-filters {
            grid-template-columns: 1fr;
        }

        .bb-recruitment-shortcode-wrapper .filter-group.filter-search {
            grid-column: 1;
        }

        .bb-recruitment-shortcode-wrapper .search-wrapper {
            flex-direction: column;
        }

        .bb-recruitment-shortcode-wrapper .search-wrapper input {
            border-radius: 6px 6px 0 0;
            border-right: 2px solid #e1e5e9;
        }

        .bb-recruitment-shortcode-wrapper .search-wrapper .search-btn {
            border-radius: 0 0 6px 6px;
            border-top: none;
        }

        .bb-recruitment-shortcode-wrapper .search-wrapper .clear-search-btn {
            right: 10px;
            top: 15px;
        }

        .bb-recruitment-shortcode-wrapper .results-summary {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .bb-recruitment-shortcode-wrapper .job-item-meta {
            grid-template-columns: 1fr;
        }

        .bb-recruitment-shortcode-wrapper .job-item-actions {
            flex-direction: column;
        }

        .bb-recruitment-shortcode-wrapper .btn {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .bb-recruitment-shortcode-wrapper {
            padding: 15px;
            margin: 10px 0;
        }

        .bb-recruitment-shortcode-wrapper .job-filters-wrapper {
            padding: 20px 15px;
        }
    }
    </style>

    <script>
    (function() {
        // Scoped to this shortcode instance
        var containerId = '<?php echo esc_js($shortcode_id); ?>';
        var container = document.getElementById(containerId);

        if (!container) return;

        // Get all filter elements within this shortcode instance
        var searchInput = container.querySelector('.job-search-input');
        var categorySelect = container.querySelector('.job-category-select');
        var locationSelect = container.querySelector('.job-location-select');
        var jobTypeSelect = container.querySelector('.job-type-select');
        var salarySelect = container.querySelector('.salary-range-select');
        var featuredCheckbox = container.querySelector('.featured-only');
        var sortSelect = container.querySelector('.sort-select');

        var searchBtn = container.querySelector('.search-btn');
        var clearSearchBtn = container.querySelector('.clear-search-btn');
        var clearFiltersBtn = container.querySelector('.clear-filters-btn');
        var clearAllFiltersBtn = container.querySelector('.clear-all-filters');

        var activeFiltersCount = container.querySelector('.active-filters-count');
        var resultsCount = container.querySelector('.results-count');
        var jobListings = container.querySelector('.job-listings');
        var jobItems = container.querySelectorAll('.job-item');

        // Show/hide jobs based on filters
        function applyFilters() {
            var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            var categoryTerm = categorySelect ? categorySelect.value : '';
            var locationTerm = locationSelect ? locationSelect.value : '';
            var jobTypeTerm = jobTypeSelect ? jobTypeSelect.value : '';
            var salaryTerm = salarySelect ? salarySelect.value : '';
            var featuredOnly = featuredCheckbox ? featuredCheckbox.checked : false;

            var visibleCount = 0;

            jobItems.forEach(function(item) {
                var visible = true;

                // Search filter
                if (searchTerm && visible) {
                    var title = item.querySelector('.job-item-title') ? item.querySelector('.job-item-title').textContent.toLowerCase() : '';
                    var excerpt = item.querySelector('.job-excerpt') ? item.querySelector('.job-excerpt').textContent.toLowerCase() : '';
                    visible = title.includes(searchTerm) || excerpt.includes(searchTerm);
                }

                // Job type filter
                if (jobTypeTerm && visible) {
                    var hasType = item.querySelector('.job-type-' + jobTypeTerm);
                    visible = !!hasType;
                }

                // Featured filter
                if (featuredOnly && visible) {
                    var isFeatured = item.querySelector('.job-featured');
                    visible = !!isFeatured;
                }

                // Category and location would require AJAX in a real implementation
                // For now, we'll just show all that match other criteria

                if (visible) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Update results count
            if (resultsCount) {
                resultsCount.textContent = visibleCount + ' job' + (visibleCount !== 1 ? 's' : '') + ' found';
            }

            // Show/hide no jobs message
            var noJobsDiv = container.querySelector('.no-jobs');
            if (visibleCount === 0) {
                if (jobListings) jobListings.style.display = 'none';
                if (noJobsDiv) noJobsDiv.style.display = 'block';
            } else {
                if (jobListings) jobListings.style.display = 'grid';
                if (noJobsDiv) noJobsDiv.style.display = 'none';
            }

            updateActiveFiltersCount();
        }

        // Sort jobs
        function sortJobs() {
            if (!sortSelect || !jobListings) return;

            var sortValue = sortSelect.value;
            var itemsArray = Array.from(jobItems);

            itemsArray.sort(function(a, b) {
                switch(sortValue) {
                    case 'title':
                        var titleA = a.querySelector('.job-item-title').textContent;
                        var titleB = b.querySelector('.job-item-title').textContent;
                        return titleA.localeCompare(titleB);

                    case 'title-desc':
                        var titleA = a.querySelector('.job-item-title').textContent;
                        var titleB = b.querySelector('.job-item-title').textContent;
                        return titleB.localeCompare(titleA);

                    case 'featured':
                        var featuredA = a.querySelector('.job-featured') ? 1 : 0;
                        var featuredB = b.querySelector('.job-featured') ? 1 : 0;
                        return featuredB - featuredA;

                    default:
                        return 0;
                }
            });

            itemsArray.forEach(function(item) {
                jobListings.appendChild(item);
            });
        }

        // Clear search
        function clearSearch() {
            if (searchInput) {
                searchInput.value = '';
            }
            if (clearSearchBtn) {
                clearSearchBtn.style.display = 'none';
            }
            applyFilters();
        }

        // Clear all filters
        function clearAllFilters() {
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = '';
            if (locationSelect) locationSelect.value = '';
            if (jobTypeSelect) jobTypeSelect.value = '';
            if (salarySelect) salarySelect.value = '';
            if (featuredCheckbox) featuredCheckbox.checked = false;
            if (sortSelect) sortSelect.value = 'date';

            if (clearSearchBtn) {
                clearSearchBtn.style.display = 'none';
            }
            applyFilters();
        }

        // Update active filters count
        function updateActiveFiltersCount() {
            var count = 0;

            if (searchInput && searchInput.value.trim()) count++;
            if (categorySelect && categorySelect.value) count++;
            if (locationSelect && locationSelect.value) count++;
            if (jobTypeSelect && jobTypeSelect.value) count++;
            if (salarySelect && salarySelect.value) count++;
            if (featuredCheckbox && featuredCheckbox.checked) count++;

            if (activeFiltersCount) {
                if (count > 0) {
                    activeFiltersCount.textContent = count + ' filter' + (count !== 1 ? 's' : '') + ' active';
                    activeFiltersCount.style.display = 'block';
                } else {
                    activeFiltersCount.style.display = 'none';
                }
            }

            // Show/hide clear search button
            if (clearSearchBtn && searchInput) {
                clearSearchBtn.style.display = searchInput.value.trim() ? 'block' : 'none';
            }
        }

        // Event listeners
        if (searchBtn) {
            searchBtn.addEventListener('click', applyFilters);
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', clearSearch);
        }

        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', clearAllFilters);
        }

        if (clearAllFiltersBtn) {
            clearAllFiltersBtn.addEventListener('click', clearAllFilters);
        }

        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });

            searchInput.addEventListener('input', function() {
                if (clearSearchBtn) {
                    clearSearchBtn.style.display = this.value.trim() ? 'block' : 'none';
                }
            });
        }

        // Filter change listeners
        [categorySelect, locationSelect, jobTypeSelect, salarySelect].forEach(function(element) {
            if (element) {
                element.addEventListener('change', applyFilters);
            }
        });

        if (featuredCheckbox) {
            featuredCheckbox.addEventListener('change', applyFilters);
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', sortJobs);
        }

        // Update count when filters change
        [searchInput, categorySelect, locationSelect, jobTypeSelect, salarySelect, featuredCheckbox].forEach(function(element) {
            if (element) {
                element.addEventListener('change', updateActiveFiltersCount);
                element.addEventListener('input', updateActiveFiltersCount);
            }
        });

        // Initial update
        updateActiveFiltersCount();
    })();
    </script>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

add_shortcode('job_listings', 'bb_recruitment_job_listings_shortcode');