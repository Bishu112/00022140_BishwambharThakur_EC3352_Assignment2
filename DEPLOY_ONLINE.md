# Deploy the real Redline Royale PHP/MySQL app online

GitHub Pages can only serve static HTML/CSS/JS. It **cannot run PHP or MySQL**, so the `bishu112.github.io/...` link is only the visual demo from the `gh-pages` branch. To open the real database-backed website from a link, upload the `main` branch to a PHP host.

Recommended free option for this assignment: **InfinityFree** (PHP + MySQL + phpMyAdmin). Any cPanel host works too.

## 1. Create the hosting account

1. Sign up for a free hosting account and create a website/subdomain, for example `yourname.infinityfreeapp.com`.
2. In the hosting control panel, open **MySQL Databases**.
3. Create a database and a MySQL user. Write down these four values:

| Value | Example |
|---|---|
| MySQL hostname | `sqlXXX.infinityfree.com` or `localhost` |
| Database name | `if0_12345678_redline` |
| Database username | `if0_12345678_rr` |
| Database password | chosen when creating the user |

Give the user **All Privileges** on that database.

## 2. Import the database

1. Open **phpMyAdmin** from the hosting control panel.
2. Select the database you created — do **not** create a second one.
3. Choose **Import** and upload:

   ```text
   database/redline_royale_host.sql
   ```

   This file is hosting-safe: it does not run `CREATE DATABASE` or `USE redline_royale_assignment2`.

4. Start the import. The database should contain 10 tables after it finishes.

> Import into an empty database. The SQL file drops existing application tables before recreating them.

## 3. Set the live database credentials

On the web host, open `config.php` in the file manager/editor and replace the local XAMPP values:

```php
<?php
return [
    'host' => 'YOUR_MYSQL_HOSTNAME',
    'port' => 3306,
    'database' => 'YOUR_DATABASE_NAME',
    'username' => 'YOUR_DATABASE_USER',
    'password' => 'YOUR_DATABASE_PASSWORD',
    'charset' => 'utf8mb4',
];
```

Use the exact values from step 1. If the host says the MySQL hostname is `localhost`, use `localhost`. Do **not** commit this edited file to GitHub with the real password inside it.

## 4. Upload the PHP files

Download the `main` branch ZIP from GitHub (or use the prepared ZIP in the working folder), then upload **all files and folders** to the host's web root, usually `htdocs` or `public_html`.

The uploaded structure must look like this:

```text
htdocs/
  index.php
  config.php
  config/
  database/
  documentation/
  includes/
  public/
  Presentation/
```

Do **not** upload the `gh-pages` static files for the live app. Keep `public/` intact — the root `index.php` redirects visitors to `public/home.php`.

## 5. Open the live site

Visit your hosting domain, for example:

```text
https://yourname.infinityfreeapp.com/
```

Test sign-in with:

| Role | Email | Password |
|---|---|---|
| Customer | `customer@redlineroyale.test` | `Demo123!` |
| Vendor | `vendor@redlineroyale.test` | `Demo123!` |
| Admin | `admin@redlineroyale.test` | `Demo123!` |

## Troubleshooting

- **Database Unavailable:** recheck `config.php`; confirm `pdo_mysql` is enabled and the MySQL user has privileges on that exact database.
- **404 on the homepage:** make sure you uploaded the root `index.php` and the whole `public/` folder beside it.
- **Blank page:** enable PHP error display in the host panel or check the hosting error log.
- **Old GitHub demo still appears:** that is expected. The `bishu112.github.io` URL stays static; your new hosting domain is the real PHP link.
