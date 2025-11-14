<?php
/**
 * Application Manager Class
 * 
 * Handles job application data management, status tracking, and admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class BB_Application_Manager {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'recruitment_applications';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_bb_update_application_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_bb_export_applications', array($this, 'ajax_export_applications'));
        add_action('wp_ajax_bb_get_application_details', array($this, 'ajax_get_application_details'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Handle bulk actions
        add_action('admin_post_bb_bulk_applications', array($this, 'handle_bulk_actions'));
    }
    
    /**
     * Add admin menu for application management
     */
    public function add_admin_menu() {
        // Update the existing Applications page to be functional
        remove_submenu_page('big-bundle', 'bb-recruitment-applications');
        
        add_submenu_page(
            'big-bundle',
            __('Applications', 'recruitment-manager'),
            '↳ ' . __('Applications', 'recruitment-manager'),
            'manage_options',
            'bb-recruitment-applications',
            array($this, 'applications_page')
        );
    }
    
    /**
     * Applications management page
     */
    public function applications_page() {
        // Handle status updates if submitted
        if (isset($_POST['update_status']) && wp_verify_nonce($_POST['app_nonce'], 'update_application_status')) {
            $this->handle_status_update();
        }
        
        // Get filter parameters
        $job_filter = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        // Get applications with filters
        $applications = $this->get_applications($job_filter, $status_filter, $date_from, $date_to, $search);
        $jobs = $this->get_jobs_with_applications();
        $stats = $this->get_application_stats();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Application Management', 'recruitment-manager'); ?></h1>
            
            <!-- Statistics Cards -->
            <div class="app-stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($stats['total']); ?></div>
                    <div class="stat-label"><?php _e('Total Applications', 'recruitment-manager'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($stats['pending']); ?></div>
                    <div class="stat-label"><?php _e('Pending Review', 'recruitment-manager'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($stats['shortlisted']); ?></div>
                    <div class="stat-label"><?php _e('Shortlisted', 'recruitment-manager'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($stats['this_week']); ?></div>
                    <div class="stat-label"><?php _e('This Week', 'recruitment-manager'); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="GET" class="app-filters">
                <input type="hidden" name="page" value="bb-recruitment-applications" />
                
                <div class="filter-row">
                    <select name="job_id">
                        <option value=""><?php _e('All Jobs', 'recruitment-manager'); ?></option>
                        <?php foreach ($jobs as $job) : ?>
                            <option value="<?php echo $job['id']; ?>" <?php selected($job_filter, $job['id']); ?>>
                                <?php echo esc_html($job['title']); ?> (<?php echo $job['count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'recruitment-manager'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'recruitment-manager'); ?></option>
                        <option value="reviewed" <?php selected($status_filter, 'reviewed'); ?>><?php _e('Reviewed', 'recruitment-manager'); ?></option>
                        <option value="shortlisted" <?php selected($status_filter, 'shortlisted'); ?>><?php _e('Shortlisted', 'recruitment-manager'); ?></option>
                        <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'recruitment-manager'); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('From Date', 'recruitment-manager'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('To Date', 'recruitment-manager'); ?>">
                    
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search applicants...', 'recruitment-manager'); ?>">
                    
                    <button type="submit" class="button"><?php _e('Filter', 'recruitment-manager'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=bb-recruitment-applications'); ?>" class="button"><?php _e('Clear', 'recruitment-manager'); ?></a>
                </div>
            </form>
            
            <!-- Bulk Actions -->
            <?php if (!empty($applications)) : ?>
            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>" id="bulk-action-form">
                <input type="hidden" name="action" value="bb_bulk_applications">
                <?php wp_nonce_field('bulk_applications', 'bulk_nonce'); ?>
                
                <div class="bulk-actions">
                    <select name="bulk_action">
                        <option value=""><?php _e('Bulk Actions', 'recruitment-manager'); ?></option>
                        <option value="mark_reviewed"><?php _e('Mark as Reviewed', 'recruitment-manager'); ?></option>
                        <option value="mark_shortlisted"><?php _e('Mark as Shortlisted', 'recruitment-manager'); ?></option>
                        <option value="mark_rejected"><?php _e('Mark as Rejected', 'recruitment-manager'); ?></option>
                        <option value="delete"><?php _e('Delete', 'recruitment-manager'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'recruitment-manager'); ?></option>
                    </select>
                    <button type="submit" class="button" onclick="return confirmBulkAction()"><?php _e('Apply', 'recruitment-manager'); ?></button>
                    
                    <button type="button" id="export-all-btn" class="button button-secondary" style="margin-left: 20px;">
                        <?php _e('Export All', 'recruitment-manager'); ?>
                    </button>
                </div>
            <?php endif; ?>
                
                <!-- Applications Table -->
                <table class="wp-list-table widefat fixed striped applications-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="select-all" />
                            </td>
                            <th class="column-applicant"><?php _e('Applicant', 'recruitment-manager'); ?></th>
                            <th class="column-job"><?php _e('Job', 'recruitment-manager'); ?></th>
                            <th class="column-status"><?php _e('Status', 'recruitment-manager'); ?></th>
                            <th class="column-date"><?php _e('Applied', 'recruitment-manager'); ?></th>
                            <th class="column-actions"><?php _e('Actions', 'recruitment-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)) : ?>
                            <tr>
                                <td colspan="6" class="no-applications">
                                    <div class="no-items">
                                        <h3><?php _e('No applications found', 'recruitment-manager'); ?></h3>
                                        <p><?php _e('Applications will appear here when candidates submit forms through your job listings.', 'recruitment-manager'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($applications as $application) : ?>
                                <tr data-app-id="<?php echo $application->id; ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="application_ids[]" value="<?php echo $application->id; ?>" />
                                    </th>
                                    <td class="column-applicant">
                                        <strong><?php echo esc_html($application->applicant_name); ?></strong><br>
                                        <a href="mailto:<?php echo esc_attr($application->applicant_email); ?>">
                                            <?php echo esc_html($application->applicant_email); ?>
                                        </a>
                                        <?php if (!empty($application->phone)) : ?>
                                            <br><?php echo esc_html($application->phone); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-job">
                                        <a href="<?php echo get_edit_post_link($application->job_id); ?>" target="_blank">
                                            <?php echo esc_html($application->job_title); ?>
                                        </a>
                                    </td>
                                    <td class="column-status">
                                        <span class="status-badge status-<?php echo esc_attr($application->application_status); ?>">
                                            <?php echo $this->get_status_label($application->application_status); ?>
                                        </span>
                                    </td>
                                    <td class="column-date">
                                        <?php echo date('M j, Y', strtotime($application->created_date)); ?><br>
                                        <small><?php echo date('H:i', strtotime($application->created_date)); ?></small>
                                    </td>
                                    <td class="column-actions">
                                        <button type="button" class="button button-small view-application" 
                                                data-app-id="<?php echo $application->id; ?>">
                                            <?php _e('View', 'recruitment-manager'); ?>
                                        </button>
                                        
                                        <select class="status-select" data-app-id="<?php echo $application->id; ?>">
                                            <option value="pending" <?php selected($application->application_status, 'pending'); ?>><?php _e('Pending', 'recruitment-manager'); ?></option>
                                            <option value="reviewed" <?php selected($application->application_status, 'reviewed'); ?>><?php _e('Reviewed', 'recruitment-manager'); ?></option>
                                            <option value="shortlisted" <?php selected($application->application_status, 'shortlisted'); ?>><?php _e('Shortlisted', 'recruitment-manager'); ?></option>
                                            <option value="rejected" <?php selected($application->application_status, 'rejected'); ?>><?php _e('Rejected', 'recruitment-manager'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php if (!empty($applications)) : ?>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- Application Detail Modal -->
        <div id="application-modal" class="application-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title"><?php _e('Application Details', 'recruitment-manager'); ?></h2>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="application-details"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary close-modal"><?php _e('Close', 'recruitment-manager'); ?></button>
                </div>
            </div>
        </div>
        
        <style>
        .app-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #0073aa;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .app-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-row select,
        .filter-row input[type="text"],
        .filter-row input[type="date"] {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .bulk-actions {
            margin: 15px 0;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .bulk-actions select,
        .bulk-actions button {
            margin-right: 10px;
        }
        
        .applications-table .column-applicant { width: 25%; }
        .applications-table .column-job { width: 25%; }
        .applications-table .column-status { width: 15%; }
        .applications-table .column-date { width: 15%; }
        .applications-table .column-actions { width: 20%; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #ffeaa7; color: #d63031; }
        .status-reviewed { background: #74b9ff; color: white; }
        .status-shortlisted { background: #00b894; color: white; }
        .status-rejected { background: #ddd; color: #666; }
        
        .status-select {
            font-size: 12px;
            padding: 2px 4px;
            margin-top: 5px;
        }
        
        .no-applications {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-items h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-items p {
            color: #999;
        }
        
        /* Modal Styles */
        .application-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            background: #f1f1f1;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }
        
        .modal-header h2 {
            margin: 0;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            background: #f9f9f9;
            border-top: 1px solid #ddd;
            text-align: right;
            border-radius: 0 0 8px 8px;
        }
        </style>
        <?php
    }
    
    /**
     * Get applications with optional filters
     */
    private function get_applications($job_filter = 0, $status_filter = '', $date_from = '', $date_to = '', $search = '') {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $prepare_values = array();
        
        if ($job_filter > 0) {
            $where_conditions[] = 'a.job_id = %d';
            $prepare_values[] = $job_filter;
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = 'a.application_status = %s';
            $prepare_values[] = $status_filter;
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = 'DATE(a.created_date) >= %s';
            $prepare_values[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = 'DATE(a.created_date) <= %s';
            $prepare_values[] = $date_to;
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(a.applicant_name LIKE %s OR a.applicant_email LIKE %s OR p.post_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT a.*, p.post_title as job_title 
            FROM {$this->table_name} a 
            LEFT JOIN {$wpdb->posts} p ON a.job_id = p.ID 
            WHERE {$where_clause} 
            ORDER BY a.created_date DESC 
            LIMIT 100
        ";
        
        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, $prepare_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get jobs that have applications
     */
    private function get_jobs_with_applications() {
        global $wpdb;
        
        $query = "
            SELECT p.ID as id, p.post_title as title, COUNT(a.id) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$this->table_name} a ON p.ID = a.job_id
            WHERE p.post_type = 'job_vacancy' AND p.post_status = 'publish'
            GROUP BY p.ID
            HAVING count > 0
            ORDER BY p.post_title
        ";
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get application statistics
     */
    private function get_application_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total applications
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // By status
        $stats['pending'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE application_status = %s", 
            'pending'
        ));
        
        $stats['shortlisted'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE application_status = %s", 
            'shortlisted'
        ));
        
        // This week
        $stats['this_week'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$this->table_name} 
            WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        return $stats;
    }
    
    /**
     * Get status label for display
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => __('Pending', 'recruitment-manager'),
            'reviewed' => __('Reviewed', 'recruitment-manager'),
            'shortlisted' => __('Shortlisted', 'recruitment-manager'),
            'rejected' => __('Rejected', 'recruitment-manager')
        );
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
    
    /**
     * AJAX handler for status updates
     */
    public function ajax_update_status() {
        check_ajax_referer('update_application_status', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'recruitment-manager'));
        }
        
        $app_id = intval($_POST['app_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        $valid_statuses = array('pending', 'reviewed', 'shortlisted', 'rejected');
        
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(__('Invalid status.', 'recruitment-manager'));
        }
        
        global $wpdb;
        $updated = $wpdb->update(
            $this->table_name,
            array('application_status' => $new_status, 'updated_date' => current_time('mysql')),
            array('id' => $app_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($updated !== false) {
            wp_send_json_success(__('Status updated successfully.', 'recruitment-manager'));
        } else {
            wp_send_json_error(__('Failed to update status.', 'recruitment-manager'));
        }
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['bulk_nonce'], 'bulk_applications')) {
            wp_die(__('Security check failed.', 'recruitment-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'recruitment-manager'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $app_ids = array_map('intval', $_POST['application_ids']);
        
        if (empty($action) || empty($app_ids)) {
            wp_redirect(admin_url('admin.php?page=bb-recruitment-applications&error=no_selection'));
            exit;
        }
        
        global $wpdb;
        $count = 0;
        
        switch ($action) {
            case 'mark_reviewed':
            case 'mark_shortlisted':
            case 'mark_rejected':
                $status = str_replace('mark_', '', $action);
                foreach ($app_ids as $app_id) {
                    $updated = $wpdb->update(
                        $this->table_name,
                        array('application_status' => $status, 'updated_date' => current_time('mysql')),
                        array('id' => $app_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                    if ($updated !== false) $count++;
                }
                $message = sprintf(__('%d applications updated.', 'recruitment-manager'), $count);
                break;
                
            case 'delete':
                foreach ($app_ids as $app_id) {
                    $deleted = $wpdb->delete($this->table_name, array('id' => $app_id), array('%d'));
                    if ($deleted !== false) $count++;
                }
                $message = sprintf(__('%d applications deleted.', 'recruitment-manager'), $count);
                break;
                
            case 'export':
                $this->export_applications($app_ids);
                exit;
        }
        
        wp_redirect(admin_url('admin.php?page=bb-recruitment-applications&success=' . urlencode($message)));
        exit;
    }
    
    /**
     * Export applications to CSV
     */
    private function export_applications($app_ids = array()) {
        global $wpdb;
        
        $where_clause = '';
        if (!empty($app_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($app_ids), '%d'));
            $where_clause = $wpdb->prepare(" WHERE a.id IN ($ids_placeholder)", $app_ids);
        }
        
        $query = "
            SELECT a.*, p.post_title as job_title 
            FROM {$this->table_name} a 
            LEFT JOIN {$wpdb->posts} p ON a.job_id = p.ID 
            {$where_clause}
            ORDER BY a.created_date DESC
        ";
        
        $applications = $wpdb->get_results($query);
        
        if (empty($applications)) {
            wp_redirect(admin_url('admin.php?page=bb-recruitment-applications&error=no_data'));
            exit;
        }
        
        $filename = 'job-applications-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Application ID',
            'Job Title',
            'Applicant Name',
            'Email',
            'Phone',
            'Status',
            'Applied Date',
            'Updated Date',
            'Application Data'
        ));
        
        // CSV data
        foreach ($applications as $app) {
            fputcsv($output, array(
                $app->id,
                $app->job_title,
                $app->applicant_name,
                $app->applicant_email,
                $app->phone,
                $this->get_status_label($app->application_status),
                $app->created_date,
                $app->updated_date,
                $app->application_data
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'big-bundle_page_bb-recruitment-applications') {
            wp_enqueue_script('bb-application-manager', BB_RECRUITMENT_MANAGER_PLUGIN_URL . 'admin/applications-script.js', array('jquery'), BB_RECRUITMENT_MANAGER_VERSION, true);
            wp_localize_script('bb-application-manager', 'bbAppManager', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bb_recruitment_nonce'),
                'confirm_delete' => __('Are you sure you want to delete the selected applications?', 'recruitment-manager'),
                'confirm_status' => __('Are you sure you want to update the status of selected applications?', 'recruitment-manager')
            ));
        }
    }
    
    /**
     * Add a new application (called by form submissions)
     */
    public function add_application($job_id, $applicant_name, $applicant_email, $application_data, $phone = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $this->table_name,
            array(
                'job_id' => $job_id,
                'applicant_name' => $applicant_name,
                'applicant_email' => $applicant_email,
                'phone' => $phone,
                'application_data' => is_array($application_data) ? json_encode($application_data) : $application_data,
                'application_status' => 'pending',
                'created_date' => current_time('mysql'),
                'updated_date' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX handler for exporting applications to CSV
     */
    public function ajax_export_applications() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get filter parameters
        $job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $selected_ids = isset($_GET['selected']) ? array_map('intval', explode(',', $_GET['selected'])) : [];
        
        // Build query conditions
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($job_id > 0) {
            $where_conditions[] = 'a.job_id = %d';
            $where_values[] = $job_id;
        }
        
        if (!empty($status)) {
            $where_conditions[] = 'a.application_status = %s';
            $where_values[] = $status;
        }
        
        if (!empty($selected_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '%d'));
            $where_conditions[] = "a.id IN ($placeholders)";
            $where_values = array_merge($where_values, $selected_ids);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get applications
        global $wpdb;
        $query = "
            SELECT a.*, p.post_title as job_title 
            FROM {$this->table_name} a 
            LEFT JOIN {$wpdb->posts} p ON a.job_id = p.ID 
            WHERE $where_clause 
            ORDER BY a.created_date DESC
        ";
        
        if (!empty($where_values)) {
            $applications = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $applications = $wpdb->get_results($query);
        }
        
        // Generate CSV
        $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Create file pointer connected to output stream
        $output = fopen('php://output', 'w');
        
        // Get all unique form fields across applications for dynamic columns
        $all_form_fields = $this->get_all_form_fields($applications);
        
        // Build CSV headers
        $headers = [
            'ID',
            'Job Title',
            'Applicant Name',
            'Email',
            'Phone',
            'Status',
            'Application Date',
            'Last Updated'
        ];
        
        // Add dynamic form field headers
        foreach ($all_form_fields as $field_key => $field_label) {
            $headers[] = $field_label;
        }
        
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($applications as $app) {
            // Parse application data
            $form_data = json_decode($app->application_data, true);
            $form_fields_data = $this->extract_form_fields_for_csv($form_data, $all_form_fields);
            
            // Build basic row
            $row = [
                $app->id,
                $app->job_title ?: 'Unknown Job',
                $app->applicant_name,
                $app->applicant_email,
                $app->phone,
                $this->get_status_label($app->application_status),
                date('d/m/Y H:i', strtotime($app->created_date)),
                date('d/m/Y H:i', strtotime($app->updated_date))
            ];
            
            // Add form field values in the same order as headers
            foreach ($all_form_fields as $field_key => $field_label) {
                $row[] = $form_fields_data[$field_key] ?? '';
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX handler for getting application details
     */
    public function ajax_get_application_details() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
        
        if (!$app_id) {
            wp_send_json_error('Invalid application ID');
        }
        
        global $wpdb;
        $application = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.post_title as job_title, p.ID as job_post_id
             FROM {$this->table_name} a 
             LEFT JOIN {$wpdb->posts} p ON a.job_id = p.ID 
             WHERE a.id = %d",
            $app_id
        ));
        
        if (!$application) {
            wp_send_json_error('Application not found');
        }
        
        // Parse application data
        $form_data = json_decode($application->application_data, true);
        $formatted_data = $this->format_application_data($form_data);
        
        $response = array(
            'id' => $application->id,
            'job_title' => $application->job_title,
            'job_url' => get_permalink($application->job_post_id),
            'applicant_name' => $application->applicant_name,
            'applicant_email' => $application->applicant_email,
            'phone' => $application->phone,
            'status' => $application->application_status,
            'status_label' => $this->get_status_label($application->application_status),
            'created_date' => $application->created_date,
            'updated_date' => $application->updated_date,
            'formatted_data' => $formatted_data
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Format application data for display
     */
    private function format_application_data($form_data) {
        if (!is_array($form_data)) {
            return '<p><em>No additional form data available.</em></p>';
        }
        
        $html = '';
        
        // Show form info
        if (isset($form_data['form_type'])) {
            $form_type_label = ucfirst(str_replace('_', ' ', $form_data['form_type']));
            $html .= '<div class="form-info">';
            $html .= '<h4>Form Information</h4>';
            $html .= '<p><strong>Form Type:</strong> ' . esc_html($form_type_label) . '</p>';
            
            if (isset($form_data['form_title'])) {
                $html .= '<p><strong>Form Title:</strong> ' . esc_html($form_data['form_title']) . '</p>';
            }
            $html .= '</div>';
        }
        
        // Show form fields
        if (isset($form_data['fields']) && is_array($form_data['fields'])) {
            $html .= '<div class="form-fields">';
            $html .= '<h4>Form Responses</h4>';
            
            // Check if this is Gravity Forms structure (array with label/value)
            $is_gravity_structure = false;
            foreach ($form_data['fields'] as $field) {
                if (is_array($field) && isset($field['label']) && isset($field['value'])) {
                    $is_gravity_structure = true;
                    break;
                }
            }
            
            if ($is_gravity_structure) {
                // Handle Gravity Forms structure
                foreach ($form_data['fields'] as $field_id => $field) {
                    if (is_array($field) && isset($field['label']) && isset($field['value'])) {
                        $label = esc_html($field['label']);
                        $value = $this->format_field_value($field['value'], $field['type'] ?? 'text');
                        
                        $html .= '<div class="form-field">';
                        $html .= '<strong>' . $label . ':</strong> ';
                        $html .= '<span class="field-value">' . $value . '</span>';
                        $html .= '</div>';
                    }
                }
            } else {
                // Handle Contact Form 7 and other simple key-value structures
                foreach ($form_data['fields'] as $field_name => $field_value) {
                    // Skip empty values and system fields
                    if (empty($field_value) || in_array($field_name, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', 'job-id'])) {
                        continue;
                    }
                    
                    // Convert field name to readable label
                    $label = $this->convert_field_name_to_label($field_name);
                    $value = $this->format_field_value($field_value, $this->detect_field_type($field_name, $field_value));
                    
                    $html .= '<div class="form-field">';
                    $html .= '<strong>' . $label . ':</strong> ';
                    $html .= '<span class="field-value">' . $value . '</span>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>';
        } else if (isset($form_data['fields']) && !is_array($form_data['fields'])) {
            // Handle other form types that might not structure data the same way
            $html .= '<div class="form-data-raw">';
            $html .= '<h4>Form Data</h4>';
            $html .= '<pre>' . esc_html(print_r($form_data, true)) . '</pre>';
            $html .= '</div>';
        }
        
        return $html ?: '<p><em>No form fields found in application data.</em></p>';
    }
    
    /**
     * Format field value for display
     */
    private function format_field_value($value, $type = 'text') {
        if (is_array($value)) {
            return esc_html(implode(', ', $value));
        }
        
        $value = esc_html($value);
        
        // Handle specific field types
        switch ($type) {
            case 'email':
                return '<a href="mailto:' . $value . '">' . $value . '</a>';
            case 'phone':
                return '<a href="tel:' . preg_replace('/[^\d+]/', '', $value) . '">' . $value . '</a>';
            case 'url':
            case 'website':
                return '<a href="' . $value . '" target="_blank">' . $value . '</a>';
            case 'fileupload':
                return '<em>File uploaded: ' . basename($value) . '</em>';
            case 'textarea':
                return '<div class="textarea-value">' . nl2br($value) . '</div>';
            default:
                return $value;
        }
    }
    
    /**
     * Convert field name to readable label
     */
    private function convert_field_name_to_label($field_name) {
        // Handle common field naming patterns
        $label = str_replace(['-', '_'], ' ', $field_name);
        $label = ucwords($label);
        
        // Handle common field names with better labels
        $common_labels = [
            'Your Name' => 'Name',
            'Your Email' => 'Email',
            'Your Phone' => 'Phone',
            'Your Address' => 'Address',
            'Your Message' => 'Message',
            'Your Comments' => 'Comments',
            'Your Question' => 'Question',
            'Your Subject' => 'Subject',
            'Best Time' => 'Best Time to Call',
            'Preferred Contact' => 'Preferred Contact Method'
        ];
        
        return $common_labels[$label] ?? $label;
    }
    
    /**
     * Detect field type from name and value
     */
    private function detect_field_type($field_name, $field_value) {
        $field_name_lower = strtolower($field_name);
        
        // Detect by field name
        if (strpos($field_name_lower, 'email') !== false) {
            return 'email';
        }
        if (strpos($field_name_lower, 'phone') !== false || strpos($field_name_lower, 'tel') !== false) {
            return 'phone';
        }
        if (strpos($field_name_lower, 'url') !== false || strpos($field_name_lower, 'website') !== false) {
            return 'url';
        }
        if (strpos($field_name_lower, 'message') !== false || strpos($field_name_lower, 'comment') !== false || strpos($field_name_lower, 'address') !== false) {
            return 'textarea';
        }
        
        // Detect by value format
        if (is_email($field_value)) {
            return 'email';
        }
        if (filter_var($field_value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        if (preg_match('/^[\d\s\-\+\(\)\.]+$/', $field_value) && strlen($field_value) >= 10) {
            return 'phone';
        }
        if (strlen($field_value) > 100 || strpos($field_value, "\n") !== false) {
            return 'textarea';
        }
        
        return 'text';
    }
    
    /**
     * Get all unique form fields across applications for CSV columns
     */
    private function get_all_form_fields($applications) {
        $all_fields = [];
        
        foreach ($applications as $app) {
            $form_data = json_decode($app->application_data, true);
            if (!is_array($form_data) || !isset($form_data['fields'])) {
                continue;
            }
            
            // Check if this is Gravity Forms structure (array with label/value)
            $is_gravity_structure = false;
            foreach ($form_data['fields'] as $field) {
                if (is_array($field) && isset($field['label']) && isset($field['value'])) {
                    $is_gravity_structure = true;
                    break;
                }
            }
            
            if ($is_gravity_structure) {
                // Handle Gravity Forms structure
                foreach ($form_data['fields'] as $field_id => $field) {
                    if (is_array($field) && isset($field['label']) && !empty($field['value'])) {
                        $field_key = 'gf_' . $field_id;
                        $all_fields[$field_key] = $field['label'];
                    }
                }
            } else {
                // Handle Contact Form 7 and other simple structures
                foreach ($form_data['fields'] as $field_name => $field_value) {
                    // Skip empty values and system fields
                    if (empty($field_value) || in_array($field_name, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', 'job-id'])) {
                        continue;
                    }
                    
                    $field_key = 'cf7_' . $field_name;
                    $field_label = $this->convert_field_name_to_label($field_name);
                    $all_fields[$field_key] = $field_label;
                }
            }
        }
        
        return $all_fields;
    }
    
    /**
     * Extract form field data for CSV in clean format
     */
    private function extract_form_fields_for_csv($form_data, $all_field_keys) {
        $field_values = [];
        
        if (!is_array($form_data) || !isset($form_data['fields'])) {
            return $field_values;
        }
        
        // Check if this is Gravity Forms structure
        $is_gravity_structure = false;
        foreach ($form_data['fields'] as $field) {
            if (is_array($field) && isset($field['label']) && isset($field['value'])) {
                $is_gravity_structure = true;
                break;
            }
        }
        
        if ($is_gravity_structure) {
            // Handle Gravity Forms structure
            foreach ($form_data['fields'] as $field_id => $field) {
                if (is_array($field) && isset($field['label']) && isset($field['value'])) {
                    $field_key = 'gf_' . $field_id;
                    $clean_value = $this->clean_field_value_for_csv($field['value'], $field['type'] ?? 'text');
                    $field_values[$field_key] = $clean_value;
                }
            }
        } else {
            // Handle Contact Form 7 and other simple structures
            foreach ($form_data['fields'] as $field_name => $field_value) {
                // Skip empty values and system fields
                if (empty($field_value) || in_array($field_name, ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', 'job-id'])) {
                    continue;
                }
                
                $field_key = 'cf7_' . $field_name;
                $clean_value = $this->clean_field_value_for_csv($field_value, $this->detect_field_type($field_name, $field_value));
                $field_values[$field_key] = $clean_value;
            }
        }
        
        return $field_values;
    }
    
    /**
     * Clean field value for CSV export (remove HTML, format nicely)
     */
    private function clean_field_value_for_csv($value, $type = 'text') {
        if (is_array($value)) {
            return implode(', ', array_map('strip_tags', $value));
        }
        
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        
        // Handle specific field types
        switch ($type) {
            case 'textarea':
                // Replace line breaks with semicolons for CSV compatibility
                return str_replace(["\r\n", "\n", "\r"], '; ', $value);
            case 'address':
                // Clean up address formatting
                return preg_replace('/,\s*,/', ',', $value);
            default:
                return $value;
        }
    }
}