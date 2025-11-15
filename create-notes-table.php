<?php
/**
 * Create Application Notes Table
 *
 * Run this file once to create the notes table
 */

require_once('../../../../wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'recruitment_application_notes';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
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

echo "Application notes table created successfully!\n";
echo "Table name: $table_name\n";
