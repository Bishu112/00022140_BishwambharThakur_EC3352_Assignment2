# Redline Royale — Database Schema Documentation

**Project:** EC3352 Dynamic Web Programming — Assessment 2 (Database Interaction)
**Application:** Redline Royale — Fashion & Apparel Store (B2C)
**Database:** `redline_royale_assignment2`
**DBMS:** MySQL 8+ / MariaDB (InnoDB, utf8mb4)

---

## Overview

The database stores all persistent data for the fashion & apparel store web application. It supports three user roles (customer, vendor, admin), vendor product management, customer ordering with delivery/collection, order status tracking, reviews, product view analytics, and date-range reports for vendors and customers.

The schema follows **Third Normal Form (3NF)**. All tables use the `InnoDB` storage engine and `utf8mb4` character set. Primary keys are `INT UNSIGNED AUTO_INCREMENT`. Foreign keys enforce referential integrity with `ON DELETE CASCADE` where appropriate.

---

## Entity-Relationship Summary

```
users 1:1 vendor_profiles
users 1:many products            (vendor sells products)
users 1:many orders              (customer places orders)
users 1:many order_items         (vendor supplies order items)
users 1:many order_status_history
users 1:many reviews             (customer writes reviews)
categories 1:many products
products 1:many product_views
products 1:many order_items
products 1:many reviews
orders 1:many order_items
orders 1:1 transactions
orders 1:many order_status_history
```

---

## Table Definitions

### 1. users
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| user_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique user identifier |
| full_name | VARCHAR(120) | NOT NULL | User's full name |
| email | VARCHAR(190) | NOT NULL, UNIQUE | User's email address (login credential) |
| phone | VARCHAR(40) | NULL | Saved contact number for account settings |
| address | VARCHAR(255) | NULL | Default delivery address |
| city | VARCHAR(80) | NULL | Default city / municipality |
| district | VARCHAR(80) | NULL | Default district |
| postal_code | VARCHAR(20) | NULL | Default postal code |
| preferred_size | VARCHAR(40) | NULL | Saved size preference |
| preferred_colour | VARCHAR(80) | NULL | Saved colour preference |
| updates_opt_in | TINYINT(1) | NOT NULL DEFAULT 0 | Opt-in flag for store updates |
| password_hash | VARCHAR(255) | NOT NULL | bcrypt-hashed password |
| role | ENUM('customer','vendor','admin') | NOT NULL DEFAULT 'customer' | Account role |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Account creation timestamp |

### 2. vendor_profiles
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| profile_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique vendor profile identifier |
| user_id | INT UNSIGNED | NOT NULL, UNIQUE, FK → users(user_id) ON DELETE CASCADE | Vendor user account (1:1) |
| business_name | VARCHAR(160) | NOT NULL | Registered business name |
| owner_name | VARCHAR(120) | NOT NULL | Business owner's name |
| phone | VARCHAR(30) | NOT NULL | Business contact phone |
| address | VARCHAR(500) | NOT NULL | Business address |

### 3. categories
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| category_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique category identifier |
| name | VARCHAR(80) | NOT NULL, UNIQUE | Category name (e.g., Kurtas, Shirts) |

### 4. products
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| product_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique product identifier |
| vendor_id | INT UNSIGNED | NOT NULL, FK → users(user_id) | Vendor who listed the product |
| category_id | INT UNSIGNED | NOT NULL, FK → categories(category_id) | Product category |
| title | VARCHAR(180) | NOT NULL | Product display title |
| description | TEXT | NULL | Product description |
| price | DECIMAL(10,2) | NOT NULL, CHECK >= 0 | Product price in NPR |
| stock | INT UNSIGNED | NOT NULL DEFAULT 0 | Available stock quantity |
| sizes | VARCHAR(120) | NOT NULL DEFAULT 'S,M,L,XL' | Comma-separated available sizes |
| colours | VARCHAR(160) | NOT NULL DEFAULT 'Natural,Olive,Black' | Comma-separated available colours |
| image_url | VARCHAR(255) | NULL | Product image path |
| status | ENUM('active','inactive') | NOT NULL DEFAULT 'active' | Product visibility status |
| views | INT UNSIGNED | NOT NULL DEFAULT 0 | Product page view counter |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Listing creation timestamp |
| updated_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last update timestamp |

### 5. orders
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| order_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique order identifier |
| customer_id | INT UNSIGNED | NOT NULL, FK → users(user_id) | Customer who placed the order |
| fulfillment | ENUM('delivery','collection') | NOT NULL | Delivery or collection method |
| recipient_name | VARCHAR(120) | NOT NULL | Person receiving the order |
| phone | VARCHAR(40) | NOT NULL | Contact phone number |
| address | VARCHAR(255) | NULL | Delivery address (delivery only) |
| payment_method | ENUM('cash_on_delivery','cash_at_collection') | NOT NULL | Payment method (cash only) |
| status | VARCHAR(40) | NOT NULL DEFAULT 'placed' | Order lifecycle status (placed, vendor accepted, ready for delivery, shipped, delivered, discarded) |
| total | DECIMAL(10,2) | NOT NULL, CHECK >= 0 | Order total amount |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Order placement timestamp |

### 6. order_items
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| order_item_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique line item identifier |
| order_id | INT UNSIGNED | NOT NULL, FK → orders(order_id) | Parent order |
| product_id | INT UNSIGNED | NOT NULL, FK → products(product_id) | Ordered product |
| vendor_id | INT UNSIGNED | NOT NULL, FK → users(user_id) | Vendor fulfilling this item |
| quantity | INT UNSIGNED | NOT NULL | Quantity ordered |
| unit_price | DECIMAL(10,2) | NOT NULL | Price per unit at order time |

### 7. product_views
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| view_id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Unique view record identifier |
| product_id | INT UNSIGNED | NOT NULL, FK → products(product_id) | Viewed product |
| session_id | VARCHAR(128) | NULL | PHP session that viewed the product |
| viewed_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | View timestamp |
| KEY | idx_product_views_date(product_id, viewed_at) | Index for report queries | Supports most-viewed reports |

### 8. transactions
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| transaction_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique transaction identifier |
| order_id | INT UNSIGNED | NOT NULL, FK → orders(order_id), UNIQUE | Linked order (1:1) |
| payment_method | ENUM('cash_on_delivery','cash_at_collection') | NOT NULL | Payment method |
| amount | DECIMAL(10,2) | NOT NULL, CHECK >= 0 | Transaction amount |
| payment_status | ENUM('pending','paid','void') | NOT NULL DEFAULT 'pending' | Payment status |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Transaction timestamp |

### 9. order_status_history
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| history_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique history record identifier |
| order_id | INT UNSIGNED | NOT NULL, FK → orders(order_id) | Parent order |
| changed_by | INT UNSIGNED | NULL, FK → users(user_id) | User who changed status |
| status | ENUM('placed','vendor accepted','ready for delivery','shipped','delivered','discarded') | NOT NULL | New order status |
| note | VARCHAR(500) | NULL | Status change note |
| changed_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Change timestamp |
| KEY | idx_order_history_order_date(order_id, changed_at) | Index for tracking queries | Supports order tracking timeline |

### 10. reviews
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| review_id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique review identifier |
| customer_id | INT UNSIGNED | NOT NULL, FK → users(user_id) | Reviewing customer |
| product_id | INT UNSIGNED | NOT NULL, FK → products(product_id) | Reviewed product |
| rating | TINYINT UNSIGNED | NOT NULL, CHECK BETWEEN 1 AND 5 | Star rating |
| comment | VARCHAR(1000) | NOT NULL | Review text |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP | Review submission timestamp |
| UNIQUE KEY | uq_review(product_id, customer_id) | One review per customer per product | Prevents duplicate reviews |

---

## Constraints & Integrity

| Table | Foreign Key | Referenced Table | On Delete |
|-------|-------------|------------------|-----------|
| vendor_profiles | user_id | users(user_id) | CASCADE |
| products | vendor_id | users(user_id) | (restrict) |
| products | category_id | categories(category_id) | (restrict) |
| orders | customer_id | users(user_id) | (restrict) |
| order_items | order_id | orders(order_id) | CASCADE |
| order_items | product_id | products(product_id) | (restrict) |
| order_items | vendor_id | users(user_id) | (restrict) |
| product_views | product_id | products(product_id) | CASCADE |
| transactions | order_id | orders(order_id) | CASCADE |
| order_status_history | order_id | orders(order_id) | CASCADE |
| order_status_history | changed_by | users(user_id) | SET NULL |
| reviews | customer_id | users(user_id) | (restrict) |
| reviews | product_id | products(product_id) | (restrict) |

---

## Seed Data

The SQL script seeds:
- **3 users:** 1 vendor, 1 customer, 1 admin (password: `Demo123!` for all)
- **1 vendor profile:** Redline Royale Demo, Kathmandu, Nepal
- **6 categories:** Kurtas, Shirts, Jackets, Waistcoats, Accessories, Shoes
- **10 active products:** Sample fashion & apparel items with prices, stock, and images

---

## Reports Support

The schema supports the following date-range reports (daily, weekly, monthly, custom):

- **Vendor reports:** products added, product views, orders, units sold, sales summary, most viewed products, transaction statement (CSV/Excel export)
- **Customer reports:** transaction count, amount spent, products ordered, transaction history (CSV/Excel export)

---

## Security Considerations

- Passwords are stored using `password_hash()` (bcrypt) — never plain text
- All database access uses PDO prepared statements to prevent SQL injection
- Role-based access control separates customer, vendor, and admin data
- CSRF tokens protect all state-changing form submissions
- No payment card information is collected or stored (cash on delivery / cash at collection only)

---

## ALTER Statements (presentation slide 13)

```sql
ALTER TABLE products ADD INDEX idx_products_status (status);
ALTER TABLE orders ADD INDEX idx_orders_customer (customer_id);
ALTER TABLE order_items ADD CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE;
ALTER TABLE reviews ADD UNIQUE KEY uq_review (product_id, customer_id);
-- plus: sizes, colours, created_at, updated_at added to products
```

All current CREATE and ALTER statements are executed from `database/redline_royale.sql`. The migration file is retained as historical reference only; do not run it against the current schema.
