<?php

function amrod_get_total_products() {
    global $wpdb;
    $table_name = 'amrod_product'; // Adjust the table name as needed

    // Perform the SQL query to count total categories
    $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    return $total_products;
}

function amrod_process_products($products,$categories)
{
    set_time_limit(300);
    global $wpdb;
    foreach($products as $key => $product)
    {

        if('KZ-AL-1-F' != $product['fullCode']) {
            //continue;
        }

        $wc_category_ids = [];
        $product_categories = $product['categories'];
        if(is_array($product_categories) && count($product_categories) > 0) {
            
            foreach($product_categories as $product_category)
            {
                $cat = get_term_by('name', $product_category['name'], 'product_cat');
                if($cat) {
                    $wc_category_ids[] = $cat->term_id;
                }
            }
            
        }
        $images = $product['images'];
        $brandingTemplates = $product['brandingTemplates'];

        $branding_guide_jpg = '';
        $img = '';

        if(count($images) > 0)
        {
            foreach($images as $image)
            {
                if($image['isDefault']) {
                    $branding_guide_jpg = $image['urls'][0]['url'];
                } else {
                    $img = $image['urls'][0]['url'];
                }
            }
        }
        
        $category_id = array_search($product_categories[0]['name'], $categories);

        $colorAttributes = [];
        $sizeAttributes = [];
        $cartonsAttributes = [];

        $product_id = '';
        if($product['variants'])
        {
            foreach($product['variants'] as $variant)
            {
                if($variant['codeColourName']) {
                    $colorAttributes[] = $variant['codeColourName'];
                }

                if($variant['codeSizeName']) {
                    $sizeAttributes[] = $variant['codeSizeName'];
                }

                $cartonsAttributes[] = [
                    'product_id' => $product_id,
                    'attribute' => 'Quantity per Carton',
                    'value' => $variant['packagingAndDimension']['piecesPerCarton'],
                ];
                $cartonsAttributes[] = [
                    'product_id' => $product_id,
                    'attribute' => 'Carton Weight (in kg)',
                    'value' => $variant['packagingAndDimension']['cartonWeight'],
                ];
                $cartonsAttributes[] = [
                    'product_id' => $product_id,
                    'attribute' => 'Carton Dimensions (in cm)',
                    'value' => $variant['packagingAndDimension']['cartonSizeDimensionH'].'H x '.$variant['packagingAndDimension']['cartonSizeDimensionL'].'L x '.$variant['packagingAndDimension']['cartonSizeDimensionW'].'W',
                ];
            }
        }
        $colorAttributes = array_unique($colorAttributes);
        $sizeAttributes = array_unique($sizeAttributes);

        $imagesUrl = [];
        if($product['images']) {
            foreach($product['images'] as $imageUrl)
            {
                $imagesUrl[] = [
                    'src' => $imageUrl['urls'][0]['url'],
                    'isDefault' => $imageUrl['isDefault'],
                    'name' => $imageUrl['name']
                ];
            }
        }

        $sku = $product['fullCode'];

        
        $wc_id = wc_get_product_id_by_sku($sku);
        
        /*
        $wp = wc_get_product($wc_id);
        if ($wp) {
            // Delete the product permanently
            //wp_delete_post($wc_id, true); // The second parameter `true` ensures permanent deletion
            //$wc_id = '';
        }
        */
        if(!(int)$wc_id) {
            // add product in woocomerce
            $wc_id = amrod_create_wc_product($product,$colorAttributes,$sizeAttributes,$imagesUrl,$product['variants'],$wc_category_ids);
        } else {
            // update product in woocommerce
            amrod_update_wc_product($wc_id,$product,$colorAttributes,$sizeAttributes,$imagesUrl,$product['variants']);
        }

        // for the moment return from it
        
        $insert = [
            'wc_id'         =>  $wc_id,
            'category_id'   =>  $category_id,
            'sku'           =>  sanitize_text_field($sku),
            'name'          =>  sanitize_text_field($product['productName']),
            'stock'         =>  0,
            'branding_guide_jpg'=>$branding_guide_jpg,
            'branding_guide_pdf'=>$product['fullBrandingGuide'],
            'image'         =>  esc_url($img),
            'imageXL'       =>  esc_url($img),
            'description'   =>  wp_kses_post($product['description'])
        ];

        $amrod_product_branding_options = [];
        
        $existing_product = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM amrod_product WHERE sku = %s", $sku)
        );
        
        if ($existing_product) {
            // Update the existing record
            $wpdb->update(
                'amrod_product',
                [
                    'wc_id'                => 0,
                    'category_id'          => $category_id,
                    'name'                 => $product['productName'],
                    'stock'                => 0,
                    'branding_guide_jpg'   => $branding_guide_jpg,
                    'branding_guide_pdf'   => $product['fullBrandingGuide'],
                    'image'                => $img,
                    'imageXL'              => $img,
                    'description'          => $product['description']
                ],
                ['sku' => $sku]
            );
            $product_id = $existing_product->id;
        } else {
            // Insert new record
            $wpdb->insert('amrod_product', $insert);

            // Check for errors
            if($wpdb->last_error) {
                // Handle the error
                amrod_log_error('Database insert error: ' . $wpdb->last_error);
                $product_id = '';
            } else {
                // Success
                $product_id = $wpdb->insert_id; // Get the inserted row ID
            }
        }
    }
}

function amrod_create_wc_product($api_product,$colorAttributes,$sizeAttributes,$product_images,$product_variations,$categories) 
{

    // if variant is single that means its simple product
    if(count($product_variations) <= 1) {
        $product = new WC_Product_Simple();
        $product->set_manage_stock( true );
    } else {
        $product = new WC_Product_Variable();
    }
    
    $product->set_name($api_product['productName']);
    $product->set_description($api_product['description']);
    $product->set_sku($api_product['fullCode']);
    $product->set_status('publish'); // Set the product status to publish

    $attributes = [];
    if($colorAttributes) 
    {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name('color') );
        $attribute->set_name('pa_color');
        $attribute->set_options(array_map('sanitize_text_field', $colorAttributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes['pa_color'] = $attribute;
    }
   
    if($sizeAttributes)
    {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name('pa_size') );
        $attribute->set_name('pa_size');
        $attribute->set_options(array_map('sanitize_text_field', $sizeAttributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes['pa_size'] = $attribute;
    }
    
    if(count($attributes) > 0)
    {
        $product->set_attributes($attributes);
    }

    $product_id = $product->save();

    // Set categories
    if (!empty($categories)) {
        wp_set_object_terms($product_id, $categories, 'product_cat');
    }

    foreach ($product_images as $index => $image_url) {
        log_images([
            'name' => $image_url['name'],
            'url' => $image_url['src'],
            'image_type' => 'product',
            'is_default' => $image_url['isDefault'] ? 1:0,
            'variation_id' => 0,
            'product_id' => $product_id,
        ]);
    }

    if(count($product_variations) > 1) 
    {
        foreach ($product_variations as $variation_data) 
        {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_sku($variation_data['fullCode']);
            $variation->set_weight($variation_data['productDimension']['weight']);
            $variation->set_length($variation_data['productDimension']['length']);
            $variation->set_width($variation_data['productDimension']['width']);

            $variation->set_stock_status('instock');

            $variation->set_attributes([
                'pa_color' => strtolower($variation_data['codeColourName']),
                'pa_size' => strtolower($variation_data['codeSizeName'])
            ]);

            // Save the variation
            $variation->save();

            if(is_array($api_product['colourImages']) && count($api_product['colourImages']) > 0)
            {
                foreach($api_product['colourImages'] as $image)
                {
                    if($variation_data['codeColour'] == $image['code']) {

                        $colourImages = $image['images'];
                        if(is_array($colourImages) && count($colourImages) > 0) {
                            foreach($colourImages as $colourImage)
                            {
                                if($colourImage['isDefault'])
                                {
                                    $url = $colourImage['urls'][0]['url'];

                                    log_images([
                                        'name' => $colourImage['name'],
                                        'url' => $url,
                                        'image_type' => 'variation',
                                        'is_default' => $colourImage['isDefault'] ? 1:0,
                                        'variation_id' => $variation->get_id(),
                                        'product_id' => $product_id,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
        }
        WC_Product_Variable::sync($product_id);
    }

    return $product_id;

}

function amrod_update_wc_product($product_id,$api_product,$colorAttributes,$sizeAttributes,$imagesUrls,$variants) 
{
    $product = wc_get_product($product_id);

    foreach ($imagesUrls as $index => $image_url) {
        $image_id = image_exists_in_media_library($image_url['name']);
               
        if(!$image_id) {
            log_images([
                'name' => $image_url['name'],
                'url' => $image_url['src'],
                'image_type' => 'product',
                'is_default' => $image_url['isDefault'] ? 1:0,
                'variation_id' => 0,
                'product_id' => $product_id,
            ]);
        }
    }

    $product->set_name($api_product['productName']);
    $product->set_description($api_product['description']);
    
    $product->set_status('publish'); // Set the product status to publish

    $variations = $product->get_children();

    // delete product attributes
    // Get the current attributes
    $attributes = $product->get_attributes();

    // Remove the attribute
    $product->set_attributes([]);
    $product->save();//die;

    $attributes = [];
    if($colorAttributes) 
    {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name('color') );
        $attribute->set_name('pa_color');
        $attribute->set_options(array_map('sanitize_text_field', $colorAttributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes['pa_color'] = $attribute;
    }

    if($sizeAttributes)
    {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id( wc_attribute_taxonomy_id_by_name('pa_size') );
        $attribute->set_name('pa_size');
        $attribute->set_options(array_map('sanitize_text_field', $sizeAttributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes['pa_size'] = $attribute;
    }

    if(count($attributes) > 0)
    {
        $existing_attributes = $product->get_attributes();
        
        // Merge new attributes with existing ones
        $attributes = array_merge( $existing_attributes, $attributes );
        
        $product->set_attributes( $attributes );
        $product->save();
    }
   
    if($variations) 
    {
        foreach ($variations as $variation_id) 
        {
            $variation = new WC_Product_Variation($variation_id);

            foreach($variants as $variant)
            {
                $sku = $variation->get_sku();
                if($sku == $variant['fullCode'])
                {
                    $variation->set_weight($variant['productDimension']['weight']);
                    $variation->set_length($variant['productDimension']['length']);
                    $variation->set_width($variant['productDimension']['width']);

                    $variation->set_stock_quantity(10);
                    $variation->set_manage_stock(true);

                    if(is_array($api_product['colourImages']) && count($api_product['colourImages']) > 0)
                    {
                        foreach($api_product['colourImages'] as $image)
                        {
                            if($variant['codeColour'] == $image['code']) {

                                $colourImages = $image['images'];
                                if(is_array($colourImages) && count($colourImages) > 0) {
                                    foreach($colourImages as $colourImage)
                                    {
                                        if($colourImage['isDefault'])
                                        {
                                            $url = $colourImage['urls'][0]['url'];
                                            $image_id = image_exists_in_media_library($colourImage['name']);
                                            if(!$image_id) {
                                                log_images([
                                                    'name' => $colourImage['name'],
                                                    'url' => $url,
                                                    'image_type' => 'variation',
                                                    'is_default' => $colourImage['isDefault'] ? 1:0,
                                                    'variation_id' => $variation->get_id(),
                                                    'product_id' => $product_id,
                                                ]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $variation->set_attributes([
                        'pa_color' => strtolower($variant['codeColourName']),
                        'pa_size' => strtolower($variant['codeSizeName'])
                    ]);
                }
            }

            $variation->save(); // Save the variation
        }
    }

    $product->save();
}

function image_exists_in_media_library($file_name) {
    global $wpdb;

    // Query to check if the file exists in the media library
    $query = $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
        AND guid LIKE %s",
        '%' . $wpdb->esc_like($file_name) . '%'
    );

    $image_id = $wpdb->get_var($query);

    return $image_id ? $image_id : false;
}

function log_images($data) 
{
    global $wpdb;

    $format = array(
        '%s', // name as string
        '%s', // url as string
        '%s', // image type as string
        '%d', // is default as string
        '%d', // variation id as integer
        '%d', // product id as integer
        '%s', // image_url as string
    );

    $wpdb->insert('amrod_sync_images', $data, $format);
}