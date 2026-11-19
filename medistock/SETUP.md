# MediStock – Laravel setup (simple steps)

These files are the **app part** of the project. Laravel itself (the `vendor` folder, `artisan`, `config`, etc.)
comes from a fresh Laravel project, then you copy our files on top of it.

## What you need
- PHP 8.2 or higher
- Composer (https://getcomposer.org)
- XAMPP (only for MySQL + phpMyAdmin)

## Steps

1. **Make a new Laravel 11 project** (in any folder, for example `C:\projects`):

       composer create-project laravel/laravel medistock

2. **Copy our files into the new `medistock` folder** (say "Yes" to replace files):
   the folders `app`, `bootstrap`, `public`, `resources`, `routes` and the file `.env.example`.

3. **Make the .env file**: copy `.env.example` to `.env` (replace the old one), then run:

       cd medistock
       php artisan key:generate

4. **Make the database** – open phpMyAdmin (XAMPP > Start MySQL) and run the files in the `sql` folder
   in this order (Import tab):

       login.sql, dashboard.sql, inventory.sql, sales.sql, analytics.sql, add_medicine.sql,
       new_sale.sql, users.sql, backup.sql, settings.sql, receipt.sql, transactions.sql, suppliers.sql, stock_in.sql, stock_adjust.sql, reports.sql, alerts.sql, detail_pages.sql

   (If phpMyAdmin ever shows "There are no routines", or the pages show a server error, run rebuild_routines.sql. It only re-creates the views and procedures, so your data is kept.)

   (If you run dashboard.sql again, run alerts.sql again after it.)
   After detail_pages.sql, also run `expired_removal.sql` (the Remove Expired Stock page)
   and `face_login.sql` (face login; running it again deletes the registered faces).

5. **Run the system**:

       php artisan serve 

   Open http://localhost:8000 and log in with `admin` / `admin123`.

## Where is everything? (for the defense)

| Folder / file | What it is |
|---|---|
| `routes/web.php` | all the page links (routes) |
| `app/Http/Controllers/` | one controller per page: the PHP logic (before, the top of each .php page) |
| `resources/views/` | Blade pages: the HTML (before, the bottom of each .php page) |
| `resources/views/layouts/app.blade.php` | the shared layout (sidebar, top bar, flash message) |
| `app/Http/Middleware/` | `CheckLogin` (must be logged in) and `AdminOnly` (User Management) |
| `app/Support/Proc.php` | small class that calls our MySQL **stored procedures** |
| `app/helpers.php` | small helper functions (money, status colors, ...) |
| `sql/` | tables, **views** and **stored procedures** (data security) |
| `public/css/` | style.css and login.css (same design as the Figma) |

## Notes
- The controllers use `Proc::call('sp_name', array(...))` for every database action, so the data security
  (views + stored procedures) is the same as before. Values are sent as `?` parameters (no SQL injection).
- Login is our own simple login (users table + `sp_login_user`), saved in the Laravel session.
- Every form has `@csrf` (Laravel's form protection).
- Pages that are not built yet (Settings, Help, Backup, Prescriptions) show `#` in the menu.
- Timezone is Asia/Manila (`APP_TIMEZONE` in `.env`).

## Face login (step 2 after the password)
1. Run `sql/face_login.sql` in phpMyAdmin.
2. Save `face-api.min.js` in `public/js` and the 10 model files in `public/models` (read the README.txt in both folders).
3. Open the system on `http://localhost:8000` (the browser only allows the camera on localhost or https).
4. Log in as Owner > User Management > **Register** (Face ID column): live face + blink, scan the Philippine National ID, tick consent, Save.
5. From now on that user signs in with password, then the face check. Users without a face still use the password only.
- Locked out (camera problem)? Set `FACE_LOGIN=false` in `.env` (then restart `php artisan serve`), or in phpMyAdmin run `DELETE FROM user_faces WHERE user_id = 1;`
- The comparison is done on the server (`FaceController`): login must be under 0.50, ID vs live face under 0.60 (see `app/helpers.php`).
- Only the 128 face numbers are saved. The face photo and the ID photo are never saved.
