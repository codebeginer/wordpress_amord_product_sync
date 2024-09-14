# wordpress_amord_product_sync
This WordPress plugin defines custom API endpoints for synchronising Amrod products with WooCommerce.

### CRON JOBS

1. Categories
    -   Sync all categories
    -   API Endpoint : wp-json/amrod/v1/categories

    -   Sync updated categories
    -   API Endpoint : wp-json/amrod/v1/categories/update

2. Products
    - Sync all products
    -   API Endpoint : wp-json/amrod/v1/products

2. Product images
    - Sync all product images
    -   API Endpoint : wp-json/amrod/v1/images/download

2. Product price update
    - Sync all product images
    -   API Endpoint : wp-json/amrod/v1/products/prices

2. Product Stock update
    - Sync all product stocks
    -   API Endpoint : wp-json/amrod/v1/products/stock