<?php 

function amrod_get_total_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'amrod_category'; // Adjust the table name as needed

    // Perform the SQL query to count total categories
    $total_categories = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    return $total_categories;
}


function armod_existing_category_names()
{
    global $wpdb;
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id,name FROM amrod_category",
            // Add any necessary parameters here
        )
    );

    // Check if results are returned
    if (empty($results)) {
        return array(); // Return an empty array if no categories are found
    }

    // Create an array of category names
    $category_names = array();
    foreach ($results as $row) {
        $category_names[$row->id] = $row->name;
    }

    return $category_names;
}

function amrod_process_categories($existing_categories,$categories,$parent_id = 0,$wc_id = 0) 
{
    global $wpdb;
    foreach($categories as $category) 
    {
        $categoryName = trim($category['categoryName']);
        
        
        $cat = get_term_by('name', $categoryName, 'product_cat');

        if(!$cat) {
            $wc_cat_id = amrod_create_woocommerce_category($categoryName,$wc_id);
        } else {
            $wc_cat_id = $cat->term_id;
        }

        // check if category exists in woocomerce or not
        // Prepare the query
        $amrod_category = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM amrod_category WHERE `name` = %s",
                $categoryName
            )
        );

        if(!$amrod_category) {
            $query = $wpdb->prepare(
                "INSERT INTO amrod_category (name, parent_id, wc_id, updated_at)
                VALUES (%s, %d, %d, UNIX_TIMESTAMP())",
                $categoryName,
                $parent_id,
                $wc_cat_id
            );
        
            // Execute the query
            $inserted = $wpdb->query($query);
            if ($inserted) {
                $category_id = $wpdb->insert_id;
            }
        } else {
            $category_id = $amrod_category->id;
        }

        if(is_array($category['children']) && count($category['children']) > 0)
        {
            amrod_process_categories($existing_categories,$category['children'],$category_id,$wc_cat_id);
        }
    }
}

function amrod_create_woocommerce_category($categoryName,$parent_id = 0) 
{

    // Create the new product category
    $new_category = wp_insert_term(
        $categoryName,
        'product_cat', 
        array(
            'description' => '',
            'parent'      => $parent_id
        )
    );

    if (is_wp_error($new_category)) {
        amrod_log_error($new_category->get_error_message());
        return;
    }

    return $new_category['term_id'];
}
