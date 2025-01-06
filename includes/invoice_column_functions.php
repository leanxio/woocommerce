<?php

function add_invoice_column_header($columns) {
    if (array_key_exists('Invoice', $columns)) {
        // Column already exists, so return the original columns
        return $columns;
    }

    $new_columns = array();
    
    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        
        if ('order_status' === $column_name) {
            $new_columns['Invoice'] = __('Invoice', 'my-textdomain');
        }
    }
    
    return $new_columns;
}

function add_invoice_column_content($column) {
    global $the_order; // Use the global $the_order object provided by WooCommerce

    if ('Invoice' === $column && is_a($the_order, 'WC_Order')) {
        $order_id = $the_order->get_id();
        $invoice_data = $the_order->get_meta('Invoice', true);
        
        if (!empty($invoice_data)) {
            // Display the invoice data
            echo '<span style="color: blue;">' . esc_html($invoice_data) . '</span>';
        } else {
            echo '–';
        }
    }
}







