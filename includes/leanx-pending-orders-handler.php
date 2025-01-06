<?php

// Function to check for pending orders when the admin visits the WooCommerce > Orders page
function leanx_check_and_cancel_pending_orders_on_admin_page() {
    global $typenow, $wpdb;

    // Initialize the WooCommerce logger
    $logger = wc_get_logger();
    $context = array( 'source' => 'leanx-check-and-cancel' );

    if ('shop_order' === $typenow) {
        $logger->info("Checking and cancelling pending orders.", $context);

        $current_time = current_time('timestamp');

        $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';
        $leanx_settings = get_option('woocommerce_leanx_settings');
        $api_key = $leanx_settings['api_key'];

        $args = array(
            'status' => 'pending',
            'limit' => -1,
        );
        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            $order_time = $order->get_date_created()->getTimestamp();
            $time_difference = $current_time - $order_time;
            $order_id = $order->get_id();

            $logger->info("Checking order with ID {$order_id}. Time difference: {$time_difference} seconds.", $context);

            if ($time_difference > 300) {
                $table_name = $wpdb->prefix . 'leanx_order';
                $invoice_no = $wpdb->get_var($wpdb->prepare("SELECT invoice_no FROM $table_name WHERE order_id = %s", $order_id));

                // Check if invoice_no is empty
                if (empty($invoice_no)) {
                    // Get the WooCommerce order
                    $order = wc_get_order($order_id);
                    // Get bill_invoice_id from the order meta
                    $invoice_no = $order->get_meta('Invoice');
                }

                $api_url = $sandbox_enabled ? "https://stag-api.leanpay.my/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=$invoice_no" : "https://api.leanx.io/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=$invoice_no";

                if ($invoice_no) {
                    $logger->info("Invoice number for order {$order_id}: {$invoice_no}", $context);

                    $response = wp_remote_post($api_url, array(
                        'headers' => array(
                            'accept' => 'application/json',
                            'auth-token' => $api_key,
                        ),
                        'timeout' => 10 // Setting timeout to 10 seconds
                    ));

                    if (is_wp_error($response)) {
                        $logger->error("API request error for order {$order_id}: " . $response->get_error_message(), $context);
                    } else {
                        $body = wp_remote_retrieve_body($response);
                        $data = json_decode($body, true);

                        if (isset($data['data']['transaction_details']['invoice_status'])) {
                            $invoice_status = $data['data']['transaction_details']['invoice_status'];
                            
                            if ($invoice_status == 'SUCCESS') {
                                $order->update_status('processing', "Order status changed to processing after successful transaction verification on store. ( $invoice_status )");
                                $logger->info("Order {$order_id} status updated to processing.", $context);
                            } elseif ($invoice_status == 'FAILED') {
                                $order->update_status('cancelled', "Order status changed to cancelled due to failed transaction verification on store. ( $invoice_status )");
                                $logger->warning("Order {$order_id} status updated to cancelled.", $context);
                            } elseif ($invoice_status == 'SENANGPAY_STOP_CHECK_FROM_PS') {
                                $order->update_status('cancelled', "Order status changed to cancelled due to failed transaction verification on store. ( $invoice_status )");
                                $logger->warning("Order {$order_id} status updated to cancelled.", $context);
                            } elseif ($invoice_status == 'FPX_STOP_CHECK_FROM_PS') {
                                $order->update_status('cancelled', "Order status changed to cancelled due to failed transaction verification on store. ( $invoice_status )");
                                $logger->warning("Order {$order_id} status updated to cancelled.", $context);
                            } else {
                                // Log or handle other statuses
                                $logger->notice("Order {$order_id} has an unhandled invoice status: {$invoice_status}.", $context);
                            }
                        } else {
                            // Log or handle case where invoice_status is not set
                            $logger->error("Order {$order_id} has no invoice_status available.", $context);
                        }
                        
                    }
                }
            }

            if ($time_difference > 43200) { // Only proceed if time difference is greater than 12 hours
                $table_name = $wpdb->prefix . 'leanx_order';
                $invoice_no = $wpdb->get_var($wpdb->prepare("SELECT invoice_no FROM $table_name WHERE order_id = %s", $order_id));
        
                // Check if invoice_no is empty
                if (empty($invoice_no)) {
                    // Get the WooCommerce order
                    $order = wc_get_order($order_id);
                    // Get bill_invoice_id from the order meta
                    $invoice_no = $order->get_meta('Invoice');
                }
        
                // Proceed only if invoice_no is valid
                if (!empty($invoice_no)) {
                    $logger->info("Valid invoice number for order {$order_id}: {$invoice_no}", $context);
        
                    // API call and response handling logic here
                    // Removed for brevity since it's unchanged
        
                    // Based on your requirement, you may want to adjust or remove this part of the code.
                    // If the invoice_no is valid and time difference is greater than 3600, you might want
                    // to update the order status to 'cancelled' directly here.
                    $order->update_status('cancelled', 'Order cancelled due to exceeding pending time and having a valid invoice number.');
                    $logger->info("Order {$order_id} status updated to cancelled due to valid invoice number and time difference.", $context);
                }
            }
        }
    }
}

add_action('load-edit.php', 'leanx_check_and_cancel_pending_orders_on_admin_page');