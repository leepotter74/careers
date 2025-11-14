<?php
/**
 * Emergency Database Table Creation
 * 
 * Visit this URL once to create the missing table:
 * http://yoursite.com/wp-content/plugins/big-bundle/modules/recruitment-manager/create-table.php
 */

// Include WordPress
require_once '../../../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

global $wpdb;
$table_name = $wpdb->prefix . 'recruitment_applications';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id int(11) NOT NULL AUTO_INCREMENT,
    job_id int(11) NOT NULL,
    applicant_name varchar(255) NOT NULL,
    applicant_email varchar(255) NOT NULL,
    phone varchar(50) DEFAULT '',
    application_data longtext,
    application_status varchar(50) DEFAULT 'pending',
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY job_id (job_id),
    KEY applicant_email (applicant_email),
    KEY application_status (application_status),
    KEY created_date (created_date)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$result = dbDelta($sql);

echo "<h2>Database Table Creation</h2>";
echo "<p>Attempted to create table: <strong>$table_name</strong></p>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Check if table now exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
echo "<p>Table exists now: <strong>" . ($table_exists ? 'YES' : 'NO') . "</strong></p>";

if ($table_exists) {
    echo "<p style='color: green;'>✅ Success! You can now submit job applications.</p>";
    echo "<p><a href='" . admin_url('admin.php?page=bb-recruitment-applications') . "'>Go to Applications Page</a></p>";
} else {
    echo "<p style='color: red;'>❌ Table creation failed. Check your database permissions.</p>";
}

// Clean up - delete this file after use
// unlink(__FILE__);
?>