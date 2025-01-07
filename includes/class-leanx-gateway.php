<?php
if (!defined('ABSPATH')) {
    exit;
}

class LeanX_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id   = 'leanx';

        $this->init_form_fields();
        $this->init_settings();

        $this->method_title       = __('LeanX', 'leanx');
        $this->method_description = __('LeanX payment gateway for WooCommerce.', 'leanx');
        $this->title              = $this->get_option('title', __('Credit/Debit Card or Online Banking', 'leanx'));
        $this->description        = $this->get_option('description', __('Pay using Credit Card, Debit Card or FPX Online Banking.', 'leanx'));
        $this->order_button_text  = $this->get_option('order_button_text', __('Pay Now', 'leanx'));
        $this->supports           = array('products');

        $leanx_verification = new LeanX_Verification();
        if (!$leanx_verification->is_valid_for_use()) {
            $this->enabled = 'no';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Display multiple icons next to the payment method title.
     *
     * @return string HTML of the icons.
     */
    public function get_icon() {
        $icon_html  = '';

        $icons = array(
            'visa'       => plugins_url( '../assets/visa_symbol.svg', __FILE__ ),
            'mastercard' => plugins_url( '../assets/mc_symbol.svg', __FILE__ ),
            'amex'       => plugins_url( '../assets/fpx_symbol.svg', __FILE__ ),
            // Add more icons here as needed
        );

        foreach ( $icons as $name => $url ) {
            $icon_html .= sprintf(
                '<img src="%s" alt="%s" width="40" class="payment-icon" />',
                esc_url( $url ),
                esc_attr( ucfirst( $name ) )
            );
        }

        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'leanx'),
                'type'    => 'checkbox',
                'label'   => __('Enable LeanX', 'leanx'),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __('Title', 'leanx'),
                'type'        => 'text',
                'description' => __('The title of the payment method displayed to the customers.', 'leanx'),
                'default'     => __('Credit/Debit Card or Online Banking', 'leanx'),
            ),
            'description' => array(
                'title'       => __('Description', 'leanx'),
                'type'        => 'textarea',
                'description' => __('The description of the payment method displayed to the customers.', 'leanx'),
                'default'     => __('Pay using Credit Card, Debit Card or FPX Online Banking.', 'leanx'),
            ),
            'is_sandbox' => array(
                'title'       => __('Sandbox Mode', 'leanx'),
                'type'        => 'checkbox',
                'label'       => __('Enable Sandbox Mode', 'leanx'),
                'default'     => 'no',
                'description' => __('Enable this option to use the sandbox environment for testing.', 'leanx'),
            ),
            'api_key' => array(
                'title'       => __('API key', 'leanx'),
                'type'        => 'text',
                'description' => __('Enter your LeanX API key.', 'leanx'),
                'default'     => '',
            ),
            'collection_id' => array(
                'title'       => __('Collection ID', 'leanx'),
                'type'        => 'text',
                'description' => __('Enter your LeanX Collection ID.', 'leanx'),
                'default'     => '',
            ),
            'instruction' => array(
                'title'       => __('Payment Instructions', 'leanx'),
                'type'        => 'textarea',
                'description' => __('Enter the payment instructions for the customers.', 'leanx'),
                'default'     => '',
            ),
            'bill_invoice_id' => array(
                'title'       => __('Bill Invoice ID', 'leanx'),
                'type'        => 'text',
                'description' => __('Enter your LeanX Bill Invoice ID.', 'leanx'),
                'default'     => '',
            ),
            'hash_key' => array(
                'title'       => __('Hash Key', 'leanx'),
                'type'        => 'text',
                'description' => __('Enter your LeanX Hash Key.', 'leanx'),
                'default'     => '',
            ),
            'order_button_text' => array(
                'title'   => __('Order Button Text', 'leanx'),
                'type'    => 'text',
                'default' => __('Pay Now', 'leanx'),
            ),
        );
    }

    public function process_payment($order_id) {
        // Call your API function
        $api_response = your_api_call_function($order_id);
    
        // Log the API response
        $logger = wc_get_logger();
        $logger->info('API Response for order ID ' . $order_id . ': ' . print_r($api_response, true), array('source' => 'leanx process payment'));
    
        // Check the API response and handle accordingly
        if ($api_response->response_code == 2000 && $api_response->description == "SUCCESS") {
            // Log the successful payment
            $logger->info('Payment success for order ID ' . $order_id . '. Redirecting to: ' . $api_response->data->redirect_url, array('source' => 'leanx process payment'));
    
            // Redirect to the external URL provided by the API response
            return array(
                'result'   => 'success',
                'redirect' => $api_response->data->redirect_url,
            );
        } else {
            // Log the failed payment
            $logger->error('Payment failed for order ID ' . $order_id, array('source' => 'leanx process payment'));
    
            wc_add_notice(__('Payment failed. Please try again. '.$order_id, 'leanx process payment'), 'error');
            return;
        }
    }
    
}

function leanx_add_gateway($methods) {
    $methods[] = 'LeanX_Gateway';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'leanx_add_gateway');