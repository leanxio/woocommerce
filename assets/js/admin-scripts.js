jQuery(document).ready(function($) {
    $('.check-transaction').click(function() {
        var orderId = $(this).data('order-id');
        var modal = $('#transaction-modal');
        var receiptContent = $('#receipt-content');
        var logoUrl = $('#leanx-logo').data('logo');

        // Show loading state in modal
        receiptContent.html('<p>Loading transaction details...</p>');
        modal.show();

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'check_transaction_status',
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var receiptHtml = `
                        <img src="` + logoUrl + `" class="leanx-logo" alt="LeanX Logo">
                        <h3>Transaction Receipt</h3>
                        <div class="receipt-box">
                            <p><strong>Order Id:</strong> ${data.order_id}</p>
                            <p><strong>Invoice No:</strong> ${data.invoice_no}</p>
                            <p><strong>Status:</strong> ${data.invoice_status}</p>
                            <p><strong>Amount:</strong> ${data.amount}</p>
                            <p><strong>Bank:</strong> ${data.bank}</p>
                            <p><strong>Company:</strong> ${data.company}</p>
                        </div>
                    `;
                    receiptContent.html(receiptHtml);

                    // Update only the checked row
                    updateTransactionRow(orderId);
                } else {
                    receiptContent.html('<p style="color: red;">Failed to fetch transaction details.</p>');
                }
            },
            error: function() {
                receiptContent.html('<p style="color: red;">Error fetching transaction.</p>');
            }
        });
    });

    // Close modal when clicking the close button
    $('.close').click(function() {
        $('#transaction-modal').hide();
    });

    // Close modal when clicking outside the content
    $(window).click(function(event) {
        if ($(event.target).is('#transaction-modal')) {
            $('#transaction-modal').hide();
        }
    });

    function updateTransactionRow(orderId) {
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'update_transaction_row',
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    console.log("✅ Updated row for Order ID:", orderId);
                    // Replace only the specific row with new updated HTML
                    $("#order-" + orderId).replaceWith(response.data.html);
                } else {
                    console.error("Failed to update row:", response.message);
                }
            },
            error: function() {
                console.error("AJAX error while updating transaction row.");
            }
        });
    }

    // Handle Sorting Click
    $(document).on("click", ".sort-column", function (e) {
        e.preventDefault();
        
        let column = $(this).data("column");
        let order = $(this).data("order");

        // Update URL and Table
        updateSorting(column, order);
    });

    function getUrlParameter(name) {
        let urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name) || "";
    };

    function updateSorting(column, order) {
        // Get current search query and selected filter status
        let searchQuery = $("#transaction-search-input").val();
        let filterStatus = getUrlParameter("filter_status"); // Get filter status from URL
    
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: "refresh_transaction_table",
                sort_by: column,
                sort_order: order,
                search: searchQuery,
                filter_status: filterStatus
            },
            success: function (response) {
                if (response.success) {
                    $("#transaction-table-body").html(response.data.html);
    
                    // Update sorting indicators
                    $(".sort-column").data("order", order === "asc" ? "desc" : "asc");
                } else {
                    console.error("Failed to sort table.");
                }
            },
            error: function () {
                console.error("AJAX error while sorting transactions.");
            }
        });
    }
});