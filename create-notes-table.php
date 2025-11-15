<?php
/**
 * Create Application Notes Table
 *
 * Access this via browser: http://freshtest.local/wp-content/plugins/big-bundle/modules/recruitment-manager/create-notes-table.php
 */

// Load WordPress
$wp_load_path = __DIR__ . '/../../../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Could not find WordPress. Path tried: ' . $wp_load_path . '<br>Current dir: ' . __DIR__);
}

// Create the table
global $wpdb;
$table_name = $wpdb->prefix . 'recruitment_application_notes';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    application_id bigint(20) NOT NULL,
    user_id bigint(20) NOT NULL,
    note_text text NOT NULL,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY application_id (application_id),
    KEY user_id (user_id),
    KEY created_date (created_date)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// Check if table was created
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if ($table_exists) {
    echo "<h1>Success!</h1>";
    echo "<p>Application notes table created successfully!</p>";
    echo "<p>Table name: <strong>$table_name</strong></p>";
    echo "<p><a href='/wp-admin/admin.php?page=bb-recruitment-board'>Go to Applicant Board</a></p>";
} else {
    echo "<h1>Error!</h1>";
    echo "<p>Failed to create table. Please check database permissions.</p>";
}
