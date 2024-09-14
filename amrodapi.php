<?php
/*
Plugin Name: Amrod API
Description: Amrod custom plugin to create a REST API.
Version: 1.0
Author: Manoj Thakur
*/
// Define the log directory and file path
define('AMROD_LOG_DIR', plugin_dir_path(__FILE__) . 'logs/');

// Create log directory if it does not exist
if (!file_exists(AMROD_LOG_DIR)) {
    mkdir(AMROD_LOG_DIR, 0755, true);
}

require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/categories.php';
require_once plugin_dir_path(__FILE__) . 'includes/products.php';
require_once plugin_dir_path(__FILE__) . 'includes/sync_images.php';
require_once plugin_dir_path(__FILE__) . 'includes/sync_stocks.php';
require_once plugin_dir_path(__FILE__) . 'includes/sync_prices.php';

// Hook into the rest_api_init action to register your custom route
add_action('rest_api_init', function () {

    // update db tables schema first
    amrod_tables_schema();

    register_rest_route('amrod/v1', '/categories/', [
        'methods'  => 'GET',
        'callback' => 'get_category_catlog',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('amrod/v1', '/categories/GetUpdated', [
        'methods'  => 'GET',
        'callback' => 'get_updated_category',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('amrod/v1', '/products/', [
        'methods'  => 'GET',
        'callback' => 'amrod_get_products',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('amrod/v1', '/products/prices', [
        'methods'  => 'GET',
        'callback' => 'get_products_prices',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('amrod/v1', '/images/download', [
        'methods'  => 'GET',
        'callback' => 'get_amrod_images',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('amrod/v1', '/products/stock', [
        'methods'  => 'GET',
        'callback' => 'get_amrod_product_stocks',
        'permission_callback' => '__return_true',
    ]);
});

function get_amrod_images()
{
    return amrod_download_images();
}
function get_amrod_product_stocks()
{
    // get access token
    $access_token = amrod_get_access_token();

    if(!$access_token) {
        $response = [
            'error' => true,
            'message' => 'Unable to retrieve access token.',
        ];
        return new WP_REST_Response($response, 500);
    }
    return amrod_sync_stocks($access_token);
}

function get_category_catlog() {

    // get access token
    $access_token = amrod_get_access_token();

    if(!$access_token) {
        $response = [
            'error' => true,
            'message' => 'Unable to retrieve access token.',
        ];
        return new WP_REST_Response($response, 500);
    }

    $existing_categories = armod_existing_category_names();

    $response = wp_remote_get('https://vendorapi.amrod.co.za/api/v1/Categories', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        amrod_log_error($error_message);
        return new WP_REST_Response(array(
            'error' => true,
            'message' => 'Error fetching categories.',
        ), 500);
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    //process categories
    
    amrod_process_categories($existing_categories,$data);
    $response = [
        'error' => false,
        'data' => $data,
    ];

    return new WP_REST_Response($response, 200);
}
function get_updated_category() {

    // get access token
    $access_token = amrod_get_access_token();

    if(!$access_token) {
        $response = [
            'error' => true,
            'message' => 'Unable to retrieve access token.',
        ];
        return new WP_REST_Response($response, 500);
    }

    $existing_categories = armod_existing_category_names();

    $response = wp_remote_get('https://vendorapi.amrod.co.za/api/v1/Categories/GetUpdated', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        amrod_log_error($error_message);
        return new WP_REST_Response(array(
            'error' => true,
            'message' => 'Error fetching categories.',
        ), 500);
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    //process categories

    amrod_process_categories($existing_categories,$data);
    $response = [
        'error' => false,
        'data' => $data,
    ];

    return new WP_REST_Response($response, 200);
}

function amrod_get_products() 
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $existing_categories = armod_existing_category_names();
    if(count($existing_categories) == 0) {
        $response = [
            'error' => true,
            'message' => 'Categories missing for the product.',
        ];
        return new WP_REST_Response($response, 500);
    }
    set_transient( 'amrod_categories',$existing_categories, 12 * HOUR_IN_SECONDS );

    // get access token
    $access_token = amrod_get_access_token();

    if(!$access_token) {
        $response = [
            'error' => true,
            'message' => 'Unable to retrieve access token.',
        ];
        return new WP_REST_Response($response, 500);
    }

    $amrod_products = isset($_SESSION['amrod_products']) ? $_SESSION['amrod_products']:'';

    if(!$amrod_products)
    {
        $response = wp_remote_get('https://vendorapi.amrod.co.za/api/v1/Products/GetProductsAndBranding', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            amrod_log_error($error_message);
            return new WP_REST_Response(array(
                'error' => true,
                'message' => 'Error fetching products.',
            ), 500);
        }

        $response_body = wp_remote_retrieve_body($response);
        $_SESSION['amrod_products'] = $response_body;
        $all_products = json_decode($response_body, true);
    } else {
        $all_products = json_decode($amrod_products, true);
    }

    if(!$all_products) {
        return new WP_REST_Response(array(
            'error' => true,
            'message' => 'No products received from the api',
        ), 500);
    }

    $product_chunks = divide_into_chunks( $all_products, 500 );
    //amrod_process_products($product_chunks[5],$existing_categories);die;
    //prd($product_chunks[0]);
    foreach ( $product_chunks as $index => $chunk ) {

        // for testing purpose

        $timestamp = time(); // You can set this to any UNIX timestamp
        $hook = 'insert_amrod_products_chunk'; // Your custom hook name
        $group = 'my_custom_group'; // Optional. Group the actions together.

        $chunk_id = uniqid('amrod_chunk_', true);
        set_transient( $chunk_id, $chunk, 12 * HOUR_IN_SECONDS ); // Save for 12 hours
        // Schedule the action
        as_schedule_single_action( $timestamp, $hook, ['chunk_id'=>$chunk_id], $group );
    }

    $response = [
        'error' => false,
        'message' => 'Total '.count($all_products).' products found. Total chunks to process : '.count($product_chunks),
    ];

    return new WP_REST_Response($response, 200);

}
// Handle insertion of a chunk
add_action( 'insert_amrod_products_chunk', 'insert_amrod_products_function', 10, 1 );

function insert_amrod_products_function($chunk_id)
{
    $products = get_transient( $chunk_id );
    $amrod_categories = get_transient( 'amrod_categories' );
    amrod_process_products($products,$amrod_categories);
}

function divide_into_chunks( $products, $chunk_size = 500 ) {
    return array_chunk( $products, $chunk_size );
}

function get_products_prices() {

    // get access token
    $access_token = amrod_get_access_token();

    if(!$access_token) {
        $response = [
            'error' => true,
            'message' => 'Unable to retrieve access token.',
        ];
        return new WP_REST_Response($response, 500);
    }

    return amrod_sync_prices($access_token);

}
