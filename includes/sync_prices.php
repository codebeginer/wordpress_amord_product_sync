<?php

function amrod_sync_prices($access_token) 
{
    set_time_limit(300);
    $response = wp_remote_get('https://vendorapi.amrod.co.za/api/v1/Prices/', array(
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
            'message' => 'Error fetching product prices.',
        ), 500);
    }

    $response_body = wp_remote_retrieve_body($response);
    $rows = json_decode($response_body);

    if(!is_array($rows) || count($rows) == 0) {
        return new WP_REST_Response(array(
            'error' => false,
            'message' => 'No price data received from api',
        ), 200);
    }

    $sku_lists = [];

    foreach($rows as $row)
    {
        if(!isset($sku_lists[$row->simplecode])) {
            $sku_lists[$row->simplecode] = [];
        }
        $sku_lists[$row->simplecode][] = $row;
    }

    foreach($sku_lists as $key => $row)
    {
        if(count($row) == 1) {
            // simple product
            amrod_update_product_price($key,$row[0]->price);
        } else {
            //variable product
            foreach($row as $variant){
                amrod_update_product_price($variant->fullCode,$variant->price);
            }
        }
        
        
        //TESTING PURPOSE
        //if($sku != 'SB-HP-10-G') {
            //continue;
        //}
    }

    return new WP_REST_Response(array(
        'error' => false,
        'message' => 'Total products updated : '.count($rows),
    ), 200);
}

function amrod_update_product_price($sku,$price) {
    $product_id = wc_get_product_id_by_sku($sku);

    if ($product_id) {
        $product = wc_get_product($product_id);

        if ($product) {
            $product->set_regular_price($price);
            $product->save();
        }
    }
}