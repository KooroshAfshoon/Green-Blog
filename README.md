# Green Blog

A lightweight personal blog engine built with plain PHP (PDO/MySQL) and vanilla JS — no framework, no build step. Right-to-left, Persian-first UI with a Vazir web font.

## Features

**Writing & content**
- Posts support a lightweight markup of their own:
  - `[IMAGE]` / `[IMAGE 2]` placeholders to position uploaded images inline in the text
  - `[code lang]...[/code]` blocks, rendered with a language label and a monospaced code box
  - `#hashtag` style tags, auto-extracted from the post body — no separate tag field
- Multi-image upload per post, with an optional **ASCII-art render mode**: any uploaded image can be converted to a text-based ASCII rendering instead of being stored as-is
- Reading-time estimate shown on each post
- Pinning posts to the top of the feed

**Reading experience**
- Live client-side search across post titles/content on the feed page
- Clickable tag chips that filter the feed by tag
- Terminal-style "typing" animation when a post loads
- Reading-progress bar
- One-click "share as image" — snapshots the post with html2canvas and copies it to the clipboard

**Engagement & stats**
- Like / unlike posts (AJAX, one click, no reload)
- View tracking, both per-post and site-wide, de-duplicated by IP for 30 minutes so refreshing doesn't inflate counts
- Admin stats dashboard: views today / yesterday / last 7 days / last 30 days / all-time, a 14-day views chart, total likes, and a per-post breakdown (views + likes) with a most-viewed ranking

**Admin**
- Simple session-based admin panel — create, edit, and delete posts from one screen
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

## Writing a post

A couple of things worth knowing when writing content from the admin panel:

- Drop `[IMAGE]` (for the first image) or `[IMAGE 2]`, `[IMAGE 3]`, etc. anywhere in the post text to place that image at that exact spot. Any image not referenced this way just isn't shown inline.
- Wrap code with `[code php]your code here[/code]` — the word after `code` becomes the label shown above the block (`php`, `bash`, `js`, or anything you like).
- Tags are just `#word` anywhere in the text — they're stripped from the visible text and rendered as clickable chips instead.

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

MIT — see [LICENSE](LICENSE).
