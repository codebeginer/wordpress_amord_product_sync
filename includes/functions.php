<?php 

$base_url = 'https://vendorapi.amrod.co.za/';

function pr($data) {
    echo "<pre>";print_r($data);echo "</pre>";
}

function prd($data) {
    echo "<pre>";print_r($data);echo "</pre>";die;
}

function amrod_log_error($message) {
    // Ensure log directory and file exist
    if (!file_exists(AMROD_LOG_DIR)) {
        mkdir(AMROD_LOG_DIR, 0755, true);
    }
    
    // Create a timestamp
    $timestamp = date('Y-m-d H:i:s');
    
    // Format the log message
    $log_message = "[{$timestamp}] ERROR: {$message}\n";
    
    // Append the log message to the log file
    file_put_contents(AMROD_LOG_DIR . 'error-log-'.date('Y-m-d').'.txt', $log_message, FILE_APPEND);
}

function amrod_tables_schema() {
    global $wpdb;

    $sql_file = plugin_dir_path(__FILE__) . 'schema.sql';

    // Read the SQL file contents
    $sql = file_get_contents($sql_file);

    // Split SQL file into individual queries
    $queries = explode(';', $sql);

    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $wpdb->query($query);
        }
    }
}

function amrod_get_access_token() {

    // Start the session if it's not already started
    if (!session_id()) {
        session_start();
    }

    // Check if we already have a valid token in the session
    if (isset($_SESSION['amrod_access_token']) && isset($_SESSION['amrod_token_expires_in']) && $_SESSION['amrod_token_expires_in'] > time()) {
        // Token is still valid
        return $_SESSION['amrod_access_token'];
    }

    $url = 'https://identity.amrod.co.za/VendorLogin';

    // Define the request body as an array
    $body = array(
        'UserName' => 'briersmarx@gmail.com',
        'Password' => 'Brandly2024@@@@',
        'CustomerCode' => '023027',
    );
    
    // Perform the POST request
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'body'      => json_encode($body),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        amrod_log_error($error_message);
        return;
    } else {
        // Get the response body
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['token']) && isset($data['expiry'])) {
            // Store the access token and expiry time in the session
            $_SESSION['amrod_access_token'] = $data['token'];
            $_SESSION['amrod_token_expires_in'] = time() + $data['expiry']; // Assuming 'expires_in' is in seconds
            return $data['token'];
        } else {
            // Handle the case where 'access_token' or 'expires_in' is not in the response
            amrod_log_error('Access token or expiry time not found in the response.');
            return;
        }
    }
}

function amrod_get_product_by_sku($sku) {
    // Ensure WooCommerce is active
    if (function_exists('wc_get_product_id_by_sku')) {
        // Get the product ID by SKU
        $product_id = wc_get_product_id_by_sku($sku);

        // Check if a product with the given SKU exists
        if (!$product_id) {
            return null; // SKU not found
        }

        // Get the product object
        $product = wc_get_product($product_id);

        return $product;
    } else {
        return null; // WooCommerce not active
    }
}

function amrod_get_amount_with_margin($amount,$margin){ 
	return $amount * (1 + $margin / 100.0);
}