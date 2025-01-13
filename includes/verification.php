<?php

class LeanX_Verification {

    private $auth_token;
    private $collection_id;
    private $bill_invoice_id;
    private $supported_currencies = array('MYR');
    private $log;

    public function __construct() {
        $this->leanx_settings = get_option('woocommerce_leanx_settings');
        $this->auth_token = $this->leanx_settings['auth_token'];
        $this->collection_id = $this->leanx_settings['collection_id'];
        $this->bill_invoice_id = $this->leanx_settings['bill_invoice_id'];
        $this->hash_key = $this->leanx_settings['hash_key'];
        $this->sandbox_enabled = $this->leanx_settings['is_sandbox'];
    
        $this->log = new WC_Logger();
    
        $context = array('source' => 'LeanX_Verification_constant');
    
        $this->log->info('woocommerce_leanx_settings: ' . print_r($this->leanx_settings, true), $context);
        $this->log->info('leanx_auth_token: ' . $this->auth_token, $context);
        $this->log->info('leanx_collection_id: ' . $this->collection_id, $context);
        $this->log->info('bill_invoice_id: ' . $this->bill_invoice_id, $context);
        $this->log->info('hash_key: ' . $this->hash_key, $context);
        $this->log->info('sandbox_enabled: ' . $this->sandbox_enabled, $context);
    }
    

    private function validate_keys_presence() {
        $valid = true;
    
        if (empty($this->auth_token)) {
            remove_action('admin_notices', array($this, 'auth_token_missing_message'));
            add_action('admin_notices', array($this, 'auth_token_missing_message'));
            $valid = false;
        }
    
        if (empty($this->collection_id)) {
            remove_action('admin_notices', array($this, 'collection_id_missing_message'));
            add_action('admin_notices', array($this, 'collection_id_missing_message'));
            $valid = false;
        }
    
        if (empty($this->bill_invoice_id)) {
            remove_action('admin_notices', array($this, 'bill_invoice_id_missing_message'));
            add_action('admin_notices', array($this, 'bill_invoice_id_missing_message'));
            $valid = false;
        }

        if (empty($this->hash_key)) {
            remove_action('admin_notices', array($this, 'hash_key_missing_message'));
            add_action('admin_notices', array($this, 'hash_key_missing_message'));
            $valid = false;
        }
    
        return $valid;
    }
    

    public function check_keys_verification() {
        // Define headers
        $headers = array(
            'Content-Type' => 'application/json',
            'auth-token' => $this->auth_token, // adjust this to your actual authorization method
        );
    
        $valid = true;
    
        // Get WooCommerce logger
        $logger = wc_get_logger();
        $context = array( 'source' => 'leanx_verification' );

        $sandbox_enabled = get_option('woocommerce_leanx_settings')['is_sandbox'] === 'yes';

        $url = $sandbox_enabled ? 'https://api.leanx.dev': 'https://api.leanx.io';

         // Log url response
         $logger->info('url: ' . print_r($url . ": " . $this->sandbox_enabled, true), $context);       
    
        // Check Auth Token
        $auth_token_response = $this->call_api($url . '/api/v1/public-merchant/validate', array(
            'auth_token' => $this->auth_token
        ), $headers);
    
        // Log Auth Token response
        $logger->info('Auth Token response: ' . print_r($auth_token_response, true), $context);
    
        if ($auth_token_response['body']['response_code'] != 2000 || $auth_token_response['body']['description'] != 'SUCCESS') {
            $logger->error('Invalid Auth Token.', $context);
            add_action('admin_notices', array($this, 'auth_token_invalid_message'));
            $valid = false;
        }
    
        // Check Collection ID
        $collection_id_response = $this->call_api($url . '/api/v1/public-merchant/validate-collection-id', array(
            'uuid' => $this->collection_id
        ), $headers);
    
        // Log Collection ID response
        $logger->info('Collection ID response: ' . print_r($collection_id_response, true), $context);
    
        if ($collection_id_response['body']['response_code'] != 2000 || $collection_id_response['body']['description'] != 'SUCCESS') {
            $logger->error('Invalid collection ID.', $context);
            add_action('admin_notices', array($this, 'collection_id_invalid_message'));
            $valid = false;
        }
    
        return $valid;
    }
    
    

    public function is_valid_for_use() {
        // Check if the Save button was clicked.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save'])) {
            return true; // Exit early if not saving the settings.
        }
        if (!$this->validate_keys_presence()) {
            return false;
        }

        if (!$this->check_keys_verification()) {
            return false;
        }

        if (!in_array(get_woocommerce_currency(), $this->supported_currencies)) {
            $this->unsupported_currency_message();
            return false;
        }

        return true;
    }

    public function auth_token_missing_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Auth Token is missing.', 'leanx') . '</p></div>';
    }

    public function collection_id_missing_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Collection ID is missing.', 'leanx') . '</p></div>';
    }

    public function bill_invoice_id_missing_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Bill Invoice Format is missing.', 'leanx') . '</p></div>';
    }

    public function hash_key_missing_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Hash key is missing.', 'leanx') . '</p></div>';
    }

    public function unsupported_currency_message() {
        echo '<div class="error"><p>' . __('LeanX Error: The current currency is not supported.', 'leanx') . '</p></div>';
    }

    public function auth_token_invalid_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Auth Token is invalid.', 'leanx') . '</p></div>';
    }

    public function collection_id_invalid_message() {
        echo '<div class="error"><p>' . __('LeanX Error: Collection ID is invalid.', 'leanx') . '</p></div>';
    }

    private function call_api($url, $params, $headers) {
        // This is just an example, adjust this to your actual API call
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($params),
            'timeout' => 60
        ));
    
        // Log params
        $logger = wc_get_logger();
        $context = array('source' => 'LeanX_Verification_call_api');
        $logger->info('API url: ' . print_r($url, true), $context);
        $logger->info('API params: ' . print_r($params, true), $context);
    
        // Log response
        $logger->info('API response: ' . print_r($response, true), $context);
    
        return array(
            'status' => wp_remote_retrieve_response_code($response),
            'body' => json_decode(wp_remote_retrieve_body($response), true)
        );
    }
    
    

    // Error logging function
    private function log_error($message) {
        $this->log->add('leanx-verification', $message);
    }
}

