<?php
/**
 * Applicant Board (Kanban View)
 *
 * Visual kanban-style board for managing job applications
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Get all applications
global $wpdb;
$table_name = $wpdb->prefix . 'recruitment_applications';

$where_clauses = array('1=1');
$query_params = array();

if ($job_filter) {
    $where_clauses[] = 'job_id = %d';
    $query_params[] = $job_filter;
}

if ($search) {
    $where_clauses[] = '(applicant_name LIKE %s OR applicant_email LIKE %s)';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
}

$where_sql = implode(' AND ', $where_clauses);

$query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_date DESC";

if (!empty($query_params)) {
    $applications = $wpdb->get_results($wpdb->prepare($query, $query_params));
} else {
    $applications = $wpdb->get_results($query);
}

// Group applications by status
$board_columns = array(
    'submitted' => array('label' => __('Submitted', 'big-bundle'), 'applications' => array()),
    'under_review' => array('label' => __('Under Review', 'big-bundle'), 'applications' => array()),
    'shortlisted' => array('label' => __('Shortlisted', 'big-bundle'), 'applications' => array()),
    'interviewed' => array('label' => __('Interviewed', 'big-bundle'), 'applications' => array()),
    'offered' => array('label' => __('Offered', 'big-bundle'), 'applications' => array()),
    'hired' => array('label' => __('Hired', 'big-bundle'), 'applications' => array()),
    'rejected' => array('label' => __('Rejected', 'big-bundle'), 'applications' => array()),
    'withdrawn' => array('label' => __('Withdrawn', 'big-bundle'), 'applications' => array())
);

// Organize applications into columns
foreach ($applications as $app) {
    $status = $app->application_status ?: 'submitted';
    if (isset($board_columns[$status])) {
        $board_columns[$status]['applications'][] = $app;
    } else {
        // Default to submitted if unknown status
        $board_columns['submitted']['applications'][] = $app;
    }
}

// Get jobs for filter
$jobs = $wpdb->get_results("
    SELECT p.ID, p.post_title, COUNT(a.id) as app_count
    FROM {$wpdb->posts} p
    LEFT JOIN $table_name a ON p.ID = a.job_id
    WHERE p.post_type = 'job_vacancy' AND p.post_status = 'publish'
    GROUP BY p.ID
    HAVING app_count > 0
    ORDER BY p.post_title
");

?>
<div class="wrap applicant-board-wrap">
    <div class="board-header">
        <h1 class="wp-heading-inline">
            <?php _e('Applicant Board', 'big-bundle'); ?>
        </h1>
        <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="page-title-action">
            <?php _e('← List View', 'big-bundle'); ?>
        </a>
    </div>

    <!-- Filters -->
    <div class="board-filters">
        <form method="GET" class="board-filter-form">
            <input type="hidden" name="page" value="bb-recruitment-board" />

            <select name="job_id" onchange="this.form.submit()">
                <option value=""><?php _e('All Jobs', 'big-bundle'); ?></option>
                <?php foreach ($jobs as $job) : ?>
                    <option value="<?php echo $job->ID; ?>" <?php selected($job_filter, $job->ID); ?>>
                        <?php echo esc_html($job->post_title); ?> (<?php echo $job->app_count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search applicants...', 'big-bundle'); ?>">

            <button type="submit" class="button"><?php _e('Filter', 'big-bundle'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=bb-recruitment-board'); ?>" class="button"><?php _e('Clear', 'big-bundle'); ?></a>
        </form>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-board">
        <?php foreach ($board_columns as $status_key => $column) : ?>
            <div class="kanban-column" data-status="<?php echo esc_attr($status_key); ?>">
                <div class="column-header">
                    <h3><?php echo esc_html($column['label']); ?></h3>
                    <span class="column-count"><?php echo count($column['applications']); ?></span>
                </div>
                <div class="column-cards" data-status="<?php echo esc_attr($status_key); ?>">
                    <?php foreach ($column['applications'] as $app) :
                        $app_data = !empty($app->application_data) ? json_decode($app->application_data, true) : array();
                        $job_title = get_the_title($app->job_id);

                        // Check for completion indicators
                        $has_form = !empty($app_data);
                        $has_cv = false;
                        $has_cover_letter = false;

                        // Check for CV and cover letter in application data
                        if ($has_form && isset($app_data['fields'])) {
                            foreach ($app_data['fields'] as $key => $value) {
                                $key_lower = strtolower($key);
                                if (is_array($value) && isset($value['label'])) {
                                    $key_lower = strtolower($value['label']);
                                }

                                if (strpos($key_lower, 'cv') !== false || strpos($key_lower, 'resume') !== false) {
                                    $has_cv = !empty($value);
                                }
                                if (strpos($key_lower, 'cover') !== false && strpos($key_lower, 'letter') !== false) {
                                    $has_cover_letter = !empty($value);
                                }
                            }
                        }
                    ?>
                        <div class="kanban-card"
                             data-id="<?php echo $app->id; ?>"
                             data-status="<?php echo esc_attr($status_key); ?>"
                             draggable="true">
                            <div class="card-header">
                                <strong class="applicant-name"><?php echo esc_html($app->applicant_name); ?></strong>
                                <span class="card-date"><?php echo date('M d', strtotime($app->created_date)); ?></span>
                            </div>
                            <div class="card-job">
                                <?php echo esc_html($job_title); ?>
                            </div>
                            <div class="card-indicators">
                                <span class="indicator <?php echo $has_form ? 'complete' : 'incomplete'; ?>"
                                      title="<?php echo $has_form ? __('Form Complete', 'big-bundle') : __('Form Incomplete', 'big-bundle'); ?>">
                                    <?php if ($has_form) : ?>
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Form', 'big-bundle'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus"></span> <?php _e('Form', 'big-bundle'); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="indicator <?php echo $has_cv ? 'complete' : 'incomplete'; ?>"
                                      title="<?php echo $has_cv ? __('CV Uploaded', 'big-bundle') : __('No CV', 'big-bundle'); ?>">
                                    <?php if ($has_cv) : ?>
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('CV', 'big-bundle'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus"></span> <?php _e('CV', 'big-bundle'); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="indicator <?php echo $has_cover_letter ? 'complete' : 'incomplete'; ?>"
                                      title="<?php echo $has_cover_letter ? __('Cover Letter Submitted', 'big-bundle') : __('No Cover Letter', 'big-bundle'); ?>">
                                    <?php if ($has_cover_letter) : ?>
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Letter', 'big-bundle'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus"></span> <?php _e('Letter', 'big-bundle'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="card-email">
                                <span class="dashicons dashicons-email"></span>
                                <?php echo esc_html($app->applicant_email); ?>
                            </div>
                            <?php if (!empty($app->applicant_phone)) : ?>
                                <div class="card-phone">
                                    <span class="dashicons dashicons-phone"></span>
                                    <?php echo esc_html($app->applicant_phone); ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-actions">
                                <button class="view-details-btn" data-id="<?php echo $app->id; ?>">
                                    <?php _e('View Details', 'big-bundle'); ?>
                                </button>
                            </div>
                            <div class="card-notes-preview">
                                <em><?php _e('Notes feature coming soon', 'big-bundle'); ?></em>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($column['applications'])) : ?>
                        <div class="empty-column">
                            <p><?php _e('No applications', 'big-bundle'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Application Details Modal (reuse existing modal) -->
<div id="application-modal" class="application-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="modal-application-details">
            <!-- Details loaded via AJAX -->
        </div>
    </div>
</div>
