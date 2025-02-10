<?php
// This file is leanx-table.php

function create_leanx_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // First table: transaction_details
    $table_name1 = $wpdb->prefix . 'transaction_details';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name1'") != $table_name1) {
        $sql1 = "CREATE TABLE $table_name1 (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            unique_id text NOT NULL,
            auth_token text NOT NULL,
            callback_data text,
            data text,
            invoice_id text,
            invoice_status VARCHAR(50) DEFAULT NULL,  /* New Column Added */
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
    } else {
        // Check if 'invoice_status' column exists, if not, add it
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table_name1` LIKE 'invoice_status'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$table_name1` ADD COLUMN invoice_status VARCHAR(50) DEFAULT NULL");
        }
    }

    // Second table: leanx_order
    $table_name2 = $wpdb->prefix . 'leanx_order';

    // Check if the second table already exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name2'") != $table_name2) {

        $sql2 = "CREATE TABLE $table_name2 (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            invoice_no varchar(55) NOT NULL,
            status varchar(55) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql2);
    }

    // Get an instance of the WC_Logger class
    $logger = wc_get_logger();
    $context = array( 'source' => 'leanx-db' );

    // Check for errors and log them
    if ($wpdb->last_error) {
        $logger->error("Error creating table(s): " . $wpdb->last_error, ['source' => 'leanx-db']);
    }
}

function drop_leanx_tables() {
    global $wpdb;

    // Get an instance of the WC_Logger class
    $logger = wc_get_logger();
    $context = array( 'source' => 'leanx-db' );

    // Drop the first table
    $table_name1 = $wpdb->prefix . 'transaction_details';
    $wpdb->query("DROP TABLE IF EXISTS $table_name1");

    if ($wpdb->last_error) {
        $logger->error("Error dropping table $table_name1: " . $wpdb->last_error, $context);
    } else {
        $logger->info("Successfully dropped table $table_name1", $context);
    }

    // Drop the second table
    $table_name2 = $wpdb->prefix . 'leanx_order';
    $wpdb->query("DROP TABLE IF EXISTS $table_name2");

    if ($wpdb->last_error) {
        $logger->error("Error dropping table $table_name2: " . $wpdb->last_error, $context);
    } else {
        $logger->info("Successfully dropped table $table_name2", $context);
    }
}


