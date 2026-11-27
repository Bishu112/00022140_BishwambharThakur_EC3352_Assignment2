# Redline Royale — EC3352 Assessment 2 Submission

Database Interaction demo for the Redline Royale B2C fashion store.

## Run

1. Start MySQL/MariaDB and confirm `pdo_mysql` is enabled.
2. Check `config.php` (default database `redline_royale_assignment2`, user `root`, empty password, automatic port 3306/3307).
3. From this folder run:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

4. Open `http://127.0.0.1:8000/index.html`.

The first request creates and seeds the database if it is missing. Demo password for all roles: `Demo123!`.

| Role | Email |
|---|---|
| Customer | `customer@redlineroyale.test` |
| Vendor | `vendor@redlineroyale.test` |
| Admin | `admin@redlineroyale.test` |

Extra demo reviewers (same password `Demo123!`): 30 Nepali customer accounts, for example `sita@redlineroyale.test` (Sita Gurung), `ram@redlineroyale.test` (Ram Shrestha) and `bikash.maharjan@redlineroyale.test` (Bikash Maharjan). Each product has three reviews from a different set of people.

## What is included for Assessment 2

| Requirement | Evidence in this folder |
|---|---|
| Database schema, tables, relationships/cardinality | `database/redline_royale.sql`, `documentation/Database_Schema.md`, `documentation/ER_Diagram.png` |
| PHP–MySQL connection | `config.php`, `config.example.php`, `config/database.php`, `includes/bootstrap.php` |
| INSERT from PHP | `public/register.php`, `public/vendor-register.php`, `public/product-list.php`, `public/admin-products.php`, `public/review.php`, `public/product-review.php` |
| SELECT from PHP | `public/home.php`, `public/index.php`, `public/product.php`, `public/product-list.php`, `public/admin-product-view.php`, `public/admin-product-edit.php`, `public/reports.php` |
| UPDATE from PHP | `public/product-list.php`, `public/vendor-orders.php`, `public/profile.php`, `public/admin-products.php`, `public/admin-product-edit.php` |
| DELETE from PHP | `public/product-list.php`, `public/admin-products.php` |
| PHP SESSION / status messages | `includes/bootstrap.php`, `includes/header.php`, `public/cart.php`, `public/checkout.php` |
| 2–3 linked PHP pages per member | `public/` pages linked from `includes/header.php` |
| Team presentation | `Presentation/00022140_BishwambharThakur_EC3352_Assignment2.pptx` |
| Customer account settings | `public/profile.php` — contact, delivery and preference UPDATE with activity SELECTs |
| Original wireframe theme | `public/style.css` — `#7A334F` plum with neutral greys, restored after the temporary premium repaint |
| Walkthrough of the data layer | `documentation/Assignment2_Demonstration.md`, `documentation/Demo_Script.md` |

Admin catalogue management uses a compact catalogue table in `public/admin-products.php`. `public/admin-product-view.php` and `public/admin-product-edit.php` open exactly one selected product at a time.

The full final integrated system is kept separately in the XAMPP project and in Assessment 4. Static design files and screenshots are kept in Assessment 3, so they are intentionally not in this Assessment 2 submission folder.

## GitHub Pages demo

The link https://bishu112.github.io/00022140_BishwambharThakur_EC3352_Assignment2/ opens the static HTML storefront copied from the Assessment 4 final system (`index.html`, `shop.html`, `product.html`, … with `css/`, `js/` and `img/`). GitHub Pages serves static files only, so the sign-in, cart, checkout and admin forms are visual demos there. Run the PHP application from `public/` in XAMPP for the working database-backed website.