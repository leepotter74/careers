<?php
/**
 * Archive Job Vacancy Template
 * 
 * Enhanced template with comprehensive search and filtering capabilities
 * Includes salary bands, locations, categories, job types, and more
 */

get_header(); ?>

<div class="container">
    <div class="content">
        
        <h1>Job Vacancies</h1>
        
        <!-- Advanced Filters -->
        <div class="job-filters-wrapper">
            <div class="job-filters">
                
                <!-- Search Box -->
                <div class="filter-group filter-search">
                    <label for="job-search-input">Search Jobs:</label>
                    <div class="search-wrapper">
                        <input type="text" id="job-search-input" placeholder="Enter job title, department, or keywords..." />
                        <button type="button" id="search-btn">Search</button>
                        <button type="button" id="clear-search-btn" title="Clear search">×</button>
                    </div>
                </div>
                
                <!-- Category Filter -->
                <?php 
                $categories = get_terms(array(
                    'taxonomy' => 'job_category',
                    'hide_empty' => true,
                ));
                ?>
                <?php if ($categories && !is_wp_error($categories)) : ?>
                <div class="filter-group">
                    <label for="job-category-select">Category:</label>
                    <select id="job-category-select">
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
                    <label for="job-location-select">Location:</label>
                    <select id="job-location-select">
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
                    <label for="job-type-select">Job Type:</label>
                    <select id="job-type-select">
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
                    <label for="salary-range-select">Salary Range:</label>
                    <select id="salary-range-select">
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
                        <input type="checkbox" id="featured-only"> Featured Jobs Only
                    </label>
                </div>
                
                <!-- Clear All Filters -->
                <div class="filter-group filter-actions">
                    <button type="button" id="clear-filters-btn" class="btn-clear">Clear All Filters</button>
                    <span id="active-filters-count" class="filter-count"></span>
                </div>
                
            </div>
            
            <!-- Results Summary -->
            <div class="results-summary">
                <span id="results-count">
                    <?php 
                    global $wp_query;
                    $found_posts = $wp_query->found_posts;
                    echo $found_posts . ' job' . ($found_posts !== 1 ? 's' : '') . ' found';
                    ?>
                </span>
                
                <!-- Sort Options -->
                <div class="sort-options">
                    <label for="sort-select">Sort by:</label>
                    <select id="sort-select">
                        <option value="date">Date Posted (Newest)</option>
                        <option value="date-old">Date Posted (Oldest)</option>
                        <option value="title">Job Title (A-Z)</option>
                        <option value="title-desc">Job Title (Z-A)</option>
                        <option value="closing">Closing Date (Nearest)</option>
                        <option value="featured">Featured First</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Job Listings -->
        <?php if (have_posts()) : ?>
            <div class="job-listings" id="job-listings">
                <?php while (have_posts()) : the_post(); ?>
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
                        $locations = get_the_terms(get_the_ID(), 'job_location');
                        $categories = get_the_terms(get_the_ID(), 'job_category');
                        ?>
                        
                        <?php if ($featured) : ?>
                            <span class="job-featured">Featured</span>
                        <?php endif; ?>
                        
                        <?php if ($locations && !is_wp_error($locations)) : ?>
                            <span class="meta-item">
                                <i class="icon-location"></i>
                                <strong>Location:</strong> 
                                <?php 
                                $location_names = array_map(function($term) { return $term->name; }, $locations);
                                echo esc_html(implode(', ', $location_names)); 
                                ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($categories && !is_wp_error($categories)) : ?>
                            <span class="meta-item">
                                <i class="icon-category"></i>
                                <strong>Category:</strong> 
                                <?php 
                                $category_names = array_map(function($term) { return $term->name; }, $categories);
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
            
            <?php
            // Enhanced Pagination
            the_posts_pagination(array(
                'mid_size'  => 2,
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
                'before_page_number' => '<span class="screen-reader-text">Page </span>',
            ));
            ?>
            
        <?php else : ?>
            <div class="no-jobs">
                <div class="no-jobs-icon">🔍</div>
                <h2>No Jobs Found</h2>
                <p>Sorry, no job vacancies match your current search criteria.</p>
                <button type="button" id="clear-all-filters" class="btn btn-primary">
                    Clear All Filters
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Base Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.content {
    background: white;
    min-height: 400px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    margin: 20px 0;
}

/* Enhanced Filters */
.job-filters-wrapper {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.job-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group.filter-search {
    grid-column: 1 / -1;
}

.filter-group.filter-actions {
    justify-content: center;
    align-items: center;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
    font-size: 14px;
}

.filter-group select,
.filter-group input[type="text"] {
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    transition: all 0.3s ease;
}

.filter-group select:focus,
.filter-group input[type="text"]:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
}

/* Search Wrapper */
.search-wrapper {
    display: flex;
    position: relative;
}

.search-wrapper input {
    flex: 1;
    border-radius: 6px 0 0 6px;
    border-right: none;
}

.search-wrapper #search-btn {
    background: #2271b1;
    color: white;
    border: 2px solid #2271b1;
    padding: 12px 20px;
    border-radius: 0 6px 6px 0;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.search-wrapper #search-btn:hover {
    background: #1e5a96;
    border-color: #1e5a96;
}

.search-wrapper #clear-search-btn {
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

.search-wrapper #clear-search-btn:hover {
    background: #c82333;
}

/* Checkbox Filter */
.filter-checkbox label {
    flex-direction: row;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.filter-checkbox input {
    margin-right: 8px;
    transform: scale(1.2);
}

/* Filter Actions */
.btn-clear {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-clear:hover {
    background: #545b62;
}

.filter-count {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Results Summary */
.results-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px 0;
    border-top: 1px solid #e1e5e9;
}

#results-count {
    font-weight: 600;
    color: #2c3e50;
}

.sort-options {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sort-options label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.sort-options select {
    padding: 8px 12px;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    background: white;
}

/* Enhanced Job Listings */
.job-listings {
    display: grid;
    gap: 25px;
}

.job-item {
    background: white;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    padding: 25px;
    transition: all 0.3s ease;
    position: relative;
}

.job-item:hover {
    border-color: #2271b1;
    box-shadow: 0 8px 25px rgba(34, 113, 177, 0.15);
    transform: translateY(-2px);
}

.job-item-title {
    margin: 0 0 15px 0;
    font-size: 22px;
}

.job-item-title a {
    text-decoration: none;
    color: #2c3e50;
    transition: color 0.3s ease;
}

.job-item-title a:hover {
    color: #2271b1;
}

/* Enhanced Meta Display */
.job-item-meta {
    margin: 15px 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    font-size: 14px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 20px;
    border: 1px solid #e1e5e9;
}

.meta-item strong {
    color: #2c3e50;
}

/* Job Type Styling */
.job-type-full-time { border-left: 4px solid #28a745; }
.job-type-part-time { border-left: 4px solid #17a2b8; }
.job-type-contract { border-left: 4px solid #ffc107; }
.job-type-temporary { border-left: 4px solid #fd7e14; }
.job-type-internship { border-left: 4px solid #6f42c1; }
.job-type-volunteer { border-left: 4px solid #20c997; }

/* Featured Badge */
.job-featured {
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
.expired {
    color: #dc3545;
    font-weight: bold;
}

.urgent {
    color: #fd7e14;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.active {
    color: #28a745;
}

/* Job Excerpt */
.job-excerpt {
    margin: 15px 0;
    line-height: 1.6;
    color: #555;
}

/* Enhanced Buttons */
.job-item-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
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

.btn-primary {
    background: linear-gradient(135deg, #2271b1, #1e5a96);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1e5a96, #1a4f7a);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.btn-disabled {
    background: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

/* No Jobs State */
.no-jobs {
    text-align: center;
    padding: 80px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border: 2px dashed #dee2e6;
}

.no-jobs-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.7;
}

.no-jobs h2 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 28px;
}

.no-jobs p {
    color: #6c757d;
    margin-bottom: 25px;
    font-size: 16px;
}

/* Pagination */
.pagination {
    margin-top: 40px;
    text-align: center;
}

.page-numbers {
    display: inline-block;
    padding: 10px 16px;
    margin: 0 4px;
    text-decoration: none;
    background: white;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    color: #2c3e50;
    transition: all 0.3s ease;
}

.page-numbers:hover,
.page-numbers.current {
    background: #2271b1;
    border-color: #2271b1;
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .content {
        padding: 20px;
    }
    
    .job-filters {
        grid-template-columns: 1fr;
    }
    
    .filter-group.filter-search {
        grid-column: 1;
    }
    
    .search-wrapper {
        flex-direction: column;
    }
    
    .search-wrapper input {
        border-radius: 6px 6px 0 0;
        border-right: 2px solid #e1e5e9;
    }
    
    .search-wrapper #search-btn {
        border-radius: 0 0 6px 6px;
        border-top: none;
    }
    
    .search-wrapper #clear-search-btn {
        right: 10px;
        top: 15px;
    }
    
    .results-summary {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .job-item-meta {
        grid-template-columns: 1fr;
    }
    
    .job-item-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 10px;
    }
    
    .content {
        padding: 15px;
        margin: 10px 0;
    }
    
    .job-filters-wrapper {
        padding: 20px 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all filter elements
    const searchInput = document.getElementById('job-search-input');
    const categorySelect = document.getElementById('job-category-select');
    const locationSelect = document.getElementById('job-location-select');
    const jobTypeSelect = document.getElementById('job-type-select');
    const salarySelect = document.getElementById('salary-range-select');
    const featuredCheckbox = document.getElementById('featured-only');
    const sortSelect = document.getElementById('sort-select');
    
    const searchBtn = document.getElementById('search-btn');
    const clearSearchBtn = document.getElementById('clear-search-btn');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const clearAllFiltersBtn = document.getElementById('clear-all-filters');
    
    const activeFiltersCount = document.getElementById('active-filters-count');
    
    // Initialize from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    if (searchInput && urlParams.has('s')) {
        searchInput.value = urlParams.get('s');
        clearSearchBtn.style.display = searchInput.value ? 'block' : 'none';
    }
    
    if (categorySelect && urlParams.has('job_category')) {
        categorySelect.value = urlParams.get('job_category');
    }
    
    if (locationSelect && urlParams.has('job_location')) {
        locationSelect.value = urlParams.get('job_location');
    }
    
    if (jobTypeSelect && urlParams.has('job_type')) {
        jobTypeSelect.value = urlParams.get('job_type');
    }
    
    if (salarySelect && urlParams.has('salary_range')) {
        salarySelect.value = urlParams.get('salary_range');
    }
    
    if (featuredCheckbox && urlParams.has('featured')) {
        featuredCheckbox.checked = urlParams.get('featured') === '1';
    }
    
    if (sortSelect && urlParams.has('orderby')) {
        sortSelect.value = urlParams.get('orderby');
    }
    
    // Update active filters count
    updateActiveFiltersCount();
    
    // Search functionality
    function performSearch() {
        updateURL();
    }
    
    function clearSearch() {
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        updateURL();
    }
    
    function clearAllFilters() {
        if (searchInput) searchInput.value = '';
        if (categorySelect) categorySelect.value = '';
        if (locationSelect) locationSelect.value = '';
        if (jobTypeSelect) jobTypeSelect.value = '';
        if (salarySelect) salarySelect.value = '';
        if (featuredCheckbox) featuredCheckbox.checked = false;
        if (sortSelect) sortSelect.value = 'date';
        
        clearSearchBtn.style.display = 'none';
        updateURL();
    }
    
    function updateURL() {
        const currentUrl = new URL(window.location);
        
        // Clear existing parameters
        currentUrl.searchParams.delete('s');
        currentUrl.searchParams.delete('job_category');
        currentUrl.searchParams.delete('job_location');
        currentUrl.searchParams.delete('job_type');
        currentUrl.searchParams.delete('salary_range');
        currentUrl.searchParams.delete('featured');
        currentUrl.searchParams.delete('orderby');
        currentUrl.searchParams.delete('paged');
        
        // Add current values
        if (searchInput && searchInput.value.trim()) {
            currentUrl.searchParams.set('s', searchInput.value.trim());
        }
        
        if (categorySelect && categorySelect.value) {
            currentUrl.searchParams.set('job_category', categorySelect.value);
        }
        
        if (locationSelect && locationSelect.value) {
            currentUrl.searchParams.set('job_location', locationSelect.value);
        }
        
        if (jobTypeSelect && jobTypeSelect.value) {
            currentUrl.searchParams.set('job_type', jobTypeSelect.value);
        }
        
        if (salarySelect && salarySelect.value) {
            currentUrl.searchParams.set('salary_range', salarySelect.value);
        }
        
        if (featuredCheckbox && featuredCheckbox.checked) {
            currentUrl.searchParams.set('featured', '1');
        }
        
        if (sortSelect && sortSelect.value && sortSelect.value !== 'date') {
            currentUrl.searchParams.set('orderby', sortSelect.value);
        }
        
        window.location.href = currentUrl.toString();
    }
    
    function updateActiveFiltersCount() {
        let count = 0;
        
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
        if (clearSearchBtn) {
            clearSearchBtn.style.display = (searchInput && searchInput.value.trim()) ? 'block' : 'none';
        }
    }
    
    // Event listeners
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
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
                performSearch();
            }
        });
        
        searchInput.addEventListener('input', function() {
            clearSearchBtn.style.display = this.value.trim() ? 'block' : 'none';
        });
    }
    
    // Filter change listeners
    [categorySelect, locationSelect, jobTypeSelect, salarySelect, sortSelect].forEach(function(element) {
        if (element) {
            element.addEventListener('change', updateURL);
        }
    });
    
    if (featuredCheckbox) {
        featuredCheckbox.addEventListener('change', updateURL);
    }
    
    // Update count when filters change
    [searchInput, categorySelect, locationSelect, jobTypeSelect, salarySelect, featuredCheckbox].forEach(function(element) {
        if (element) {
            element.addEventListener('change', updateActiveFiltersCount);
            element.addEventListener('input', updateActiveFiltersCount);
        }
    });
});
</script>

<?php get_footer(); ?>