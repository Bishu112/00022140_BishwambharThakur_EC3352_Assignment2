# Verification Notes

**Project:** EC3352 Dynamic Web Programming — Assessment 2  
**Scope:** Active PHP application under `public/`, shared code under `includes/`, configuration, database export, and Assessment 2 presentation.

## Checks completed

- PHP syntax lint passed for all active PHP pages, shared includes, and configuration files using the XAMPP Lite PHP runtime.
- Checked that the database export defines the ten tables used by the application and seeds the demo accounts and products.
- Reviewed the vendor product list and handlers for add, view, edit, and delete actions and vendor ownership scoping.
- Confirmed the corrected presentation contains all ten table slides, the ALTER section, and five original marking-rubric pages.

## Database integration status

The included integration check is `tests/database_smoke.php`. It could not be completed in this session: MariaDB could not start against its configured data directory because the directory was not writable to the current process. Database-backed page behavior still needs to be verified with MySQL/MariaDB started through the XAMPP Lite control panel.

After starting MySQL/MariaDB through XAMPP Lite, run:

```powershell
php tests/database_smoke.php
```

The integration check rolls its temporary writes back inside a database transaction.
