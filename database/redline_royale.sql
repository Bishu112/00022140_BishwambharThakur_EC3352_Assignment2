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
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  postal_code VARCHAR(20) NULL,
  preferred_size VARCHAR(40) NULL,
  preferred_colour VARCHAR(80) NULL,
  updates_opt_in TINYINT(1) NOT NULL DEFAULT 0,
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
  -- product_id becomes NULL when a catalogue product is deleted; product_title keeps the historical name.
  product_id INT UNSIGNED NULL,
  product_title VARCHAR(180) NULL,
  vendor_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
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
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
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
(3,'Redline Demo Admin','admin@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','admin'),
(5,'Sita Gurung','sita.gurung@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(6,'Ram Shrestha','ram.shrestha@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(7,'Anisha Thapa','anisha.thapa@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(8,'Bikash Maharjan','bikash.maharjan@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(9,'Kabita Rai','kabita.rai@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(10,'Sujan Karki','sujan.karki@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(11,'Pooja Acharya','pooja.acharya@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(12,'Amit Tamang','amit.tamang@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(13,'Nisha Gurung','nisha.gurung@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(14,'Ramesh Thapa','ramesh.thapa@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(15,'Sarita Magar','sarita.magar@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(16,'Dipesh KC','dipesh.kc@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(17,'Anjali Shrestha','anjali.shrestha@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(18,'Prakash Gurung','prakash.gurung@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(19,'Mina Tamang','mina.tamang@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(20,'Hari Bohora','hari.bohora@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(21,'Sushila Chhetri','sushila.chhetri@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(22,'Bimal Rai','bimal.rai@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(23,'Rekha Yadav','rekha.yadav@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(24,'Gopal Shrestha','gopal.shrestha@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(25,'Sunita Maharjan','sunita.maharjan@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(26,'Narendra Thapa','narendra.thapa@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(27,'Ishwari Rai','ishwari.rai@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(28,'Kiran Gurung','kiran.gurung@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(29,'Mamata Limbu','mamata.limbu@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(30,'Bhola Shrestha','bhola.shrestha@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(31,'Ayushma Karki','ayushma.karki@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(32,'Dinesh Rai','dinesh.rai@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(33,'Lalita Gurung','lalita.gurung@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer'),
(34,'Santosh Tamang','santosh.tamang@redlineroyale.test','$2y$10$Z5uxUrs.iviOhkAgDRe7B.BGAYp1Qjvjm494izkC5nywR2QaRg6p.','customer');
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

-- Demo customer reviews: three different Nepali reviewers per product
INSERT INTO reviews (review_id,product_id,customer_id,rating,comment,created_at) VALUES
(1,1,5,5,'The cotton is breathable and the cut is clean. Fit was true to size and the parcel arrived on time.','2026-10-10 09:00:00'),
(2,1,6,4,'The cotton is breathable and the cut is clean. Good value for the price and easy to wear.','2026-10-11 09:17:00'),
(3,1,7,5,'The cotton is breathable and the cut is clean. I would order again from Redline Royale.','2026-10-12 09:34:00'),
(4,2,8,4,'The wool feels warm without being heavy. Fit was true to size and the parcel arrived on time.','2026-10-12 09:00:00'),
(5,2,9,5,'The wool feels warm without being heavy. Good value for the price and easy to wear.','2026-10-13 09:17:00'),
(6,2,10,4,'The wool feels warm without being heavy. I would order again from Redline Royale.','2026-10-14 09:34:00'),
(7,3,11,5,'Light linen that suits warm weather. Fit was true to size and the parcel arrived on time.','2026-10-14 09:00:00'),
(8,3,12,4,'Light linen that suits warm weather. Good value for the price and easy to wear.','2026-10-15 09:17:00'),
(9,3,13,5,'Light linen that suits warm weather. I would order again from Redline Royale.','2026-10-16 09:34:00'),
(10,4,14,4,'The embroidery detail is neat and durable. Fit was true to size and the parcel arrived on time.','2026-10-16 09:00:00'),
(11,4,15,5,'The embroidery detail is neat and durable. Good value for the price and easy to wear.','2026-10-17 09:17:00'),
(12,4,16,4,'The embroidery detail is neat and durable. I would order again from Redline Royale.','2026-10-18 09:34:00'),
(13,5,17,5,'Bright Dhaka pattern with a tidy finish. Fit was true to size and the parcel arrived on time.','2026-10-18 09:00:00'),
(14,5,18,4,'Bright Dhaka pattern with a tidy finish. Good value for the price and easy to wear.','2026-10-19 09:17:00'),
(15,5,19,5,'Bright Dhaka pattern with a tidy finish. I would order again from Redline Royale.','2026-10-20 09:34:00'),
(16,6,20,4,'Authentic fabric and good stitching. Fit was true to size and the parcel arrived on time.','2026-10-20 09:00:00'),
(17,6,21,5,'Authentic fabric and good stitching. Good value for the price and easy to wear.','2026-10-21 09:17:00'),
(18,6,22,4,'Authentic fabric and good stitching. I would order again from Redline Royale.','2026-10-22 09:34:00'),
(19,7,23,5,'Subtle sheen and a smart formal shape. Fit was true to size and the parcel arrived on time.','2026-10-22 09:00:00'),
(20,7,24,4,'Subtle sheen and a smart formal shape. Good value for the price and easy to wear.','2026-10-23 09:17:00'),
(21,7,25,5,'Subtle sheen and a smart formal shape. I would order again from Redline Royale.','2026-10-24 09:34:00'),
(22,8,26,4,'Solid stitching and a brass buckle. Fit was true to size and the parcel arrived on time.','2026-10-24 09:00:00'),
(23,8,27,5,'Solid stitching and a brass buckle. Good value for the price and easy to wear.','2026-10-25 09:17:00'),
(24,8,28,4,'Solid stitching and a brass buckle. I would order again from Redline Royale.','2026-10-26 09:34:00'),
(25,9,29,5,'Polished leather and a comfortable shape. Fit was true to size and the parcel arrived on time.','2026-10-26 09:00:00'),
(26,9,30,4,'Polished leather and a comfortable shape. Good value for the price and easy to wear.','2026-10-27 09:17:00'),
(27,9,31,5,'Polished leather and a comfortable shape. I would order again from Redline Royale.','2026-10-28 09:34:00'),
(28,10,32,4,'Light canvas with a clean everyday look. Fit was true to size and the parcel arrived on time.','2026-10-28 09:00:00'),
(29,10,33,5,'Light canvas with a clean everyday look. Good value for the price and easy to wear.','2026-10-29 09:17:00'),
(30,10,34,4,'Light canvas with a clean everyday look. I would order again from Redline Royale.','2026-10-30 09:34:00');
