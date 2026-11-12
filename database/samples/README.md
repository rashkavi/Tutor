# Dashboard sample data

Fills the tutor, class details, student and parent dashboards with realistic data so they can be shown and tested.
**Use only on a test/demo copy of the database, never on the live one.**

1. Run the migrations first (`database/migrations/001` … `012`; section 10 needs `012_tute_delivery.sql`).
2. Load the sample data:
   ```bash
   mysql -u USER -p DB_NAME < database/samples/dashboard-sample-data.sql
   ```
3. Remove it again (everything it added is marked, nothing else is touched):
   ```bash
   mysql -u USER -p DB_NAME < database/samples/remove-sample-data.sql
   ```

It uses the classes and students already in the database, and its dates are relative to today, so it can be loaded
at any time. Running it twice adds nothing new.

What it adds: enrolments for classes with no students, monthly fee plans, two weekly class times per class,
class days from 6 weeks ago to 3 weeks ahead (plus one extra class today), attendance, fees for up to 6 months
(paid, waiting for approval and not paid), recordings, addresses, parents linked to children, and one courier tute
per class with delivery statuses.
