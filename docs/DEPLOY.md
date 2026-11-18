# Uploading to the live server

A page that suddenly looks unstyled (landing page, offer page) is almost always an upload that stopped half way:
the CSS file on the server is shorter than it should be, so the rules near its end are missing.

## Every upload

1. On your computer: `php bin/make-deploy-manifest.php` (writes `deploy-manifest.json`: every file with its size and SHA-1).
2. Upload the changed files **and** `deploy-manifest.json` (FTP in binary mode; wait until the queue is empty).
3. Run new migrations and `php bin/seed.php` when the PR says so.
4. Sign in as ENLIXO admin and open **`/deploy-check`**. It lists files that are **missing**, **cut off** (smaller than
   they should be) or a **different version**. Upload those again until it says "Every file is uploaded completely".
5. Purge the host cache if there is one, then Ctrl + F5.

`deploy-manifest.json` cannot be downloaded from the site (.htaccess); only the Deploy Check page reads it.

## Caches

Every CSS / JS link carries `?v=<release>.<newest change time of any CSS/JS file>` (`Project::getVersion()`), and the
landing / sign-up pages add each file's own change time. A re-uploaded file therefore always gets a new address, so
browsers and host caches never keep the old copy.

## Page not found

A missing address shows `404.php` (`ErrorDocument 404` in `.htaccess`; `bin/dev-router.php` does the same locally).
Addresses without `.php` are only rewritten when that `.php` file exists. The 404 page has inline styles only, so it
works even when CSS files are missing, and links back to the app the address belongs to (tutor, portal, parent, admin).
