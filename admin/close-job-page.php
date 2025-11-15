<?php
/**
 * Close Job Confirmation Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$app_count = isset($_GET['app_count']) ? intval($_GET['app_count']) : 0;

if (!$job_id) {
    wp_die(__('Invalid job ID.', 'big-bundle'));
}

$job_title = get_the_title($job_id);

// Handle delete applications action
if (isset($_POST['delete_applications']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_applications_' . $job_id)) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'recruitment_applications';

    $deleted = $wpdb->delete($table_name, array('job_id' => $job_id), array('%d'));

    $message = sprintf(
        _n(
            'Job closed successfully. %d application was deleted.',
            'Job closed successfully. %d applications were deleted.',
            $deleted,
            'big-bundle'
        ),
        $deleted
    );

    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
    $app_count = 0; // Reset count after deletion
}

?>

<div class="wrap">
    <h1><?php _e('Close Job', 'big-bundle'); ?></h1>

    <div class="card" style="max-width: 800px;">
        <h2><?php _e('Job Successfully Closed', 'big-bundle'); ?></h2>

        <p>
            <strong><?php _e('Job:', 'big-bundle'); ?></strong> <?php echo esc_html($job_title); ?>
        </p>

        <p>
            <?php echo esc_html(sprintf(
                _n(
                    'This job has %d pending application.',
                    'This job has %d pending applications.',
                    $app_count,
                    'big-bundle'
                ),
                $app_count
            )); ?>
        </p>

        <?php if ($app_count > 0) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php _e('What would you like to do with the applications?', 'big-bundle'); ?></strong>
                </p>
                <p><?php _e('You can choose to keep the applications for your records, or permanently delete them to clean up the database.', 'big-bundle'); ?></p>
            </div>

            <form method="POST" style="margin-top: 20px;">
                <?php wp_nonce_field('delete_applications_' . $job_id); ?>
                <input type="hidden" name="delete_applications" value="1" />

                <p>
                    <button type="submit" class="button button-primary button-large"
                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to permanently delete all applications for this job? This action cannot be undone.', 'big-bundle')); ?>');">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        <?php echo esc_html(sprintf(
                            _n(
                                'Delete %d Application',
                                'Delete All %d Applications',
                                $app_count,
                                'big-bundle'
                            ),
                            $app_count
                        )); ?>
                    </button>

                    <a href="<?php echo admin_url('edit.php?post_type=job_vacancy'); ?>" class="button button-large">
                        <?php _e('Keep Applications & Return to Jobs', 'big-bundle'); ?>
                    </a>
                </p>
            </form>
        <?php else : ?>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=job_vacancy'); ?>" class="button button-primary button-large">
                    <?php _e('Return to Jobs', 'big-bundle'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h3><?php _e('What Happens Next?', 'big-bundle'); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php _e('The job will be marked as "Closed" in the job listings', 'big-bundle'); ?></li>
            <li><?php _e('No new applications can be submitted for this job', 'big-bundle'); ?></li>
            <li><?php _e('The job will no longer appear in public job listings', 'big-bundle'); ?></li>
            <li><?php _e('You can reopen the job anytime by editing it and removing the "Manually Closed" meta field', 'big-bundle'); ?></li>
        </ul>
    </div>
</div>

<style>
.card p {
    margin: 10px 0;
}

.card .notice.inline {
    margin: 15px 0;
    padding: 10px 15px;
}

.button .dashicons {
    vertical-align: middle;
}
</style>
