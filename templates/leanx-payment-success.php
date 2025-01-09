<?php
/**
 * Template Name: LeanX Payment Success
 */

// Start output buffering
ob_start();

get_header();

// Check the query parameters for the order ID and payment status
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$invoice_no = isset($_GET['invoice_no']) ? $_GET['invoice_no'] : 'error';

echo var_dump($order_id, $invoice_no);


if ($order_id && !empty($invoice_no)) {

    $leanx_settings = get_option('woocommerce_leanx_settings');
    $is_sandbox = $leanx_settings['is_sandbox'];
    // Get the sandbox setting from the admin settings
    $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';
    $api_key = $leanx_settings['api_key'];

    // Mark the order as completed and reduce stock levels
    $order = wc_get_order($order_id);

    $url = $sandbox_enabled ? 'https://api.leanx.dev': 'https://api.leanx.io';

    // Check order id with API
    $api_url = $url . '/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=' . $invoice_no; 

    $max_attempts = 3;
    $attempt = 0;
    $successful = false;
    
    while ($attempt < $max_attempts && !$successful) {
        // Replace 'your_auth_token' with your actual auth token
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'auth-token' => $api_key,
            ),
            'timeout' => 20 // Setting timeout to 20 seconds
        ));

        // Log API response
        $logger = wc_get_logger();
        $context = array('source' => 'leanx_order_verification');
        $logger->info('API key: ' . $api_key, $context);
        // Log URL response
        $logger->info('URL: ' . $api_url . ": sandbox_enabled: " . $sandbox_enabled, $context);
        $logger->info('API response: ' . print_r($response, true), $context);
        $response_code = wp_remote_retrieve_response_code($response);
    
        if ($response_code == 200) {
            // If response code is 200, decode the body and proceed with the logic
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
    
            // Check API response content here
            if ($api_response['response_code'] == '2000' && $api_response['data']['transaction_details']['invoice_status'] == 'SUCCESS') {
                // Handle success scenario
                if ($order->get_status() === 'pending') {
                    $order->update_status('processing', 'Payment successful via LeanX.');
                }
                wp_redirect($order->get_checkout_order_received_url());
                $successful = true;
            } elseif ($api_response['data']['transaction_details']['invoice_status'] == 'FAILED') {
                // Handle failure scenario
                if ($order->get_status() === 'pending') {
                    $order->update_status('cancelled', 'Payment failed via LeanX.');
                }
                wc_add_notice(__('Order verification failed. Please contact support.', 'leanx'), 'error');
                wp_redirect(wc_get_cart_url());
                $successful = true;
            } else {
                // Handle unknown status scenario
                if ($order->get_status() === 'pending') {
                    $order->update_status('on-hold', 'Payment status unknown via LeanX. Please manually check the order status.');
                }
                wc_add_notice(__('Payment processing is taking longer than usual. Please wait for an email from our payment provider for status update, or contact support if this issue persists.', 'leanx'), 'error');
                wp_redirect(wc_get_cart_url());
                $successful = true;
            }
        } else {
            // If the response code is not 200, increment the attempt counter and try again
            $attempt++;
            sleep(1); // Sleep for 1 second to give some time before retrying. Adjust as needed.
        }
    }
    
    if (!$successful) {
        // Handle unknown status scenario
        if ($order->get_status() === 'pending') {
            $order->update_status('on-hold', 'Payment status unknown via LeanX. Please manually check the order status.');
        }
        // If after all attempts the API call was not successful, show a generic error message
        wc_add_notice(__('Unable to process payment at this time. Please try again later or contact support.', 'leanx'), 'error');
        wp_redirect(wc_get_cart_url());
    }
    



} else {
    // Log invoice number
    $logger->info('Invoice number: ' . print_r($invoice_no, true), $context);

    // Redirect the user to the cart page with an error message
    wc_add_notice(__('Payment failed. Please try again.', 'leanx'), 'error');
    wp_redirect(wc_get_cart_url());
    exit;
}



get_footer();

// End output buffering and send the buffer
ob_end_flush();


# example of payment success page
// $external_url = "https://yourdomain.com/leanx-payment-success?order_id={$order_id}";