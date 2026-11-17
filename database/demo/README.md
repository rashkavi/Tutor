# ENLIXO Tutor demo data (SQL)

For a TEST server where `php bin/seed-demo.php` cannot be run.

1. The database must already have the ENLIXO tables and plans: migrations up to `018_free_plan_upgrades.sql` and `php bin/seed.php`
   (or a copy of a database that has them).
2. phpMyAdmin → the test database → **Import** → `enlixo-demo-data.sql` → Go. (Or `mysql -u USER -p DATABASE < enlixo-demo-data.sql`.)
3. Upload the files in `tutes-attachments/` to `assets/uploads/tutes-attachments/` on the server (the demo tutes open them).
4. Sign in with the details in `docs/DEMO-DATA.md` (every password `Demo#2026`). Students and parents need a WhatsApp code:
   on a test server without WhatsApp set `ENLIXO_DEMO_OTP=1`, then the demo numbers (+9470000…) take the code `123456`.

To remove it: import `remove-demo-data.sql` (only demo rows: …@demo.enlixo.lk e-mails and +9470000… numbers).
Importing `enlixo-demo-data.sql` twice stops at the first demo e-mail that already exists, so remove it first.

The file inserts every row without its id and links rows with `LAST_INSERT_ID()` variables, so it never clashes with the
ids already in the database; plans, subjects, cities and permissions are found by name. Dates are fixed at the day it
was made: run `php bin/seed-demo.php reset && php bin/export-demo-sql.php` to make a fresh one.
