# Assessment 2 demonstration script

Use this order when demonstrating the database layer.

1. **Show the design.** Open `documentation/ER_Diagram.png` and `documentation/Database_Schema.md`. Point out the 10 tables, primary keys, foreign keys and 1:1 / 1:M cardinality.

2. **Show the SQL file.** Open `database/redline_royale.sql`. Show `CREATE DATABASE`, the `CREATE TABLE` statements, `ALTER` statements and seed data.

3. **Start the app.** From this folder run `php -S 127.0.0.1:8000 -t public`, then open `http://127.0.0.1:8000/index.html`.

4. **SELECT.** Open the catalogue (`public/index.php` or `public/home.php`) and a product detail page (`public/product.php`). Explain that products come from `SELECT` queries with PDO prepared statements.

5. **INSERT.** Register a new customer through `public/register.php`, or add a vendor product through `public/product-list.php`. Show the matching `INSERT INTO users(...)` / `INSERT INTO products(...)` in the source.

6. **UPDATE.** Edit a vendor product in `public/product-list.php` or change an order status in `public/vendor-orders.php`. Show the matching `UPDATE` query. Also open `public/profile.php` as the customer and save contact, delivery and preference details; point to the `UPDATE users SET ...` statement with the session flash message.

7. **DELETE.** Delete a vendor product in `public/product-list.php`. Show `DELETE FROM products WHERE product_id=? AND vendor_id=?`.

8. **SESSION.** Add an item to `public/cart.php`, proceed to `public/checkout.php`, and show a flash message. Explain that `$_SESSION` stores authentication, cart contents, checkout details, CSRF token and confirmation/status messages.

9. **Linked pages and teamwork.** Use the shared header in `includes/header.php` to move between catalogue, product, cart, vendor workspace and reports. Explain which group member demonstrated each page.

10. **Close with the deck.** Open `Presentation/00022140_BishwambharThakur_EC3352_Assignment2.pptx` and link the demonstration back to the rubric.