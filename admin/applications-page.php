<?php
/**
 * Applications Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$application_handler = Application_Handler::get_instance();

// Handle form actions
if ($_POST && wp_verify_nonce($_POST['bb_recruitment_nonce'] ?? '', 'bb_recruitment_applications')) {
    $action = sanitize_text_field($_POST['action'] ?? '');
    
    switch ($action) {
        case 'update_status':
            $application_id = intval($_POST['application_id']);
            $new_status = sanitize_text_field($_POST['new_status']);
            
            if ($application_handler->update_status($application_id, $new_status)) {
                echo '<div class="notice notice-success"><p>' . __('Application status updated successfully.', 'big-bundle') . '</p></div>';
            }
            break;
            
        case 'delete_application':
            $application_id = intval($_POST['application_id']);
            
            if ($application_handler->delete_application($application_id)) {
                echo '<div class="notice notice-success"><p>' . __('Application deleted successfully.', 'big-bundle') . '</p></div>';
            }
            break;
    }
}

// Get filters
$job_filter = intval($_GET['job_id'] ?? 0);
$status_filter = sanitize_text_field($_GET['status'] ?? '');
$search_filter = sanitize_text_field($_GET['search'] ?? '');
$current_page = intval($_GET['paged'] ?? 1);
$per_page = 20;

// Build query args
$query_args = array(
    'limit' => $per_page,
    'offset' => ($current_page - 1) * $per_page,
    'search' => $search_filter
);

if ($job_filter) {
    $query_args['job_id'] = $job_filter;
}

if ($status_filter) {
    $query_args['status'] = $status_filter;
}

// Get applications
$applications = $application_handler->get_applications($query_args);
$total_applications = $application_handler->get_applications_count($query_args);
$total_pages = ceil($total_applications / $per_page);

// Get all jobs for filter dropdown
$jobs = get_posts(array(
    'post_type' => 'job_vacancy',
    'post_status' => array('publish', 'expired'),
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

?>
<div class="wrap bb-applications-page">
    <h1>
        <?php _e('Job Applications', 'big-bundle'); ?>
        <span class="bb-total-count">(<?php echo number_format($total_applications); ?>)</span>
    </h1>
    
    <!-- Filters -->
    <div class="bb-filters-section">
        <form method="get" action="" class="bb-filters-form">
            <input type="hidden" name="page" value="bb-recruitment-applications">
            
            <div class="bb-filter-group">
                <label for="job_filter"><?php _e('Filter by Job:', 'big-bundle'); ?></label>
                <select name="job_id" id="job_filter">
                    <option value=""><?php _e('All Jobs', 'big-bundle'); ?></option>
                    <?php foreach ($jobs as $job): ?>
                    <option value="<?php echo $job->ID; ?>" <?php selected($job_filter, $job->ID); ?>>
                        <?php echo esc_html($job->post_title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="bb-filter-group">
                <label for="status_filter"><?php _e('Filter by Status:', 'big-bundle'); ?></label>
                <select name="status" id="status_filter">
                    <option value=""><?php _e('All Statuses', 'big-bundle'); ?></option>
                    <option value="submitted" <?php selected($status_filter, 'submitted'); ?>><?php _e('Submitted', 'big-bundle'); ?></option>
                    <option value="under_review" <?php selected($status_filter, 'under_review'); ?>><?php _e('Under Review', 'big-bundle'); ?></option>
                    <option value="shortlisted" <?php selected($status_filter, 'shortlisted'); ?>><?php _e('Shortlisted', 'big-bundle'); ?></option>
                    <option value="interviewed" <?php selected($status_filter, 'interviewed'); ?>><?php _e('Interviewed', 'big-bundle'); ?></option>
                    <option value="offered" <?php selected($status_filter, 'offered'); ?>><?php _e('Offered', 'big-bundle'); ?></option>
                    <option value="hired" <?php selected($status_filter, 'hired'); ?>><?php _e('Hired', 'big-bundle'); ?></option>
                    <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'big-bundle'); ?></option>
                    <option value="withdrawn" <?php selected($status_filter, 'withdrawn'); ?>><?php _e('Withdrawn', 'big-bundle'); ?></option>
                </select>
            </div>
            
            <div class="bb-filter-group">
                <label for="search_filter"><?php _e('Search:', 'big-bundle'); ?></label>
                <input type="text" name="search" id="search_filter" value="<?php echo esc_attr($search_filter); ?>" 
                       placeholder="<?php _e('Name or email...', 'big-bundle'); ?>">
            </div>
            
            <div class="bb-filter-actions">
                <button type="submit" class="button"><?php _e('Filter', 'big-bundle'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button">
                    <?php _e('Clear', 'big-bundle'); ?>
                </a>
            </div>
        </form>
        
        <div class="bb-bulk-actions">
            <button type="button" class="button" id="bb-export-applications">
                <?php _e('Export Results', 'big-bundle'); ?>
            </button>
        </div>
    </div>
    
    <?php if (empty($applications)): ?>
    <div class="bb-no-applications">
        <h2><?php _e('No applications found', 'big-bundle'); ?></h2>
        <p><?php _e('No applications match your current filters.', 'big-bundle'); ?></p>
        <?php if ($job_filter || $status_filter || $search_filter): ?>
        <p><a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button">
            <?php _e('View All Applications', 'big-bundle'); ?>
        </a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    
    <!-- Applications Table -->
    <div class="bb-applications-table-container">
        <table class="wp-list-table widefat fixed striped bb-applications-table">
            <thead>
                <tr>
                    <th class="column-applicant"><?php _e('Applicant', 'big-bundle'); ?></th>
                    <th class="column-job"><?php _e('Position', 'big-bundle'); ?></th>
                    <th class="column-applied"><?php _e('Applied', 'big-bundle'); ?></th>
                    <th class="column-status"><?php _e('Status', 'big-bundle'); ?></th>
                    <th class="column-actions"><?php _e('Actions', 'big-bundle'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $application): ?>
                <tr class="bb-application-row" data-application-id="<?php echo $application->id; ?>">
                    <td class="column-applicant">
                        <div class="bb-applicant-info">
                            <strong class="bb-applicant-name"><?php echo esc_html($application->applicant_name); ?></strong>
                            <div class="bb-applicant-contact">
                                <a href="mailto:<?php echo esc_attr($application->applicant_email); ?>" class="bb-email-link">
                                    <?php echo esc_html($application->applicant_email); ?>
                                </a>
                                <?php if ($application->applicant_phone): ?>
                                <span class="bb-phone"><?php echo esc_html($application->applicant_phone); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="column-job">
                        <?php 
                        $job_title = get_the_title($application->job_id);
                        if ($job_title): 
                        ?>
                        <a href="<?php echo get_edit_post_link($application->job_id); ?>" class="bb-job-link">
                            <?php echo esc_html($job_title); ?>
                        </a>
                        <?php else: ?>
                        <span class="bb-job-deleted"><?php _e('Job no longer available', 'big-bundle'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-applied">
                        <span class="bb-date" title="<?php echo esc_attr($application->created_date); ?>">
                            <?php echo human_time_diff(strtotime($application->created_date), current_time('timestamp')) . ' ' . __('ago', 'big-bundle'); ?>
                        </span>
                    </td>
                    <td class="column-status">
                        <select class="bb-status-select" data-application-id="<?php echo $application->id; ?>">
                            <option value="submitted" <?php selected($application->application_status, 'submitted'); ?>><?php _e('Submitted', 'big-bundle'); ?></option>
                            <option value="under_review" <?php selected($application->application_status, 'under_review'); ?>><?php _e('Under Review', 'big-bundle'); ?></option>
                            <option value="shortlisted" <?php selected($application->application_status, 'shortlisted'); ?>><?php _e('Shortlisted', 'big-bundle'); ?></option>
                            <option value="interviewed" <?php selected($application->application_status, 'interviewed'); ?>><?php _e('Interviewed', 'big-bundle'); ?></option>
                            <option value="offered" <?php selected($application->application_status, 'offered'); ?>><?php _e('Offered', 'big-bundle'); ?></option>
                            <option value="hired" <?php selected($application->application_status, 'hired'); ?>><?php _e('Hired', 'big-bundle'); ?></option>
                            <option value="rejected" <?php selected($application->application_status, 'rejected'); ?>><?php _e('Rejected', 'big-bundle'); ?></option>
                            <option value="withdrawn" <?php selected($application->application_status, 'withdrawn'); ?>><?php _e('Withdrawn', 'big-bundle'); ?></option>
                        </select>
                    </td>
                    <td class="column-actions">
                        <div class="bb-action-buttons">
                            <button type="button" class="button button-small bb-view-application" 
                                    data-application-id="<?php echo $application->id; ?>">
                                <?php _e('View', 'big-bundle'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete bb-delete-application" 
                                    data-application-id="<?php echo $application->id; ?>">
                                <?php _e('Delete', 'big-bundle'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bb-pagination">
        <?php
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo; ' . __('Previous', 'big-bundle'),
            'next_text' => __('Next', 'big-bundle') . ' &raquo;',
            'total' => $total_pages,
            'current' => $current_page,
            'show_all' => false,
            'end_size' => 1,
            'mid_size' => 2,
            'type' => 'list'
        );
        
        echo paginate_links($pagination_args);
        ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Application Detail Modal -->
<div id="bb-application-modal" class="bb-modal" style="display: none;">
    <div class="bb-modal-overlay"></div>
    <div class="bb-modal-content">
        <div class="bb-modal-header">
            <h2><?php _e('Application Details', 'big-bundle'); ?></h2>
            <button type="button" class="bb-modal-close">&times;</button>
        </div>
        <div class="bb-modal-body">
            <div id="bb-application-details"></div>
        </div>
        <div class="bb-modal-footer">
            <button type="button" class="button bb-modal-close"><?php _e('Close', 'big-bundle'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Status change handler
    $('.bb-status-select').on('change', function() {
        const applicationId = $(this).data('application-id');
        const newStatus = $(this).val();
        const $select = $(this);
        const originalStatus = $select.data('original-status') || $select.find('option:selected').data('original');
        
        if (confirm('<?php _e('Are you sure you want to change this application status?', 'big-bundle'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_update_application_status',
                    application_id: applicationId,
                    status: newStatus,
                    nonce: '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $select.data('original-status', newStatus);
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                            .insertAfter('.wrap h1').delay(3000).fadeOut();
                    } else {
                        alert(response.data || '<?php _e('Error updating status', 'big-bundle'); ?>');
                        $select.val(originalStatus);
                    }
                },
                error: function() {
                    alert('<?php _e('Error updating status', 'big-bundle'); ?>');
                    $select.val(originalStatus);
                }
            });
        } else {
            $select.val(originalStatus);
        }
    });
    
    // View application handler
    $('.bb-view-application').on('click', function() {
        const applicationId = $(this).data('application-id');
        
        // Load application details via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bb_get_application_details',
                application_id: applicationId,
                nonce: '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#bb-application-details').html(response.data.html);
                    $('#bb-application-modal').show();
                } else {
                    alert(response.data || '<?php _e('Error loading application', 'big-bundle'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error loading application', 'big-bundle'); ?>');
            }
        });
    });
    
    // Delete application handler
    $('.bb-delete-application').on('click', function() {
        const applicationId = $(this).data('application-id');
        const $row = $(this).closest('.bb-application-row');
        
        if (confirm('<?php _e('Are you sure you want to delete this application? This action cannot be undone.', 'big-bundle'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bb_delete_application',
                    application_id: applicationId,
                    nonce: '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                            .insertAfter('.wrap h1').delay(3000).fadeOut();
                    } else {
                        alert(response.data || '<?php _e('Error deleting application', 'big-bundle'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('Error deleting application', 'big-bundle'); ?>');
                }
            });
        }
    });
    
    // Export applications handler
    $('#bb-export-applications').on('click', function() {
        const params = new URLSearchParams(window.location.search);
        params.set('action', 'bb_export_applications');
        params.set('nonce', '<?php echo wp_create_nonce('bb_recruitment_nonce'); ?>');
        
        window.location.href = ajaxurl + '?' + params.toString();
    });
    
    // Modal handlers
    $('.bb-modal-close, .bb-modal-overlay').on('click', function() {
        $('#bb-application-modal').hide();
    });
    
    // Initialize original status values
    $('.bb-status-select').each(function() {
        $(this).data('original-status', $(this).val());
    });
});
</script>

<style>
.bb-applications-page {
    margin-right: 20px;
}

.bb-total-count {
    color: #666;
    font-weight: normal;
    font-size: 16px;
}

/* Filters */
.bb-filters-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin: 20px 0;
    padding: 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.bb-filters-form {
    display: flex;
    align-items: flex-end;
    gap: 15px;
    flex-wrap: wrap;
}

.bb-filter-group {
    display: flex;
    flex-direction: column;
    min-width: 120px;
}

.bb-filter-group label {
    font-size: 12px;
    color: #666;
    margin-bottom: 3px;
}

.bb-filter-group select,
.bb-filter-group input[type="text"] {
    height: 30px;
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.bb-filter-actions {
    display: flex;
    gap: 10px;
}

/* Applications Table */
.bb-applications-table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow-x: auto;
}

.bb-applications-table {
    margin: 0;
}

.bb-applications-table th,
.bb-applications-table td {
    padding: 12px 10px;
    vertical-align: top;
}

.column-applicant { width: 25%; }
.column-job { width: 25%; }
.column-applied { width: 15%; }
.column-status { width: 15%; }
.column-actions { width: 20%; }

.bb-applicant-info {
    line-height: 1.4;
}

.bb-applicant-name {
    display: block;
    color: #2c3e50;
    margin-bottom: 3px;
}

.bb-applicant-contact {
    font-size: 12px;
    color: #666;
}

.bb-email-link {
    text-decoration: none;
    color: #0073aa;
}

.bb-phone::before {
    content: " • ";
    margin: 0 5px;
}

.bb-job-link {
    color: #0073aa;
    text-decoration: none;
}

.bb-job-deleted {
    color: #dc3232;
    font-style: italic;
}

.bb-date {
    color: #666;
    font-size: 13px;
}

.bb-status-select {
    width: 100%;
    padding: 4px 6px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 12px;
}

.bb-action-buttons {
    display: flex;
    gap: 5px;
}

/* No applications */
.bb-no-applications {
    text-align: center;
    padding: 40px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    color: #666;
}

/* Pagination */
.bb-pagination {
    margin: 20px 0;
    text-align: center;
}

.bb-pagination ul {
    display: inline-block;
    margin: 0;
    padding: 0;
    list-style: none;
}

.bb-pagination li {
    display: inline-block;
    margin: 0 2px;
}

.bb-pagination a,
.bb-pagination span {
    display: block;
    padding: 8px 12px;
    text-decoration: none;
    border: 1px solid #ddd;
    background: white;
}

.bb-pagination .current {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

/* Modal */
.bb-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
}

.bb-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
}

.bb-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 700px;
    max-height: 80%;
    background: white;
    border-radius: 5px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.bb-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background: #f8f9fa;
}

.bb-modal-header h2 {
    margin: 0;
    color: #2c3e50;
}

.bb-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bb-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.bb-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    background: #f8f9fa;
    text-align: right;
}

/* Responsive */
@media (max-width: 768px) {
    .bb-filters-section {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .bb-filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bb-filter-group {
        min-width: auto;
    }
    
    .bb-action-buttons {
        flex-direction: column;
    }
    
    .bb-modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>