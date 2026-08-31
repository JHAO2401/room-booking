# RoomPlate — Discussion Room Booking Website

A PHP + MySQL + vanilla JS/HTML/CSS website for booking discussion rooms.
Users register as Student or Faculty; only Faculty accounts can also book
internal-only rooms (e.g. the Boardroom).

## Features

- **Public browsing** — anyone can browse bookable rooms without an account.
- **Sign up / log in** — visitors register as Student or Faculty; Faculty
  accounts can also book internal-only rooms, which stay hidden from and
  unbookable by Student accounts.
- **Live slot picker** — pick a date, click a start slot then an end slot;
  already-booked ranges are shown as taken (AJAX call to `api/check_availability.php`).
- **Booking requests** — go in as `pending`, preventing double-booking of the
  same room/time even under a race condition (checked again server-side).
- **My Bookings** — users can view and cancel their own bookings.
- **Admin dashboard** — approve/reject pending requests, see stats, manage
  rooms (add/edit/disable), all protected behind an `admin` role.

## Tech stack

Plain PHP 8 (PDO + MySQL), vanilla JavaScript, hand-written CSS. No framework,
no build step — easy to deploy on almost any AWS compute option.

## Project structure

```
room-booking/
├── index.php            # only entry point kept at the root
├── admin/                # admin-only pages (dashboard, rooms, bookings, room_form)
├── api/                  # small JSON endpoints used by the JS slot picker
├── assets/css/js/        # styling and front-end interactivity
├── config/db.php         # PDO connection (reads env vars)
├── database/schema.sql   # tables + seed data (rooms + a default admin)
├── includes/             # shared header/footer/functions (auth, csrf, uploads, etc.)
├── images/
│   ├── logo/              # drop a logo.png/.svg here — header picks it up automatically
│   ├── backgrounds/       # drop a background image here — used as a subtle site backdrop
│   └── rooms/             # room photos uploaded via the admin "Add/Edit room" form
├── booking/
│   ├── room.php            # room detail + booking form
│   └── my_bookings.php     # a user's own bookings
├── login_register/
│   ├── login.php
│   ├── register.php
│   └── logout.php
```

### Logo & background images

Drop an image file into `images/logo/` (e.g. `logo.png`) and/or
`images/backgrounds/` (e.g. `hero.jpg`) — the header automatically detects
and uses the first image file it finds in each folder, no code change
needed. Remove the file to go back to the plain text/letter styling.

### Room photos (admin)

In **Admin → Manage rooms → Add/Edit room**, there's a photo upload field.
Accepted formats: JPG, PNG, WEBP, up to 5MB. Uploaded photos are saved to
`images/rooms/` with a random filename and the path is stored in the
`rooms.image_url` column. Uploading a new photo replaces the old one; you
can also tick "Remove current photo" to clear it. Make sure `images/rooms/`
is writable by the web server user in your deployment (e.g.
`chown www-data:www-data images/rooms` on an EC2/Apache setup).

## Running it locally

You need PHP 8+ and MySQL/MariaDB.

```bash
# 1. Create the database and seed data
mysql -u root -p < database/schema.sql

# 2. Point the app at your DB (or just edit config/db.php directly)
export DB_HOST=127.0.0.1
export DB_NAME=room_booking
export DB_USER=root
export DB_PASS=yourpassword

# 3. Run PHP's built-in server from the project folder
php -S localhost:8000

# 4. Visit http://localhost:8000/index.php
```

Default admin login: **admin@example.com / Admin@123** — change this password
immediately (see `database/generate_admin_hash.php` to create a new hash).

## Deploying on AWS

There are two straightforward paths. Both work well for a PHP + MySQL app
like this one.

### Option A — Simplest: Elastic Beanstalk (PHP platform) + RDS

1. **Database:** Create an **Amazon RDS for MySQL** instance (free-tier
   eligible `db.t3.micro` is fine to start). Note its endpoint, username,
   password, and database name.
2. Connect to it (e.g. from a bastion/Cloud9) and run `database/schema.sql`
   against it to create the tables.
3. **App hosting:** Create an **Elastic Beanstalk** environment with the
   "PHP" platform. Zip up this project folder (excluding `.git`) and upload
   it as your application version.
4. In the Beanstalk environment's **Configuration → Software**, add
   environment properties: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   (and `DB_PORT` if not 3306) pointing at your RDS instance.
5. Beanstalk gives you a public URL immediately. Add a custom domain via
   **Route 53** + an SSL cert from **AWS Certificate Manager** attached to
   the environment's load balancer when you're ready to go live.

### Option B — More control: EC2 (LAMP) + RDS

1. Launch an **EC2** instance (Ubuntu, `t3.micro` is enough to start).
2. Install a LAMP-ish stack:
   ```bash
   sudo apt update
   sudo apt install -y apache2 php php-mysql libapache2-mod-php mysql-client
   sudo systemctl enable apache2 --now
   ```
3. Copy this project into `/var/www/html/` (e.g. via `scp` or `git clone`).
4. Set `DB_HOST` etc. as environment variables for Apache (e.g. via
   `SetEnv` directives in the Apache vhost, or a `.env`-loading snippet at
   the top of `config/db.php` — happy to add one if you'd like).
5. Create an **RDS for MySQL** instance as in Option A, open its security
   group to accept connections from the EC2 instance's security group, and
   run `database/schema.sql` against it.
6. Open port 80/443 on the EC2 security group, point a domain at its
   Elastic IP, and add HTTPS with **Let's Encrypt (certbot)** or an
   **Application Load Balancer** + ACM certificate in front of the instance.

### A note on file structure for Apache

If you deploy to Apache directly, make sure the **document root points at
this `room-booking/` folder itself** (not a parent folder), since the app
uses root-relative paths like `/assets/css/style.css` and `/login_register/login.php`.

## Security notes before going live

- Change the default admin password immediately.
- Make sure `display_errors` is off in production PHP settings.
- Serve the site over HTTPS only (both options above support this).
- The app already uses prepared statements (PDO) and CSRF tokens on every
  form; keep both patterns if you extend it.
