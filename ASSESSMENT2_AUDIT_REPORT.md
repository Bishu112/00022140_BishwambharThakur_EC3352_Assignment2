# Assessment 2 deep audit report

**Date:** 27 November 2026  
**Folder:** `00022140_BishwambharThakur_EC3352_Assessment2_Submission`  
**Scope:** all files in the clean Assessment 2 submission folder: PHP pages, shared includes, SQL, documentation, images and the presentation deck.

## Result

The Assessment 2 folder is aligned with the coursework question and rubric after the fixes below.

## Checks passed

| Area | Result |
|---|---|
| PHP syntax | 38 PHP files, 0 syntax errors |
| Local page/component references | No broken page, CSS, JS, favicon or image references |
| PDO connection | Connects on port 3307; database `redline_royale_assignment2`, 10 tables |
| Database seed after reset | 3 users, 10 products, all 10 active |
| INSERT from PHP | Present in registration, vendor registration, product creation, order placement and reviews |
| SELECT from PHP | Present in catalogue, product detail, vendor products/orders, reports and account settings |
| UPDATE from PHP | Present in product edit, order status, admin product edit and account settings |
| DELETE from PHP | Present in product deletion |
| SESSION usage | Authentication, cart, checkout details, flash/status messages and CSRF |
| Prepared statements | 91 `prepare()` calls found |
| POST protection | Every POST handler calls `check_csrf()` |
| Role guards | Vendor, customer and admin pages use `require_role()` |
| Card data | No card / CVV / credit / debit input fields found; cash only |
| External libraries | None; only local CSS/JS and one XML namespace URL in an Excel export |
| Runtime smoke test | All tested pages/assets returned 200 with no PHP/SQL errors |
| Login smoke test | Customer, vendor and admin logins succeeded with `Demo123!`; dashboards returned 200 |
| Role guard smoke test | Unauthenticated vendor dashboard returned 302 to login |
| Admin product management | Catalogue table plus dedicated single-product View/Edit pages; all admin smoke assertions passed |
| Customer account settings | Expanded `profile.php` with contact, delivery, preference and activity panels; all account smoke assertions passed |
| Visual theme | Original wireframe palette restored: `#7A334F` plum with neutral greys; temporary premium repaint removed |
| Text contrast | Original wireframe text colours restored across storefront, dashboards, admin and account pages; static HTML matches the design report stylesheet |
| Admin product deletion | Deleting a product linked to an order now succeeds; sold-item name/price are kept in order history |

## Presentation audit

- File: `Presentation/00022140_BishwambharThakur_EC3352_Assignment2.pptx`
- Slides: 34
- Group names and IDs on slide 2 match the names added to `documentation/Assignment2_Demonstration.md`
- Covers all 10 tables, ER diagram, relational schema, CREATE/ALTER statements, CRUD, registration/login, dashboards, summary, conclusion and rubric
- The ER image inside the deck is byte-identical to `documentation/ER_Diagram.png`
- Slides 30–34 contain embedded rubric images; their text is image-based, not machine-readable, but all five images are present
- Slide 13 now includes a note that the final SQL also contains sizes, colours and timestamps
- Slides 8, 10 and 20 now reflect the account settings columns added to `users` and the ALTER statement note

## Fixes applied during the audit

1. Filled real group member names and student IDs in `documentation/Assignment2_Demonstration.md` section 8 using the names from the presentation.
2. Corrected the seed-data note in `documentation/Database_Schema.md` from 13 products to 10 active products.
3. Updated the checklist item for group member details to complete.
4. Updated slide 13 of the presentation to mention the final product-table columns.
5. Re-imported `database/redline_royale.sql` into the local demo database because the running database had stale/test records (8 products, 7 users). A backup of the previous data was saved before the reset.
6. Replaced the repeated admin edit forms with a single catalogue table and added `admin-product-view.php` / `admin-product-edit.php`, so View and Edit open only the selected product. The pages keep the original wireframe colour palette.
7. Expanded `public/profile.php` into a full Account Settings page with persisted contact, delivery and preference fields and activity figures, using the original wireframe styling. Added matching `users` columns to the SQL and schema notes, and updated presentation slides 8, 10 and 20.
8. Reverted the temporary premium plum/gold/ivory repaint and restored the original wireframe colour values (`#7A334F` plum, neutral greys) across the stylesheet, including the added admin and account panels. Malformed CSS line breaks that had been stored as literal `\n` text remain repaired.
9. Removed the extra sitewide readability overrides so headers, footers, hero panels and workspace chrome use the original wireframe colours again. The static HTML stylesheet was restored to the design-report version.
10. Fixed the “linked to an order and cannot be deleted” failure: `order_items.product_id` is now nullable with `ON DELETE SET NULL` plus a `product_title` snapshot, reviews cascade on product delete, and both admin/vendor delete handlers snapshot the title before deleting. Vendor sold-item history uses a `LEFT JOIN` so deleted products still appear.

## Before demonstration

Use this server command from this folder:

```powershell
php -S 127.0.0.1:8000 -t public
```

Open:

```text
http://127.0.0.1:8000/index.html
```

Demo password for all three roles: `Demo123!`

Follow `documentation/Demo_Script.md` when presenting.
