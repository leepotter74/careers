<?php
/**
 * Create Job Alerts Table
 * 
 * Visit this URL once to create the missing alerts table:
 * http://freshtest.local/wp-content/plugins/big-bundle/modules/recruitment-manager/create-alerts-table.php
 */

// Include WordPress
require_once '../../../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

global $wpdb;
$table_name = $wpdb->prefix . 'recruitment_job_alerts';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) NOT NULL,
    alert_name varchar(255) DEFAULT '',
    alert_type enum('contextual','profile') DEFAULT 'profile',
    criteria longtext,
    frequency enum('immediate','daily','weekly') DEFAULT 'immediate',
    is_active tinyint(1) DEFAULT 1,
    last_sent datetime NULL,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY alert_type (alert_type),
    KEY is_active (is_active),
    KEY created_date (created_date)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$result = dbDelta($sql);

echo "<h2>Job Alerts Table Creation</h2>";
echo "<p>Attempted to create table: <strong>$table_name</strong></p>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Check if table now exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
echo "<p>Table exists now: <strong>" . ($table_exists ? 'YES' : 'NO') . "</strong></p>";

if ($table_exists) {
    echo "<p style='color: green;'>✅ Success! You can now create job alerts.</p>";
    echo "<p><a href='" . home_url() . "'>Go to your profile to test job alerts</a></p>";
} else {
    echo "<p style='color: red;'>❌ Table creation failed. Check your database permissions.</p>";
}

// Clean up - delete this file after use
// unlink(__FILE__);
?>