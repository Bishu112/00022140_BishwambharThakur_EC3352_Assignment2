# Installation Guide

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `mbstring`, and file uploads enabled.
- MySQL 8 or MariaDB with InnoDB and `utf8mb4` support.
- This Assessment 2 submission folder.

## Start the application

1. Start MySQL/MariaDB using XAMPP Lite or your installed database service.
2. Check `config.php`. The default database is `redline_royale_assignment2`, with host `127.0.0.1` and automatic port selection (3306, then 3307).
3. From the project root, start PHP:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

4. Visit `http://127.0.0.1:8000/index.html`.

The PHP application imports `database/redline_royale.sql` when the configured database does not exist. The SQL file creates its own database and includes the ten table definitions, schema alterations, and demo records. A MySQL account with permission to create databases is required for this automatic setup. Otherwise, import the SQL file through phpMyAdmin using an account with those permissions, then reload the site.

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Vendor | `vendor@redlineroyale.test` | `Demo123!` |
| Customer | `customer@redlineroyale.test` | `Demo123!` |
| Admin | `admin@redlineroyale.test` | `Demo123!` |
