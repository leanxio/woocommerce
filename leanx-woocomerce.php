<?php
/**
 * Plugin Name: LeanX for WooCommerce
 * Plugin URI: https://docs.leanx.io/api-docs/suite-plugin/woocommerce
 * Description: A LeanX payment gateway for WooCommerce.
 * Author: Nazirul Ifwat Abd Aziz, Adam Salleh
 * Author URI: https://www.leanx.io/
 * Version: 1.1.0
 * Requires PHP: 7.0
 * Requires at least: 4.6
 * Text Domain: leanx
 * Requires Plugins: woocommerce
 * License: GPLv3
 * Domain Path: /languages/
 * WC requires at least: 3.0
 * WC tested up to: 6.7
 */

 define('LEANX_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'leanx_init', 11);

function leanx_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    load_plugin_textdomain('leanx', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    require_once plugin_dir_path(__FILE__) . 'includes/class-leanx-gateway.php';
    require_once plugin_dir_path(__FILE__) . 'includes/leanx-functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/leanx-table.php';
    require_once plugin_dir_path(__FILE__) . 'includes/verification.php';
    require_once plugin_dir_path(__FILE__) . 'includes/leanx-admin-page.php';
    require_once plugin_dir_path(__FILE__) . 'includes/leanx-pending-orders-handler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/invoice_column_functions.php';

    // Add hooks for invoice column
    add_filter('manage_edit-shop_order_columns', 'add_invoice_column_header');
    add_action('manage_shop_order_posts_custom_column', 'add_invoice_column_content');

}

add_action('before_woocommerce_init', function(){

    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );

    }

});

// Add settings link to the plugin
function leanx_add_settings_link( $links ) {
    // Create settings link pointing to the desired URL
    $settings_link = '<a href="' . get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout&section=leanx') . '">' . __( 'Settings', 'leanx' ) . '</a>';
    // Add the settings link to the beginning of plugin
    array_unshift( $links, $settings_link );
    // Return the links array
    return $links;
}
// Get the plugin basename
$plugin = plugin_basename( __FILE__ );
// Add the settings link to the plugin action links
add_filter( "plugin_action_links_$plugin", 'leanx_add_settings_link' );

// Function for creating table
function create_leanx_table_on_activation() {
    // Ensure function is available
    if (!function_exists('create_leanx_table')) {
        require_once plugin_dir_path(__FILE__) . 'includes/leanx-table.php';
    }

    // Call the function to create the table
    create_leanx_table();
}

// Function for dropping table on deactivation
function drop_leanx_tables_on_deactivation() {
    // Ensure function is available
    if (!function_exists('drop_leanx_tables')) {
        require_once plugin_dir_path(__FILE__) . 'includes/leanx-table.php';
    }

    // Call the function to drop the table
    drop_leanx_tables();
}

// Register activation hook
register_activation_hook( __FILE__, 'create_leanx_table_on_activation' );

// Register deactivation hook
register_deactivation_hook(__FILE__, 'drop_leanx_tables_on_deactivation');