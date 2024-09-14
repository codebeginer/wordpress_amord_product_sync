<?php

function amrod_sync_stocks($access_token) 
{
    set_time_limit(300);
    $response = wp_remote_get('https://vendorapi.amrod.co.za/api/v1/Stock/', array(
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
            'message' => 'Error fetching product stocks.',
        ), 500);
    }

    $response_body = wp_remote_retrieve_body($response);
    $rows = json_decode($response_body, true);

    if(!is_array($rows) || count($rows) == 0) {
        return new WP_REST_Response(array(
            'error' => false,
            'message' => 'No stock data received from api',
        ), 200);
    }

    foreach($rows as $row)
    {
        $sku = $row['fullCode'];
        /*
        TESTING PURPOSE
        if($sku != 'SB-HP-10-G') {
            continue;
        }
        */
        $product_id = wc_get_product_id_by_sku($sku);
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            $product->set_manage_stock( true );
            $product->set_stock_quantity($row['stock']);
            $product->set_stock_status(($row['stock'] > 0 ? "instock" : "outofstock"));
            $product->save();
        }
    }

    return new WP_REST_Response(array(
        'error' => false,
        'message' => 'Total products updated : '.count($rows),
    ), 200);
}