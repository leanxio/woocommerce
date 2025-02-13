<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <!-- Admin Header -->
    <div class="admin-header">
        <h1>LeanX Transaction - Table</h1>
    </div>

    <!-- Logo -->
    <div id="leanx-logo" data-logo="<?php echo plugin_dir_url(__FILE__) . '../assets/leanx-icon.png'; ?>" style="display: none;"></div>
    
    <!-- Order Tabs -->
    <div class="order-tabs-wrapper">
        <div class="order-tabs">
            <a href="?page=wp_transaction_details" class="<?php echo empty($filter_status) ? 'active' : ''; ?>">
                All <span class="tab-count">(<?php echo esc_html($total_rows); ?>)</span>
            </a> |
            <a href="?page=wp_transaction_details&filter_status=pending" class="<?php echo ($filter_status === 'pending') ? 'active' : ''; ?>">
                Pending <span class="tab-count">(<?php echo esc_html($pending_count); ?>)</span>
            </a> |
            <a href="?page=wp_transaction_details&filter_status=processing" class="<?php echo ($filter_status === 'processing') ? 'active' : ''; ?>">
                Processing <span class="tab-count">(<?php echo esc_html($processing_count); ?>)</span>
            </a> |
            <a href="?page=wp_transaction_details&filter_status=on-hold" class="<?php echo ($filter_status === 'on-hold') ? 'active' : ''; ?>">
                On Hold <span class="tab-count">(<?php echo esc_html($on_hold_count); ?>)</span>
            </a> |
            <a href="?page=wp_transaction_details&filter_status=cancelled" class="<?php echo ($filter_status === 'cancelled') ? 'active' : ''; ?>">
                Cancelled <span class="tab-count">(<?php echo esc_html($cancelled_count); ?>)</span>
            </a>
        </div>
        <!-- Search Box -->
        <form method="post" action="">
            <input type="hidden" name="page" value="wp_transaction_details"> <!-- Ensures page stays the same -->
            <p class="search-box">
                <label class="screen-reader-text" for="transaction-search-input"></label>
                <input type="search" id="transaction-search-input" name="search" placeholder="Search Order ID" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="search-submit" class="button" value="Search orders">
            </p>
        </form>
    </div>
    
    <!-- Pagination Controls (Top) -->
    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html($total_rows); ?> items</span>
            <span class="pagination-links">
                <!-- First & Previous Buttons -->
                <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="?page=wp_transaction_details&paged=1&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">«</span></a>
                    <a class="prev-page button" href="?page=wp_transaction_details&paged=<?php echo ($current_page - 1); ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">‹</span></a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                <?php endif; ?>

                <!-- Page Input -->
                <span class="paging-input">
                    <label for="current-page-selector" class="screen-reader-text">Current Page</label>
                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="1" aria-describedby="table-paging">
                    <span class="tablenav-paging-text"> of <span class="total-pages"><?php echo esc_html($total_pages); ?></span></span>
                </span>

                <!-- Next & Last Buttons -->
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="?page=wp_transaction_details&paged=<?php echo ($current_page + 1); ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">›</span></a>
                    <a class="last-page button" href="?page=wp_transaction_details&paged=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">»</span></a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Transaction Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column sortable <?php echo ($sort_by === 'order_id' && $sort_order === 'asc') ? 'asc' : 'desc'; ?>">
                    <a href="#" class="sort-column" data-column="order_id" data-order="<?php echo ($sort_order === 'asc') ? 'desc' : 'asc'; ?>">
                        <span>Order ID</span>
                        <span class="sorting-indicators">
                            <span class="sorting-indicator asc" aria-hidden="true"></span>
                            <span class="sorting-indicator desc" aria-hidden="true"></span>
                        </span>
                    </a>
                </th>
                <th class="column-invoice-id">Invoice ID</th>
                <th class="column-auth-token">Auth Token</th>
                <th class="column-amount">Amount</th>
                <th class="column-status">Status</th>
                <th class="column-actions text-right">Action</th>
            </tr>
        </thead>
        <tbody id="transaction-table-body">
            <?php foreach ($results as $row): ?>
                <tr id="order-<?php echo esc_attr($row->order_id); ?>">
                    <td class="column-order-id" data-label="Order ID">
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $row->order_id . '&action=edit')); ?>">
                            <?php echo esc_html($row->order_id); ?>
                        </a>
                    </td>
                    <td class="column-invoice-id" data-label="Invoice ID"><?php echo esc_html($row->invoice_id); ?></td>
                    <td class="column-auth-token" data-label="Auth Token"><?php echo esc_html($row->auth_token); ?></td>
                    <td class="column-amount" data-label="Amount"><?php echo esc_html(number_format($row->data, 2)); ?></td>
                    <td class="column-status" data-label="Status">
                        <?php
                        // Default values
                        $status_class = 'status-pending';
                        $status_text = 'Pending';

                        // Update status based on `invoice_status`
                        if ($row->invoice_status === 'SUCCESS') {
                            $status_class = 'status-processing';
                            $status_text = 'Processing';
                        } elseif (in_array($row->invoice_status, ['FAILED', 'FPX_STOP_CHECK_FROM_PS', 'SENANGPAY_STOP_CHECK_FROM_PS'])) {
                            $status_class = 'status-cancelled';
                            $status_text = 'Cancelled';
                        } elseif (in_array($row->invoice_status, ['Pending', 'pending'])) {
                            $status_class = 'status-pending';
                            $status_text = 'Pending';
                        } elseif (!empty($row->invoice_status)) {
                            $status_class = 'status-on-hold';
                            $status_text = 'On-Hold';
                        }
                        ?>
                        <span class="status-label <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_text); ?>
                        </span>
                    </td>
                    <td class="column-actions text-right" data-label="Actions">
                        <button class="button check-transaction" data-order-id="<?php echo esc_attr($row->order_id); ?>">
                            Check
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination Controls (Bottom) -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html($total_rows); ?> items</span>
            <span class="pagination-links">
                <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="?page=wp_transaction_details&paged=1&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">«</span></a>
                    <a class="prev-page button" href="?page=wp_transaction_details&paged=<?php echo ($current_page - 1); ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">‹</span></a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                <?php endif; ?>

                <span class="paging-input">
                    <label for="current-page-selector-bottom" class="screen-reader-text">Current Page</label>
                    <input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="1" aria-describedby="table-paging">
                    <span class="tablenav-paging-text"> of <span class="total-pages"><?php echo esc_html($total_pages); ?></span></span>
                </span>

                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="?page=wp_transaction_details&paged=<?php echo ($current_page + 1); ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">›</span></a>
                    <a class="last-page button" href="?page=wp_transaction_details&paged=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><span aria-hidden="true">»</span></a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- Transaction Receipt Modal -->
<div id="transaction-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="receipt-content" class="modal-body"></div>
    </div>
</div>