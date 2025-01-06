<?php
// Hook for adding admin menus
add_action('admin_menu', 'add_wp_transaction_details_page');

// Action function for the above hook
function add_wp_transaction_details_page() {
    // Add a new submenu under WooCommerce
    add_submenu_page('woocommerce', 'LeanX Transaction', 'LeanX Transaction', 'manage_woocommerce', 'wp_transaction_details', 'display_wp_transaction_details_page');
}

function display_wp_transaction_details_page() {
    global $wpdb;

    // Set your table name
    $table_name = $wpdb->prefix . 'transaction_details';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $where_clause = $search ? " WHERE order_id LIKE '%$search%' OR unique_id LIKE '%$search%'" : '';
    $per_page = 20;
    $current_page = isset($_GET['paged']) && is_numeric($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $per_page;
    $results = $wpdb->get_results("SELECT * FROM $table_name" . $where_clause . " LIMIT $per_page OFFSET $offset");
    $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name" . $where_clause);
    $total_pages = ceil($total_rows / $per_page);

    // First Box: Table and Pagination
    echo '<div class="wrap" style="text-align: center; margin-bottom: 2rem; padding: 20px; border: 1px solid #ccc;">';
    echo '<h2>LeanX Transaction - Table</h2>';

    // Display search form
    echo '<form method="post" class="form-inline mb-4" style="text-align: center;">';
    echo '<input type="text" name="search" value="' . $search . '" class="form-control mr-2" style="margin: 1rem;">';
    echo '<input type="submit" value="Search" class="btn btn-primary">';
    echo '</form>';

    echo '<table class="table table-striped" style="margin: 1rem auto; width: 80%;">';
    echo '<thead><tr>';
    echo '<th>Order ID</th>';
    echo '<th>Unique ID</th>';
    echo '<th>API Key</th>';
    echo '<th>Data</th>';
    echo '<th>Invoice ID</th>';
    echo '</tr></thead>';

    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . $row->order_id . '</td>';
        echo '<td>' . $row->unique_id . '</td>';
        echo '<td>' . $row->api_key . '</td>';
        echo '<td>' . $row->data . '</td>';
        echo '<td>' . $row->invoice_id . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // Pagination
    echo '<nav aria-label="Page navigation" style="text-align: center;">';
    echo '<ul class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        echo '<li class="page-item' . ($i == $current_page ? ' active' : '') . '">';
        echo '<a class="page-link" href="?page=wp_transaction_details&paged=' . $i . '">' . $i . '</a>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</nav>';
    echo '</div>';

    // Second Box: API Request
    echo '<div class="wrap" style="text-align: center; margin-bottom: 2rem; padding: 20px; border: 1px solid #ccc;">';
    echo '<h2 style="font-size: 20px;">LeanX Transaction - API Request</h2>';

    echo '<form method="post" style="margin: 0 auto;">';
    echo '<label for="order_id" style="font-size: 20px;">Order ID: </label>';
    echo '<input type="text" id="order_id" name="order_id" required style="font-size: 20px; width: 300px;">';
    echo '<input type="submit" value="Check" style="font-size: 20px;">';
    echo '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'leanx-manual-check' ); // Custom context for filtering logs
    
        $order_id = sanitize_text_field($_POST['order_id']);
    
        $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';
        $leanx_settings = get_option('woocommerce_leanx_settings');
        $api_key = $leanx_settings['api_key'];
    
        $table_name = $wpdb->prefix . 'leanx_order';
        $invoice_no = $wpdb->get_var($wpdb->prepare("SELECT invoice_no FROM $table_name WHERE order_id = %s", $order_id));
    
        // Check if invoice_no is empty
        if (empty($invoice_no)) {
            // Get the WooCommerce order
            $order = wc_get_order($order_id);
            // Get bill_invoice_id from the order meta
            $invoice_no = $order->get_meta('Invoice');
        }
    
        if ($invoice_no) {
            $api_url = $sandbox_enabled ? "https://stag-api.leanpay.my/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=$invoice_no" : "https://api.leanx.io/api/v1/public-merchant/public/manual-checking-transaction?invoice_no=$invoice_no";
    
            $attempt = 0;
    
            while ($attempt < 3) { // 1 original attempt + 2 more attempts
                $response = wp_remote_post($api_url, array(
                    'headers' => array(
                        'accept' => 'application/json',
                        'auth-token' => $api_key,
                    ),
                    'timeout' => 20 // Setting timeout to 20 seconds
                ));
                $logger->info("API Key : $api_key", $context);

                $http_code = wp_remote_retrieve_response_code($response);

                $logger->info("status code : $http_code", $context);
    
                if ($http_code == 200) {
                    $body = wp_remote_retrieve_body($response);
                    $logger->info("response : $body", $context);
                    $data = json_decode($body, true);
    
                    $logger->info("API request successful for order ID: $order_id, Invoice No: $invoice_no", $context);

                    // Check the response_code and description in the response
                    if ($data['response_code'] == 2000 && $data['description'] == 'SUCCESS') {
                        $logger->info("API request successful with SUCCESS status for order ID: $order_id, Invoice No: $invoice_no", $context);
                        // Making the response look like a receipt
                        echo '<h3>Transaction Receipt</h3>';
                        echo '<div style="border: 1px solid black; padding: 20px; width: 300px; margin: auto;">';
                        echo '<p><strong>Order Id:</strong> ' . $order_id . '</p>';
                        echo '<p><strong>Invoice No:</strong> ' . $data['data']['transaction_details']['invoice_no'] . '</p>';
                        echo '<p><strong>Status:</strong> ' . $data['data']['transaction_details']['invoice_status'] . '</p>';
                        echo '<p><strong>Amount:</strong> ' . $data['data']['transaction_details']['amount'] . '</p>';
                        echo '<p><strong>Bank:</strong> ' . $data['data']['transaction_details']['bank_provider'] . '</p>';
                        echo '<p><strong>Company:</strong> ' . $data['data']['company_name'] . '</p>';
                        echo '</div>';
        
                        // Update order status to 'processing' if status is 'SUCCESS' or 'cancelled' if status is 'FAILED'
                        if ($data['data']['transaction_details']['invoice_status'] === 'SUCCESS') {
                            $order = wc_get_order($order_id);
                            $order->update_status('processing', 'Order status changed to processing.');
                        } elseif ($data['data']['transaction_details']['invoice_status'] === 'FAILED') {
                            $order = wc_get_order($order_id);
                            $order->update_status('cancelled', 'Order status changed to cancelled due to failed transaction.');
                        }elseif ($data['data']['transaction_details']['invoice_status'] === 'FPX_STOP_CHECK_FROM_PS') {
                            $order = wc_get_order($order_id);
                            $order->update_status('cancelled', 'Order status changed to cancelled due to failed transaction.');
                        }elseif ($data['data']['transaction_details']['invoice_status'] === 'SENANGPAY_STOP_CHECK_FROM_PS') {
                            $order = wc_get_order($order_id);
                            $order->update_status('cancelled', 'Order status changed to cancelled due to failed transaction.');
                        }
        
                        break;
                    } elseif ($data['response_code'] == 3614 || $data['description'] == 'FAILED') {
                        $logger->error("API request returned with FAILED status for order ID: $order_id. Reason: {$data['breakdown_errors']}", $context);
                        
                        // Displaying the error in a receipt-like format
                        echo '<h3>Transaction Details</h3>';
                        echo '<div style="border: 1px solid black; padding: 20px; width: 300px; margin: auto;">';
                        echo '<p><strong>Order Id:</strong> ' . $order_id . '</p>';
                        // Since invoice number might not be available, you decide how to handle it. Showing a placeholder or a message.
                        echo '<p><strong>Invoice No:</strong> Not Available</p>';
                        echo '<p><strong>Status:</strong> FAILED</p>';
                        echo '<p style="color: red;"><strong>Reason:</strong> ' . $data['breakdown_errors'] . '</p>';
                        echo '</div>';
                        
                        $order = wc_get_order($order_id);
                        $order->update_status('cancelled', 'Order status changed to cancelled due to failed transaction.');
                        // Here, you might also handle the failure, e.g., updating the order status or notifying someone.
                        break;
                    }
                } else {
                    $attempt++;
                    $logger->warning("API request attempt $attempt failed for order ID: $order_id, HTTP code: $http_code", $context);
                    if ($attempt >= 3) {
                        echo '<p style="color: red;">Failed to get a 200 response from the API after 3 attempts.</p>';
                        $logger->error("Failed to get a 200 response from the API after 3 attempts for order ID: $order_id", $context);
                    }
                }
            }
        } else {
            echo '<p style="color: red;">Invalid Order ID</p>';
            $logger->error("Invalid Order ID: $order_id", $context);
        }
    }

    echo '</div>';
}



