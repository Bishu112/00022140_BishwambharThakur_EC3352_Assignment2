# Redline Royale — Assignment 2 Demonstration

**Project:** EC3352 Dynamic Web Programming — Assessment 2 (Database Interaction, 10%, Group)
**Application:** Redline Royale — Fashion & Apparel Store (B2C)
**Database:** `redline_royale_assignment2` (10 normalized tables, MySQL/MariaDB)

---

## 1. Introduction

Redline Royale is a dynamic B2C fashion & apparel web application that enables independent vendors to list and sell clothing and accessories online. Customers can browse products, search and filter, add items to a session-based shopping cart, checkout with delivery or collection (cash on delivery only), track orders, and submit reviews. Vendors can manage their own products, accept or discard order requests, view sold items, and generate date-range reports.

This demonstration covers the **data layer** that powers the store: the database schema, PHP PDO connection, full CRUD operations via PHP, PHP SESSION usage, and linked pages presented by each group member.

---

## 2. Database Description

**Database name:** `redline_royale_assignment2`
**Character set:** `utf8mb4` / `utf8mb4_unicode_ci`
**Storage engine:** `InnoDB`
**Tables:** 10 (all normalized to 3NF)

| Table | Purpose | Key Relationships |
|-------|---------|-------------------|
| `users` | Customer, vendor, and admin accounts | 1:1 vendor_profiles, 1:many orders/products/reviews |
| `vendor_profiles` | Vendor business details | FK → users |
| `categories` | Product categories (Kurtas, Shirts, etc.) | 1:many products |
| `products` | Vendor product listings | FK → users (vendor), FK → categories |
| `orders` | Customer orders (delivery/collection) | FK → users (customer) |
| `order_items` | Line items within orders | FK → orders, products, users (vendor) |
| `product_views` | Product page view analytics | FK → products |
| `transactions` | Payment records (cash only) | FK → orders (1:1) |
| `order_status_history` | Order status audit trail | FK → orders, users |
| `reviews` | Customer product reviews | FK → users, products |

**Entity-Relationship Diagram:** See `documentation/ER_Diagram.png`. Cardinality includes `1:1` (users→vendor_profiles, orders→transactions) and `1:M` (all other relationships).

---

## 3. Relational Schema

Full table definitions with columns, data types, constraints, primary keys, foreign keys, and CHECK constraints are documented in `documentation/Database_Schema.md`.

Key design decisions:
- All primary keys are `INT UNSIGNED AUTO_INCREMENT` (except `product_views.view_id` which is `BIGINT UNSIGNED` for high-volume analytics).
- Foreign keys enforce referential integrity with `ON DELETE CASCADE` where dependent records should not outlive their parent (e.g., order_items when an order is deleted).
- `CHECK` constraints validate non-negative prices, ratings between 1 and 5, and non-negative transaction amounts.
- `UNIQUE` constraints on `users.email`, `categories.name`, `transactions.order_id`, and `reviews(customer_id, product_id)` prevent duplicates.

---

## 4. PHP–MySQL Connection

**File:** `includes/bootstrap.php`

A single PDO connection is established once per request and shared across all pages. Settings come from `config.php` (via `config/database.php`); the script tries the configured port, then 3306 and 3307, and imports `database/redline_royale.sql` automatically if the database is missing:

```php
$pdo = new PDO(
    "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset={$charset}",
    $config['username'],
    $config['password'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
```

- **Prepared statements** are used for every query to prevent SQL injection.
- **Error mode** is set to exceptions for robust error handling.
- **`config.php`** holds connection parameters (host, port, database, username, password, charset) and is loaded via `config/database.php`.

---

## 5. CRUD Operations via PHP

### INSERT
| Feature | File | SQL |
|---------|------|-----|
| User registration | `public/register.php`, `public/vendor-register.php` | `INSERT INTO users(...)` |
| Product creation | `public/product-list.php` | `INSERT INTO products(...)` |
| Order placement | `public/review.php` | `INSERT INTO orders(...)`, `INSERT INTO order_items(...)`, `INSERT INTO transactions(...)`, `INSERT INTO order_status_history(...)` |
| Product review | `public/product-review.php` | `INSERT INTO reviews(...)` |
| Status history | `public/vendor-orders.php` | `INSERT INTO order_status_history(...)` |

### SELECT
| Feature | File | SQL |
|---------|------|-----|
| Product catalogue | `public/index.php` | `SELECT ... FROM products JOIN categories ... WHERE status='active'` with search/filter/sort |
| Product detail | `public/product.php` | `SELECT ... FROM products WHERE product_id=? AND status='active'` |
| Vendor products | `public/product-list.php` | `SELECT ... FROM products WHERE vendor_id=?` |
| Vendor orders | `public/vendor-orders.php` | `SELECT DISTINCT ... FROM orders JOIN order_items ... WHERE vendor_id=?` |
| Order tracking | `public/order-tracking.php` | `SELECT ... FROM orders WHERE order_id=? AND customer_id=?` + `SELECT ... FROM order_status_history` |
| Reports | `public/reports.php`, `public/customer-reports.php` | Date-range filtered SELECT with GROUP BY for aggregates |

### UPDATE
| Feature | File | SQL |
|---------|------|-----|
| Product edit | `public/product-list.php` | `UPDATE products SET ... WHERE product_id=? AND vendor_id=?` |
| Order status | `public/vendor-orders.php` | `UPDATE orders o JOIN order_items i ON i.order_id=o.order_id SET o.status=? WHERE o.order_id=? AND i.vendor_id=?` |
| Transaction status | `public/vendor-orders.php` | `UPDATE transactions SET payment_status=... WHERE order_id=?` |
| Profile update | `public/profile.php` | `UPDATE users SET ...`, `UPDATE vendor_profiles SET ...` |

### DELETE
| Feature | File | SQL |
|---------|------|-----|
| Product deletion | `public/product-list.php` | `DELETE FROM products WHERE product_id=? AND vendor_id=?` |
| Admin product deletion | `public/admin-products.php` | `DELETE FROM products WHERE product_id=?` |

Note: customer order cancellation (`customer-dashboard.php`) is an UPDATE to status `discarded`, not a DELETE.

---

## 6. PHP SESSION Usage

PHP SESSION is used throughout the application for:

| Purpose | File(s) | Implementation |
|---------|---------|----------------|
| User authentication | `bootstrap.php`, all pages | `$_SESSION['user']` stores the logged-in user record (id, email, role, full_name) |
| Flash/status messages | `bootstrap.php` `flash()` | `$_SESSION['flash']` set on action, read and cleared on next page load |
| Shopping cart | `public/cart.php`, `public/product.php` | `$_SESSION['cart']` maps product_id → quantity; `$_SESSION['cart_variants']` stores size/colour |
| Checkout details | `public/checkout.php`, `public/review.php` | `$_SESSION['checkout_details']` stores delivery/collection info |
| CSRF protection | `bootstrap.php` `csrf_token()` / `check_csrf()` | `$_SESSION['csrf']` random token validated on POST |

Example:
```php
function flash($message = null) {
    if ($message !== null) { $_SESSION['flash'] = $message; return; }
    $value = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $value;
}
```

---

## 7. User Registration and Login

### Registration (`public/register.php`, `public/vendor-register.php`)
- Customers: full name, email and password. Vendors (`vendor-register.php`): business name, owner name, email, phone, business address and password.
- Validates email format and required fields.
- Inserts into `users` with `password_hash()` (bcrypt).
- Inserts into `vendor_profiles` for vendor accounts.
- Sets flash message and redirects to login.

### Login (`public/login.php`, `public/vendor-login.php`)
- Accepts email and password.
- `SELECT` from `users` by email, then `password_verify()` against stored hash.
- On success, regenerates the session ID, sets `$_SESSION['user']` and redirects to the requested page (`next`) or `home.php`; the header then shows the dashboard link for the user's role (customer-dashboard, vendor-dashboard, or admin-dashboard). `vendor-login.php` redirects straight to `vendor-dashboard.php`.
- On failure, shows error message.

### Role-Based Access Control
`require_role(['vendor'])`, `require_role(['customer'])`, etc. in `bootstrap.php`:
- Redirects unauthenticated users to `login.php`.
- Shows 403 error if the logged-in user's role is not authorized for the page.

---

## 8. Team Page Allocation

Each group member developed and demonstrated 2–3 functional pages with PHP-based database interaction. The table below is a suggested allocation to be filled in by the group:

| Member | Student ID | Pages Developed | Features |
|--------|------------|-----------------|----------|
| Member 1 | | `home.php`, `index.php` | Product catalogue, search, filters, sort |
| Member 2 | | `product.php`, `cart.php` | Product detail, reviews, session cart |
| Member 3 | | `checkout.php`, `review.php`, `payment.php` | Delivery/collection checkout, order placement |
| Member 4 | | `vendor-dashboard.php`, `product-list.php` | Vendor product CRUD, dashboard metrics |
| Member 5 | | `vendor-orders.php`, `reports.php` | Accept/discard orders, date-range reports, export |

All pages are linked through the shared header navigation in `includes/header.php`, presenting a combined product as a team.

---

## 9. SQL Statements Used in the Demonstration

### Create Database
```sql
CREATE DATABASE IF NOT EXISTS redline_royale_assignment2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### Create Tables (sample)
```sql
CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','vendor','admin') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

All 10 `CREATE TABLE` statements are in `database/redline_royale.sql`.

### ALTER Statements
```sql
ALTER TABLE products ADD INDEX idx_products_status (status);
ALTER TABLE orders ADD INDEX idx_orders_customer (customer_id);
ALTER TABLE order_items ADD CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE;
ALTER TABLE reviews ADD UNIQUE KEY uq_review (product_id, customer_id);
ALTER TABLE products ADD COLUMN sizes ..., ADD COLUMN colours ..., ADD COLUMN created_at ..., ADD COLUMN updated_at ...;
```
These are run in `database/redline_royale.sql` after the CREATE TABLE statements.

### CRUD Query Statements (executed from PHP)
```sql
-- INSERT (registration)
INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?);

-- SELECT (product catalogue)
SELECT p.product_id, p.title, p.price, c.name category
FROM products p JOIN categories c ON c.category_id = p.category_id
WHERE p.status = 'active';

-- UPDATE (product edit)
UPDATE products SET category_id=?, title=?, price=?, stock=? WHERE product_id=? AND vendor_id=?;

-- DELETE (product removal)
DELETE FROM products WHERE product_id=? AND vendor_id=?;
```

---

## 10. Presentation Requirements Covered

The following slides are included in `Presentation/00022140_BishwambharThakur_EC3352_Assignment2.pptx`:

1. Cover Page
2. Project Team (title slide)
3. Table of Contents
4. Introduction
5. Database Description
6. Entity-Relationship Diagram
7. ER Diagram Components (cardinality and constraints)
8. Relational Schema
9. SQL: Create Database
10. SQL: Create Table - users
11. SQL: Create Table - products
12. SQL: Create Table - orders
13. SQL: ALTER Statements
14. SQL: INSERT - User Registration
15. SQL: SELECT - Product Listing
16. SQL: UPDATE - Edit Product
17. SQL: DELETE - Delete Product
18. PHP: User Registration and Login
19. Admin and Customer Dashboard Interaction
20. Summary
21. Conclusion
22. Any Questions? Thank You
23. Marking Rubric (5 slides)

Group members must fill in their real names and student IDs where placeholders appear before the presentation.

---

## 11. Conclusion

The data layer for Redline Royale is fully implemented with:
- A normalized 10-table schema with proper PK/FK relationships and cardinality.
- PDO connection with prepared statements for secure database access.
- Complete CRUD operations demonstrated via PHP.
- PHP SESSION used for authentication, flash messages, and shopping cart.
- User registration and login with role-based access control.
- All pages linked through shared header navigation.

The application is ready for Assessment 2 demonstration and integration into Assessments 3 and 4.
