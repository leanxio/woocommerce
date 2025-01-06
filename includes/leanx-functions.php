<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'my_plugin_init');

function your_api_call_function($order_id) {
    global $wpdb; // Add this line to access the $wpdb object

    // Get an instance of the WC_Logger class
    $logger = wc_get_logger();
    $context = array( 'source' => 'leanx-plugin' );

    $order = wc_get_order($order_id);

    // Get the sandbox setting from the admin settings
    $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';

    $leanx_settings = get_option('woocommerce_leanx_settings');

    $random_string = substr(str_shuffle(md5(microtime())), 0, 6);
    $is_sandbox = $leanx_settings['is_sandbox'];
    $api_key = $leanx_settings['api_key'];
    $collection_id = $leanx_settings['collection_id'];
    $bill_invoice_id = $leanx_settings['bill_invoice_id'] . '-' . $order_id;

    // Add Bill Invoice ID as custom meta data to WooCommerce order
    $order->update_meta_data('Invoice', $bill_invoice_id);
    $order->save(); // Important: Save the order to commit the meta data update

    // $base_url = 'http://localhost:8000';
    $base_url = home_url();
    
    // Insert into the leanx_order table
    $table_name = $wpdb->prefix . 'leanx_order';
    $data = array(
        'order_id'   => $order_id,
        'invoice_no' => $bill_invoice_id,
        'status'     => 'PENDING',
    );
    $format = array('%d', '%s', '%s'); // Formats each field (integer, string, string)

    $wpdb->insert($table_name, $data, $format);

    // Check if there was an error and log it
    if ($wpdb->last_error) {
        $logger->error("Database insert error: " . $wpdb->last_error, $context);
    }

    // log to see the value
    $logger = wc_get_logger();
    $logger->info('Is Sandbox: ' . $is_sandbox, array('source' => 'leanx'));
    $logger->info('API Key: ' . $api_key, array('source' => 'leanx'));
    $logger->info('Collection ID: ' . $collection_id, array('source' => 'leanx'));
    $logger->info('Bill Invoice ID: ' . $bill_invoice_id, array('source' => 'leanx'));
    
    // Prepare the data for the API call
    // $client_data = json_encode(array(
    //     "order_id" => $order_id
    // ));

    // Prepare the data for the API call
    $data = array(
        'collection_uuid' => $collection_id,
        'amount'          => $order->get_total(), // Use the order total as the amount
        'redirect_url'    => $base_url . '?order_id='. $order_id . '&invoice_no='. $bill_invoice_id,
        'callback_url'    => $base_url . '/wp-json/leanx/v1/callback',
        'full_name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), // Use the customer's full name
        'email'           => $order->get_billing_email(), // Use the customer's email
        'phone_number'    => $order->get_billing_phone(), // Use the customer's phone number
        'client_data'     => $order_id, // Add the client data as a JSON string
    );

    $logger->info('API Call Data: ' . print_r(json_encode($data), true), array('source' => 'leanx'));

    // Choose the appropriate URL depending on the sandbox setting
    $api_url = $sandbox_enabled ? 'https://stag-api.leanpay.my/api/v1/public-merchant/public/collection-payment-portal?invoice_no=' . $bill_invoice_id : 'https://api.leanx.io/api/v1/public-merchant/public/collection-payment-portal?invoice_no=' . $bill_invoice_id;

    // Make the API call using the wp_remote_post function
    $response = wp_remote_post($api_url, array(
        'method'      => 'POST',
        'timeout'     => 60,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'auth-token' => $api_key // Replace with your actual auth token
        ),
        'body'        => json_encode($data),
        'cookies'     => array(),
    ));

    if (is_wp_error($response)) {
        // Handle the error
        $error_message = $response->get_error_message();
        // Use the WooCommerce logger to log the error
        $logger->error("API Call Error: $error_message", $context);
        return false;
    } else {
        // Log the entire response
        $logger->info("API Response: " . print_r($response, true), $context);

        // Decode the JSON response
        $api_response = json_decode($response['body']);

        // Log the decoded JSON response
        $logger->info("Decoded API Response: " . print_r($api_response, true), $context);

        // Check if the response has the expected format
        if (null !== $api_response->response_code && 2000 == $api_response->response_code && isset($api_response->data->redirect_url)) {
            return $api_response;
        } else {
            return false;
        }
    }
}



function leanx_get_template($template_name) {
    // Check if the template exists in the theme/child-theme
    $template = locate_template('woocommerce/' . $template_name);
    if (!$template) {
        // If not found in the theme, use the template from the plugin
        $template = LEANX_PLUGIN_DIR . 'templates/' . $template_name;
    }
    return $template;
}

function leanx_payment_success_template($template) {
    // Check for the 'order_id' and 'payment_status' query parameters
    if (isset($_GET['order_id']) && isset($_GET['invoice_no'])) {
        $order_id = intval($_GET['order_id']);
        $invoice_no = $_GET['invoice_no'];

        // Check if the payment_status is 'success'
        if ($invoice_no) {
            // Define the path to the leanx-payment-success.php file in your plugin folder
            $leanx_payment_success_template = plugin_dir_path(dirname(__FILE__, 1)) . 'templates/leanx-payment-success.php';

            if (file_exists($leanx_payment_success_template)) {
                return $leanx_payment_success_template;
            }
        }
    }
    return $template;
}
add_filter('template_include', 'leanx_payment_success_template');

add_action('rest_api_init', function () {
  register_rest_route('leanx/v1', '/callback/', array(
    'methods' => WP_REST_Server::CREATABLE, // equivalent to 'POST'
    'callback' => 'leanx_process_callback',
  ));
});

function send_data_to_api($signed) {
    $logger = wc_get_logger();
    $context = ['source' => 'send_data_to_api'];

    $error_response = [
        'success' => false,
        'error' => '',
        'error_message' => '',
        'http_status' => 500, // Default to internal server error.
    ];

    try {
        $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';
        $secret_key = get_option('woocommerce_leanx_settings')['hash_key'];

        $url = $sandbox_enabled ? 'https://stag-api.leanpay.my/api/v1/jwt/decode' : 'https://api.leanx.io/api/v1/jwt/decode';
        $headers = ['accept' => 'application/json', 'Content-Type' => 'application/json'];

        $post_data = ['signed' => $signed, 'secret_key' => $secret_key];
        $logger->info('Sending data: ' . print_r($post_data, true), $context);

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($post_data),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $logger->error('Error occurred: ' . $error_message, $context);
            return array_merge($error_response, [
                'error' => 'wp_remote_post_failed',
                'error_message' => "WP Remote Post Error: $error_message",
                'http_status' => 400,
            ]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $logger->info('Response code: ' . $response_code, $context);
        $logger->info('Response body: ' . $response_body, $context);

        if ($response_code >= 400) {
            return array_merge($error_response, [
                'error' => 'api_error',
                'error_message' => "API Error with response code $response_code",
                'http_status' => $response_code,
            ]);
        }

        return ['success' => true, 'response' => $response_body];

    } catch (Exception $e) {
        $logger->error('Exception occurred: ' . $e->getMessage(), $context);
        return array_merge($error_response, [
            'error' => 'exception',
            'error_message' => 'Exception occurred: ' . $e->getMessage(),
            'http_status' => 500,
        ]);
    }
}





function leanx_process_callback(WP_REST_Request $request) {
    global $wpdb;
    $logger = wc_get_logger();
    $context = array('source' => 'leanx-callback');

    try {
        $raw_data = $request->get_body();
        $logger->info('Raw request body: ' . $raw_data, $context);

        // Retrieve and process the data from the request.
        $data = $request->get_json_params();
        $logger->info('Received callback with data: ' . print_r($data, true), $context);

        $data = isset($data['data']) ? $data['data'] : null;
        if (!$data) {
            throw new Exception("No data found in request.");
        }
        
        $logger->info('Data inside data: ' . print_r($data, true), $context);

        // Send signed token to the API and store the response in $data_decode
        $response = send_data_to_api($data);
        $logger->info('Response Decode : ' . print_r($response, true), $context);

        if (!$response['success']) {
            $logger->error("Failed to send data to API: " . $response['error_message'], $context);
            wp_send_json_error(['message' => $response['error_message']], $response['http_status']);
            die();
        }

        // Decode the response right after verifying it's successful
        $decoded_response = json_decode($response['response'], true);

        // Check if the decoded response is an array and has the required structure
        if (!is_array($decoded_response) || !isset($decoded_response['data'])) {
            throw new Exception("Invalid process data received from API.");
        }

        // Extracting the nested 'data' part where the actual data resides
        $process_data = $decoded_response['data'];

        // Now, if you need to access the 'order_id', you can do so like this
        if (isset($process_data['client_data']['order_id'])) {
            $order_id = $process_data['client_data']['order_id'];
            $logger->info("Order ID: {$order_id}", $context);
        } else {
            throw new Exception("Order ID not found in the API response.");
        }

        $order_id_from_client_data = $process_data['client_data']['order_id']; // Get the order_id from client_data

        // Retrieve new data fields
        $invoice_no = $process_data['invoice_no'];
        $merchant_invoice_no = $process_data['client_data']['merchant_invoice_no'];
        $uuid = $process_data['client_data']['uuid'];
        $invoice_status = $process_data['invoice_status'];
        $amount = $process_data['amount'];

        // Log retrieved data
        $logger->info("Invoice No: $invoice_no, Merchant Invoice No: $merchant_invoice_no, Invoice Status: $invoice_status, Amount: $amount", $context);

        // Check if the invoice_status is "SUCCESS" and update order status accordingly
        $order = wc_get_order($order_id_from_client_data);
        if (!$order) {
            throw new Exception("Order not found.");
        }
        $current_status = $order->get_status();
        if ($invoice_status == 'SUCCESS' && ($current_status == 'pending' || $current_status == 'on-hold')) {
            $order->update_status('processing', "Order set to processing after successful callback. ($merchant_invoice_no)");
        } elseif ($current_status == 'pending' || $current_status == 'on-hold') {
            $order->update_status('cancelled', "Order status changed to cancelled due to failed callback. ($merchant_invoice_no)");
            $logger->warning("Invoice status is not SUCCESS. Current status: $invoice_status", $context);
        }

        // Database insertion
        $table_name = $wpdb->prefix . 'transaction_details';
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id_from_client_data,
                'unique_id' => $merchant_invoice_no,
                'api_key' => $uuid,
                'callback_data' => serialize($data),
                'data' => $amount,
                'invoice_id' => $invoice_no,
            )
        );

        if ($result === false) {
            throw new Exception("Failed to insert data into database.");
        }
        $logger->info("Successfully inserted data into $table_name.", $context);

        // Return the decoded data as a JSON response
        wp_send_json_success($process_data);
    } catch (Exception $e) {
        $logger->error("An error occurred during callback processing: " . $e->getMessage(), $context);
        wp_send_json_error(['message' => 'An error occurred: ' . $e->getMessage()], 500);
    } finally {
        die();
    }
}

