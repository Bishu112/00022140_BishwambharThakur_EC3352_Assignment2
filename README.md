# Redline Royale — EC3352 Assessment 2

Redline Royale is a PHP and MySQL fashion store built with HTML, CSS, and vanilla JavaScript. It demonstrates database design, PHP PDO, SQL CRUD, sessions, role-based pages, checkout, order tracking, and reports.

## Run locally with XAMPP Lite

1. Start MySQL/MariaDB in XAMPP Lite. Ensure PHP has the `pdo_mysql` and `mbstring` extensions enabled.
2. Open a terminal in this project folder (`FINAL  WEBSITE DYNAMIC`).
3. Start the PHP server from the project root:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

4. Open `http://127.0.0.1:8000/index.html`.

The first PHP page creates `redline_royale_assignment2` and imports `database/redline_royale.sql` when the database is missing. The seed now has 10 active men's products. If the database was already installed, run `database/migrations/2026-11-24-set-demo-catalog-to-10.sql` in phpMyAdmin; it adds missing demo items and marks three extras inactive while preserving records referenced by past orders. The home page displays the complete active catalog. Update `config.php` if the database host, port, or credentials differ on your computer. `config.example.php` is the portable template.

## Demo accounts

All seeded accounts use password `Demo123!`.

| Role | Email |
|---|---|
| Vendor | `vendor@redlineroyale.test` |
| Customer | `customer@redlineroyale.test` |
| Admin | `admin@redlineroyale.test` |

## Project structure

```text
config.php                  Local database settings
config.example.php          Portable settings template
config/database.php         Loads local settings
database/redline_royale.sql Database schema, ALTER statements, and demo data
includes/                   Shared bootstrap, header, footer, report exports
public/                     PHP pages, HTML entry, CSS, JS, and product images
documentation/              Schema, installation, demo, and verification notes
design-references/          Supplied storefront wireframes
Presentation/               Original and corrected Assessment 2 decks
tests/                      Non-destructive database integration smoke check
```

## Main features

- Customer registration/login, catalogue search and filters, product pages, cart, checkout, and order tracking.
- Vendor product view/add/edit/delete, order handling, profile, and date-range reports.
- Admin product and order management and reports.
- Cash on delivery or cash at collection; no card data is collected.
- PDO prepared statements, password hashing, role checks, CSRF tokens, and session cart.
- The seeded men's catalog uses bundled PNG product photos; image credits and source links are in `documentation/Product_Image_Sources.md`.

## Troubleshooting

- **Database unavailable:** start MySQL/MariaDB and enable `pdo_mysql` and `mbstring` in the PHP runtime used to serve the site. Check `config.php` credentials and port.
- **404:** start the server with `-t public` and open the URL above.
- **No products:** import `database/redline_royale.sql` into the configured database.
- **Image upload errors:** ensure PHP upload limits allow files up to 5 MB; uploaded images are stored under `public/uploads/products/`.

The `_previous_site_2026-11-24` folder is a retained backup of the older implementation. Run and submit the project root containing this README, not that backup.
