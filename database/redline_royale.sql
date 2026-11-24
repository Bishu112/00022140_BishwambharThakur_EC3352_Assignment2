-- Redline Royale Assessment 2 database (MySQL 8+ / MariaDB 10.4+)
-- Structure matches the Assessment 2 presentation slides 8-13 (relational schema, CREATE and ALTER statements).

CREATE DATABASE IF NOT EXISTS redline_royale_assignment2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE redline_royale_assignment2;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS reviews, transactions, order_status_history, product_views, order_items, vendor_profiles, orders, products, categories, users;
SET FOREIGN_KEY_CHECKS=1;

-- ---------------------------------------------------------------- CREATE TABLES
CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','vendor','admin')
    NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL
    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vendor_profiles (
  profile_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  business_name VARCHAR(160) NOT NULL,
  owner_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(500) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE products (
  product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  image_url VARCHAR(255),
  status ENUM('active','inactive')
    NOT NULL DEFAULT 'active',
  views INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (vendor_id) REFERENCES users(user_id),
  FOREIGN KEY (category_id)
    REFERENCES categories(category_id)
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  fulfillment ENUM('delivery','collection') NOT NULL,
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address VARCHAR(255),
  payment_method ENUM('cash_on_delivery',
    'cash_at_collection') NOT NULL,
  total DECIMAL(10,2) NOT NULL CHECK (total >= 0),
  status VARCHAR(40) NOT NULL DEFAULT 'placed',
  created_at TIMESTAMP NOT NULL
    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  vendor_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  FOREIGN KEY (vendor_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE transactions (
  transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  payment_method ENUM('cash_on_delivery','cash_at_collection') NOT NULL,
  amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
  payment_status ENUM('pending','paid','void') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
  history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  changed_by INT UNSIGNED NULL,
  status ENUM('placed','vendor accepted','ready for delivery','shipped','delivered','discarded') NOT NULL,
  note VARCHAR(500) NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order_history_order_date(order_id,changed_at),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reviews (
  review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment VARCHAR(1000) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  FOREIGN KEY (customer_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE product_views (
  view_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  session_id VARCHAR(128) NULL,
  viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product_views_date(product_id,viewed_at),
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- ALTER STATEMENTS (presentation slide 13)
ALTER TABLE products
  ADD INDEX idx_products_status (status);

ALTER TABLE orders
  ADD INDEX idx_orders_customer (customer_id);

ALTER TABLE order_items
  ADD CONSTRAINT fk_oi_order
  FOREIGN KEY (order_id) REFERENCES orders(order_id)
  ON DELETE CASCADE;

ALTER TABLE reviews
  ADD UNIQUE KEY uq_review (product_id, customer_id);

-- Extra ALTERs: product options and timestamps used by the store pages and reports
ALTER TABLE products
  ADD COLUMN sizes VARCHAR(120) NOT NULL DEFAULT 'S,M,L,XL',
  ADD COLUMN colours VARCHAR(160) NOT NULL DEFAULT 'Natural,Olive,Black',
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Demo password for each account: Demo123!
INSERT INTO users (user_id,full_name,email,password_hash,role) VALUES
(1,'Redline Demo Vendor','vendor@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','vendor'),
(2,'Redline Demo Customer','customer@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(3,'Redline Demo Admin','admin@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','admin');
INSERT INTO vendor_profiles(user_id,business_name,owner_name,phone,address) VALUES
(1,'Redline Royale Demo','Redline Demo Vendor','9800000000','Kathmandu, Nepal');
INSERT INTO categories(name) VALUES ('Kurtas'),('Shirts'),('Jackets'),('Waistcoats'),('Accessories'),('Shoes');
INSERT INTO products(vendor_id,category_id,title,description,price,stock,image_url,status,views) VALUES
(1,1,'Heritage Cotton Kurta','A breathable cotton kurta with a clean, classic cut.',3200,12,'images/products/kurta.png','active',44),
(1,3,'Wool Blend Heritage Jacket','A structured everyday jacket from a local maker.',7800,5,'images/products/heritage-jacket.png','active',31),
(1,2,'Linen Summer Shirt','A lightweight linen shirt for warm days, with a relaxed fit.',2800,15,'images/products/linen-shirt.png','active',22),
(1,4,'Embroidered Waistcoat','A structured waistcoat with traditional embroidery detail.',4500,8,'images/products/waistcoat.png','active',15),
(1,5,'Dhaka Topi','A traditional Nepali woven cap in colourful Dhaka pattern.',1200,25,'images/products/dhaka-topi.png','active',28),
(1,2,'Nepali Dhaka Shirt','A shirt crafted from authentic Nepali Dhaka fabric.',3500,10,'images/products/nepali-dhaka-shirt.png','active',19),
(1,3,'Silk Blend Blazer','A refined silk-blend blazer for formal occasions.',9500,4,'images/products/silk-blazer.png','active',7),
(1,5,'Handcrafted Leather Belt','A hand-stitched leather belt with brass buckle.',1800,15,'images/products/belt.png','active',10),
(1,6,'Leather Oxford Shoes','Handcrafted brown leather Oxford shoes with a classic silhouette.',6800,6,'images/products/leather-shoes.png','active',16),
(1,6,'Canvas Sneakers','Comfortable everyday canvas sneakers in a clean, minimal style.',3200,14,'images/products/sneakers.png','active',24);

