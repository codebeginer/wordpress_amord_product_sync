CREATE TABLE IF NOT EXISTS amrod_category(
  id BIGINT NOT NULL PRIMARY KEY, 
  parent_id BIGINT DEFAULT NULL, 
  wc_id BIGINT NOT NULL, 
  name VARCHAR(300) NOT NULL, 
  updated_at BIGINT DEFAULT NULL, 
  KEY (wc_id), 
  KEY(parent_id), 
  KEY (updated_at)
);
CREATE TABLE IF NOT EXISTS amrod_product(
  id BIGINT NOT NULL PRIMARY KEY, 
  wc_id BIGINT NOT NULL, 
  category_id BIGINT DEFAULT NULL, 
  sku VARCHAR(300) NOT NULL, 
  name VARCHAR(300) NOT NULL, 
  stock INT DEFAULT NULL, 
  branding_guide_jpg VARCHAR(500) DEFAULT NULL, 
  branding_guide_pdf VARCHAR(500) DEFAULT NULL, 
  image VARCHAR(500) DEFAULT NULL, 
  imageXL VARCHAR(500) DEFAULT NULL, 
  description TEXT DEFAULT NULL, 
  deleted BOOLEAN DEFAULT NULL, 
  updated_at BIGINT DEFAULT NULL, 
  original_price DECIMAL(18, 2) DEFAULT NULL, 
  KEY (wc_id), 
  KEY (sku), 
  KEY (deleted), 
  KEY (updated_at), 
  KEY(category_id)
) DEFAULT COLLATE utf8mb4_unicode_ci;
ALTER TABLE `amrod_product` CHANGE `id` `id` BIGINT NOT NULL AUTO_INCREMENT;
CREATE TABLE IF NOT EXISTS amrod_product_images(
  id BIGINT NOT NULL, 
  code VARCHAR(50) NOT NULL, 
  url VARCHAR(500) NOT NULL, 
  PRIMARY KEY(id, code), 
  KEY(url)
);
CREATE TABLE IF NOT EXISTS amrod_product_cartons(
  product_id BIGINT NOT NULL, 
  attribute VARCHAR(100) NOT NULL, 
  value VARCHAR(100) NOT NULL, 
  PRIMARY KEY(product_id, attribute)
);
CREATE TABLE IF NOT EXISTS amrod_product_branding_options(
  product_id BIGINT NOT NULL, 
  position VARCHAR(100) NOT NULL, 
  `option` VARCHAR(100) NOT NULL, 
  PRIMARY KEY(product_id, position, `option`)
);
CREATE TABLE IF NOT EXISTS amrod_product_variation(
  id BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT, 
  product_id BIGINT NOT NULL, 
  wc_id BIGINT NOT NULL, 
  sku VARCHAR(300) NOT NULL, 
  color_code VARCHAR(20) DEFAULT NULL, 
  color_name VARCHAR(50) DEFAULT NULL, 
  size VARCHAR(50) DEFAULT NULL, 
  stock INT DEFAULT NULL, 
  image VARCHAR(500) DEFAULT NULL, 
  imageXL VARCHAR(500) DEFAULT NULL, 
  deleted BOOLEAN DEFAULT NULL, 
  KEY (product_id), 
  KEY (wc_id), 
  KEY (deleted), 
  KEY (sku)
);
CREATE TABLE IF NOT EXISTS amrod_product_size_attribute(
  product_id BIGINT NOT NULL, 
  size VARCHAR(50) NOT NULL, 
  attribute VARCHAR(100) NOT NULL, 
  value VARCHAR(100) NOT NULL, 
  PRIMARY KEY(product_id, size, attribute)
);
CREATE TABLE IF NOT EXISTS earning_margins(
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(50) NOT NULL, 
  percentage INT DEFAULT 15,
  UNIQUE (source)
);
INSERT INTO earning_margins (source) 
VALUES ('amrod')
ON DUPLICATE KEY UPDATE source = source;
INSERT INTO earning_margins (source) 
VALUES ('kevro')
ON DUPLICATE KEY UPDATE source = source;
CREATE TABLE IF NOT EXISTS `amrod_sync_images` (
  `id` bigint(20) NOT NULL,
  `name` text NOT NULL,
  `url` text,
  `image_type` enum('product','variation') NOT NULL DEFAULT 'product',
  `is_default` tinyint(4) NOT NULL DEFAULT '0',
  `variation_id` int(11) NOT NULL DEFAULT '0',
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
