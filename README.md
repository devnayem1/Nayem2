# LuckBox – setup (PHP 8+ / MySQL, shared hosting)
1. In cPanel create a MySQL database + user. Import `database.sql` (phpMyAdmin > Import).
2. Edit `config.php` (DB_HOST, DB_NAME, DB_USER, DB_PASS).
3. Upload everything in this folder to `public_html` (keep `uploads/` writable, chmod 755 or 775).
4. Open `https://yoursite/admin.php` once: create your admin (password min 10 chars, stored with password_hash).
5. Admin > Prizes: add/edit/delete, upload images. Admin > Odds: set % (active total must be <= 100, remainder = no prize). Admin > Statistics/Export CSV: Dashboard + "Export CSV".
6. Use HTTPS (needed for the player-side verify button).
## How the draw works
`api.php?a=start` picks the result with PHP `random_int()`, stores it, and returns only a SHA-256 commitment of `seed|roll|prizeId|winningBox`. `a=pick` validates the box once (transactional, a round cannot be replayed), records the result and then reveals the data so the player can re-hash it.
## Troubleshooting
"Database connection failed": check config.php. Upload fails: make `uploads/` writable. Blank API errors: see PHP error log.
