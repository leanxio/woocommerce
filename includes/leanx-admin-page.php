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

    // Set table name
    $table_name = $wpdb->prefix . 'transaction_details';

    // Get selected filter from URL
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';

    // Initialize WHERE conditions array
    $where_conditions = [];
    $filter_condition = '';

    // Process search input
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Add search condition to the query
    if (!empty($search)) {
        $where_conditions[] = $wpdb->prepare("(order_id LIKE %s OR invoice_id LIKE %s)", "%{$search}%", "%{$search}%");
    }

    // Apply status filtering
    $status_filters = [
        "processing" => "invoice_status = 'SUCCESS'",
        "cancelled"  => "invoice_status IN ('FAILED', 'FPX_STOP_CHECK_FROM_PS', 'SENANGPAY_STOP_CHECK_FROM_PS')",
        "pending"    => "invoice_status IS NULL OR invoice_status = '' OR invoice_status IN ('Pending', 'pending')",
        "on-hold"    => "invoice_status NOT IN ('SUCCESS', 'FAILED', 'FPX_STOP_CHECK_FROM_PS', 'SENANGPAY_STOP_CHECK_FROM_PS', 'Pending', 'pending') 
                        AND invoice_status IS NOT NULL AND invoice_status <> ''"
    ];

    if (!empty($status_filters[$filter_status])) {
        $where_conditions[] = $status_filters[$filter_status];
    }

    // Combine WHERE conditions into a single SQL query string
    $where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

    // Pagination settings
    $per_page = 20;
    $current_page = isset($_GET['paged']) && is_numeric($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Fetch filtered results & count filtered rows in one query
    $results = $wpdb->get_results("SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS * FROM $table_name $where_clause ORDER BY order_id DESC LIMIT $per_page OFFSET $offset");
    $total_filtered_rows = (int) $wpdb->get_var("SELECT FOUND_ROWS()");

    // Fetch total rows count (static to avoid redundant queries)
    static $total_rows;
    if (!isset($total_rows)) {
        $total_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    $total_pages = ceil($total_filtered_rows / $per_page);

    // Count transactions by status (using a single optimized query)
    $status_counts = $wpdb->get_row("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN invoice_status = 'SUCCESS' THEN 1 ELSE 0 END) AS processing_count,
            SUM(CASE WHEN invoice_status IN ('FAILED', 'FPX_STOP_CHECK_FROM_PS', 'SENANGPAY_STOP_CHECK_FROM_PS') THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN invoice_status IS NULL OR invoice_status = '' OR invoice_status IN ('Pending', 'pending') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN invoice_status NOT IN ('SUCCESS', 'FAILED', 'FPX_STOP_CHECK_FROM_PS', 'SENANGPAY_STOP_CHECK_FROM_PS', 'Pending', 'pending') 
                    AND invoice_status IS NOT NULL AND invoice_status <> '' THEN 1 ELSE 0 END) AS on_hold_count
        FROM $table_name
    ");

    $processing_count = (int) $status_counts->processing_count;
    $cancelled_count = (int) $status_counts->cancelled_count;
    $pending_count = (int) $status_counts->pending_count;
    $on_hold_count = (int) $status_counts->on_hold_count;
    
    // Load frontend template
    include plugin_dir_path(__FILE__) . 'leanx-admin-transaction-table.php';
}
function load_admin_transaction_assets($hook) {
    // Load only on the transaction page
    if ($hook !== 'woocommerce_page_wp_transaction_details') {
        return;
    }

    // Get plugin directory URL
    $plugin_url = plugin_dir_url(__FILE__);

    // Enqueue CSS
    wp_enqueue_style('admin-transaction-style', $plugin_url . '../assets/css/admin-styles.css', array(), '1.0.0', 'all');

    // Enqueue JavaScript
    wp_enqueue_script('admin-transaction-script', $plugin_url . '../assets/js/admin-scripts.js', array('jquery'), '1.0.0', true);

    // Pass ajaxurl to JavaScript
    wp_localize_script('admin-transaction-script', 'plugin_url', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'load_admin_transaction_assets');