# Green Blog

A lightweight personal blog engine built with plain PHP (PDO/MySQL) and vanilla JS — no framework, no build step. Right-to-left, Persian-first UI with a Vazir web font.

## Features

- Post feed with pinning, tag filtering, and per-post pages
- Like / unlike posts (AJAX, one click, no reload)
- View tracking (per-post and site-wide, deduplicated by IP for 30 minutes)
- Multi-image uploads per post, with an optional **ASCII-art render mode** for images
- Hashtag-style tags (`#php #linux`) auto-extracted from post content
- Simple session-based admin panel (create / edit / delete posts, stats dashboard)
- Clean URLs for posts (`/post/42`) via `.htaccess`

## Requirements

- PHP 7.4+ (uses `PDO`, `password_verify`, `mbstring`, GD for ASCII conversion)
- MySQL / MariaDB
- Apache with `mod_rewrite` (for the `.htaccess` clean-URL rule) — or adapt the rewrite rule for nginx

## Setup

1. **Clone the repo** and upload it to your PHP host, or serve it locally.

2. **Create a database** and import the schema:
   ```bash
   mysql -u youruser -p your_database < schema.sql
   ```

3. **Create your local config:**
   ```bash
   cp config.example.php config.php
   ```
   Edit `config.php` and fill in your real database credentials and admin login.

   To generate the admin password hash:
   ```bash
   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   Paste the result into `admin_pass_hash` in `config.php`.

4. **Make `uploads/` and `ascii/` writable** by the web server:
   ```bash
   chmod 755 uploads ascii
   ```

5. Visit `index.php` in your browser. Log in at `login.php` with the admin credentials you set in `config.php` to reach the admin panel.

## Project structure

```
index.php            Blog feed (list of posts, tag filter)
post.php             Single post view
admin.php            Admin panel — create posts, stats
edit.php             Edit / delete an existing post
login.php            Admin login
like.php             AJAX endpoint for like/unlike
track_view.php       View-tracking logic (included by index.php / post.php)
helpers.php          Tag extraction, reading-time estimate, tag-chip rendering
ascii_converter.php  Converts an uploaded image to ASCII art
db.php               Loads config.php and opens the PDO connection
config.example.php   Template — copy to config.php and fill in your values
schema.sql           Database schema
style.css / script.js
fonts/Vazir.ttf       Persian web font
uploads/, ascii/      Runtime-generated content (git-ignored, kept via .gitkeep)
```

## Security notes

- `config.php` is git-ignored — never commit real database credentials or the admin password hash.
- The admin password is stored as a bcrypt hash (`password_hash` / `password_verify`), not in plain text.
- If you fork this project, treat `login.php` as a starting point only — for anything public-facing, consider adding rate limiting / CSRF protection on top.

## License

No license specified yet — add one (MIT, for example) if you plan to accept contributions or want to make the terms explicit.
