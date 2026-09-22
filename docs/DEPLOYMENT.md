# Deploying EV Catalog on cPanel

This guide takes you from the downloaded package to a live site. It takes about 15 minutes.
No SSH, Composer, Node or build step is needed: everything is plain PHP + MySQL.

## Requirements

| Requirement | Minimum | Where to check in cPanel |
|---|---|---|
| PHP | **8.0** (8.1–8.3 recommended) | *Software → MultiPHP Manager* |
| PHP extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `json` (all standard) | *Software → Select PHP Version → Extensions* |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+ | *Databases → MySQL Databases* |
| Apache | `mod_rewrite` enabled (it is on every cPanel host) | — |

## Package contents

```
ev-catalog-cpanel.zip
└── ev-catalog/
    ├── INSTALL.txt             ← one-page quick install
    ├── public_html/            ← upload the CONTENTS of this folder to your web root
    │   ├── .htaccess           ← URL routing + blocks access to internal folders
    │   ├── index.php           ← front controller (all pages and /api/* go through it)
    │   ├── config.sample.php   ← copy to config.php and fill in
    │   ├── .env.example        ← alternative to config.php
    │   ├── app/                ← PHP source (protected by .htaccess)
    │   ├── views/              ← HTML templates (protected)
    │   ├── assets/             ← CSS + JS
    │   └── uploads/            ← admin image uploads (PHP execution disabled)
    ├── database/ev_catalog.sql ← schema + sample data + default admin
    └── docs/                   ← this guide, API reference, schema, wireframes
```

(The `public_html/` folder in the zip is the `ev-site/` folder of the repository.)

---

## Step 1: Create the database

1. cPanel → **Databases → MySQL® Databases**.
2. **Create New Database**: e.g. `evcatalog`. cPanel prefixes it with your account name, e.g. `myacct_evcatalog`. Note the full name.
3. **MySQL Users → Add New User**: e.g. `evuser`, which becomes `myacct_evuser`. Use the password generator and **copy the password**.
4. **Add User To Database**: select the user and the database, tick **ALL PRIVILEGES**, and save.

## Step 2: Import the SQL file

1. cPanel → **Databases → phpMyAdmin**.
2. Click your new database (`myacct_evcatalog`) in the left sidebar.
3. **Import** tab → *Choose File* → select `database/ev_catalog.sql` → **Import** (leave the defaults: format SQL, charset utf-8).
4. You should see 6 tables: `audit_log`, `brands`, `login_attempts`, `settings`, `users`, `vehicles`.

> ⚠️ The SQL file **drops and recreates** these tables. Only import it into an empty database, or into one you intend to reset.

## Step 3: Upload the website files

**Option A: File Manager (easiest)**

1. cPanel → **Files → File Manager**. Click **Settings** (top right) and tick **Show Hidden Files (dotfiles)**. Without this, `.htaccess` is invisible.
2. Open your **home directory** (the folder *above* `public_html`) and **Upload** `ev-catalog-cpanel.zip` there. Keeping it outside the web root means the SQL file and docs are never publicly reachable.
3. Right-click the zip → **Extract**. This creates `ev-catalog/`.
4. Open `ev-catalog/public_html/`, click **Select All**, then **Move** everything to `/public_html` (or `/public_html/ev` for a subfolder install).
5. When the site works, delete `ev-catalog/` and the zip from your home directory (download `database/ev_catalog.sql` first if you want to keep a copy).

**Option B: FTP.** Upload the *contents* of `public_html/` (including `.htaccess`) to your web root in binary mode.

**Installing in a subfolder** (e.g. `https://example.com/ev/`): put the files in `public_html/ev/` instead. The app detects the subfolder automatically. If links break on your host, set `'base_path' => '/ev'` in `config.php` and uncomment `RewriteBase /ev/` in `.htaccess`.

## Step 4: Configure: `config.php` **or** `.env`

Use **one** of the two. `config.php` takes priority if both exist.

### Option A: `config.php` (recommended)

In File Manager, right-click `config.sample.php` → **Copy** → name it `config.php`. Then **Edit** it:

```php
'app' => [
    'url'       => 'https://yourdomain.com',
    'base_path' => '',            // '/ev' if installed in public_html/ev
    'debug'     => false,         // keep false on a live site
    'timezone'  => 'Europe/London',
],
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'myacct_evcatalog', // full prefixed database name
    'user' => 'myacct_evuser',    // full prefixed user name
    'pass' => 'the-password-you-copied',
],
```

### Option B: `.env`

Copy `.env.example` to `.env` and edit it:

```ini
APP_URL=https://yourdomain.com
APP_BASE_PATH=
APP_DEBUG=false
DB_HOST=localhost
DB_DATABASE=myacct_evcatalog
DB_USERNAME=myacct_evuser
DB_PASSWORD=the-password-you-copied
```

Both files are blocked from web access by `.htaccess`.

## Step 5: Permissions

File Manager defaults are usually correct. Confirm:

| Path | Permission |
|---|---|
| Folders (`app`, `views`, `assets`, …) | `755` |
| Files (`*.php`, `*.css`, `*.js`, `.htaccess`) | `644` |
| `config.php` / `.env` | `640` (or `600` if your host runs PHP as your user, which most cPanel hosts do) |
| `uploads/` | `755`. It must be writable by PHP for image uploads |

## Step 6: Select PHP version

cPanel → **MultiPHP Manager** → tick your domain → choose **PHP 8.1** or newer → Apply.

## Step 7: Enable HTTPS

cPanel → **Security → SSL/TLS Status** → *Run AutoSSL*. When your site loads over `https://`, the session cookie is sent with the `Secure` flag automatically.

To force HTTPS, add this at the top of `.htaccess`, just after `RewriteEngine On`:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Step 8: First login

1. Visit `https://yourdomain.com/admin`.
2. Sign in with:
   - **Email:** `admin@example.com`
   - **Password:** `ChangeMe123!`
3. You are **forced to choose a new password** before the dashboard unlocks.
4. Go to **Users** → edit the admin → change the email to your real address.
5. Go to **Site settings** → set your site name, tagline, currency symbol and footer.
6. Go to **EVs** → edit or delete the sample vehicles and add your own.

Every change in the admin writes to MySQL immediately. The public site reads from MySQL on every request with no cache, so changes are live on the next page load.

---

## Verify the install

| Check | Expected |
|---|---|
| `https://yourdomain.com/` | Homepage with featured EVs |
| `https://yourdomain.com/api/meta` | JSON |
| `https://yourdomain.com/config.php` | **403 Forbidden** |
| `https://yourdomain.com/app/routes.php` | **403 Forbidden** |
| `https://yourdomain.com/admin` | Login screen |

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Setup required" page | `config.php` (or `.env`) is missing or `db.name` is empty. |
| 404 on every page except the homepage | `.htaccess` was not uploaded (enable *Show Hidden Files*) or `mod_rewrite` is off. In a subfolder, set `RewriteBase`. |
| "A database error occurred." | Wrong DB name, user or password. Remember the cPanel prefix (`myacct_`). Temporarily set `'debug' => true` to see the exact error, then set it back. |
| 500 Internal Server Error | Check *cPanel → Metrics → Errors*. Usually the PHP version is below 8.0, or a `php_flag` line is not allowed. If so, delete the `<IfModule mod_php…>` blocks from `uploads/.htaccess`. |
| Image upload fails | `uploads/` is not writable (set `755`), or the file is larger than `upload_max_filesize` (raise it in *MultiPHP INI Editor*). |
| "Your session has expired" on save | Reload the admin page. This can happen after the 2-hour idle timeout (`session.lifetime`). |
| Locked out after failed logins | Wait 15 minutes, or run `DELETE FROM login_attempts;` in phpMyAdmin. |
| Forgot the admin password | In phpMyAdmin run the SQL below. It resets the password to `ChangeMe123!` and forces a change at next login. |

```sql
UPDATE users SET password_hash = '$2y$12$ZsCmZuwwUcLTCqhe3H70FumaiQz6M9sk1O4PgLYqILkt31733.wGu',
                 must_change_password = 1, is_active = 1
WHERE email = 'admin@example.com';
```

## Security checklist

- [ ] Default admin password changed (enforced) and admin email updated
- [ ] `debug` is `false`
- [ ] HTTPS enabled and forced
- [ ] `/config.php`, `/.env` and `/app/` return 403
- [ ] Give content editors the **editor** role, not **admin**
- [ ] Enable cPanel **Backup** (or JetBackup) for files **and** the database

Built-in protections: bcrypt password hashing, prepared statements everywhere, CSRF tokens on every state-changing request, login throttling (5 failures per 15 minutes per IP), session ID regeneration on login, an idle session timeout, HttpOnly/SameSite cookies, security headers, image-only uploads verified by MIME and content, and PHP execution disabled in `uploads/`.

## Updating the site later

1. Back up the database (phpMyAdmin → Export) and `config.php` / `uploads/`.
2. Upload the new files over the old ones, **except** `config.php`, `.env` and `uploads/`.
3. **Do not** re-import `ev_catalog.sql`, because it resets all data.
