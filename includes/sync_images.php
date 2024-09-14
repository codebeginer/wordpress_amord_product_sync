<?php 

function read_last_image_processed() {
    // Define the file path (adjust the path based on your specific setup)
    $file_path = AMROD_LOG_DIR . 'last_image_processed.txt'; // Or adjust to your plugin/theme folder

    // Check if the file exists
    if (!file_exists($file_path)) {
        return;
    }

    // Use the WP_Filesystem API
    global $wp_filesystem;
    
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
    }

    // Read the file contents
    $file_contents = $wp_filesystem->get_contents($file_path);
    
    if ($file_contents === false) {
        return;
    }

    return $file_contents;
}

function amrod_download_images()
{
    global $wpdb;
    set_time_limit(300);
    if (!function_exists('media_sideload_image')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    $last_id = read_last_image_processed();

    if($last_id) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM amrod_sync_images WHERE id > $last_id ORDER BY id asc limit 100"
            )
        );
    } else {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM amrod_sync_images ORDER BY id asc limit 100"
            )
        );
    }
    
    file_put_contents(AMROD_LOG_DIR . 'last_image_processed.txt', $rows[count($rows)-1]->id);
    
    if(!$rows) {
        return new WP_REST_Response(array(
            'error' => false,
            'message' => 'No images are left to be processed',
        ), 200);
    }

    $delete_ids = [];
    foreach($rows as $row)
    {
        switch($row->image_type)
        {
            case 'variation':
                    $variation = new WC_Product_Variation($row->variation_id);
                    $image_id = media_sideload_image($row->url, $row->product_id, $row->name, 'id');
                    if (!is_wp_error($image_id)) {
                        $variation->set_image_id($image_id);
                    }
                    $variation->save();
                    $delete_ids[] = $row->id;
                    
                    $wpdb->delete(
                        'amrod_sync_images',
                        [ 'id' => $row->id ], // Where clause
                        [ '%d' ] // Format (d for integer)
                    );
                    
                break;
            case 'product':
                    if($row->variation_id == 0) {
                        $product = new WC_Product_Variable($row->product_id);
                    } else {
                        $product = new WC_Product_Simple($row->product_id);
                    }
                    
                    if($row->is_default) {
                        // set product image
                        $image_id = media_sideload_image($row->url, $row->product_id, $row->name, 'id');
                        $product->set_image_id($image_id);
                    } else {
                        // set image in gallery
                        $image_id = media_sideload_image($row->url, $row->product_id, $row->name, 'id');
                        
                        $product->set_gallery_image_ids([$image_id]);
                    }
                    $product->save();
                    
                    $delete_ids[] = $row->id;
                    
                    $wpdb->delete(
                        'amrod_sync_images',
                        [ 'id' => $row->id ], // Where clause
                        [ '%d' ] // Format (d for integer)
                    );
                    
                break;
            default:
                break;
        }
    }

    if(count($delete_ids) > 0) {
        //$ids = implode(',', array_map('intval', $delete_ids));
        //$wpdb->query("DELETE FROM amrod_sync_images WHERE id IN ($ids)");
    }
    return new WP_REST_Response(array(
        'error' => false,
        'message' => 'Total images : '.count($rows),
    ), 200);
}