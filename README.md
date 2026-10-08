# 💾 RetroBB — the old-school forum, reborn

**RetroBB** is free, open-source forum software with the soul of the early-2000s
internet — gradient title bars, postbit profiles, BBCode, the works — rebuilt
from scratch with modern code underneath. No accounts to create, no cloud to
join, no monthly bill: you host it, you own it.

MIT licensed. Made with nostalgia by [techwiz.dad](https://techwiz.dad).

---

## ✨ What's inside

**The retro stuff**
- Classic board index → forums → topics → posts, just like you remember
- Three built-in skins: **Classic**, **Midnight**, and **Silver**
- Light / Dark / Auto theme that follows your device
- BBCode (`[b]`, `[quote]`, `[code]`, `[url]`, …), postbit mini-profiles, member list

**The modern comforts**
- SEO-friendly URLs (`/topic/welcome-to-retrobb.t1`) that survive renames
- Alerts-free simplicity: fast pages, no tracking, no ads, mobile-friendly
- Registration spam protection: invisible honeypot, built-in human check,
  or plug in Cloudflare Turnstile / hCaptcha / reCAPTCHA
- Moderation done right: reports + mod queue, warnings, temp/perm bans,
  move/split/merge topics, full mod log
- Community plugins via a simple hooks system (see `plugins/hello-world/`)

---

## 🚀 Install it (5 minutes)

**You need:** any shared host or VPS with **PHP 8.1+** and SQLite (already on
most hosts), or MySQL if you prefer.

**Option A — upload (typical shared hosting, e.g. cPanel):**
1. Download the latest zip from the
   [releases page](https://github.com/techwiz18/retrobb/releases).
2. Upload it to your hosting and unzip.
3. Point your domain at the **`public/` folder** (this part matters — not the
   top folder, the one called `public`).
4. Visit `yoursite.com/install.php` and click **Install now**.
5. Log in as `admin` / `admin123`, change the password, and **delete
   `public/install.php`**. Done — go make some boards!

**Option B — local test drive:**
```bash
git clone https://github.com/techwiz18/retrobb.git
cd retrobb
php bin/migrate.php --seed
php -S localhost:8000 -t public/
# open http://localhost:8000 — login admin / admin123
```

**MySQL instead of SQLite?** Copy `config.example.php` to `config.php` and fill
in your database details (or set the `RETROBB_MYSQL_*` environment variables),
then run `php bin/migrate.php --seed`.

---

## 🧭 First steps after installing

1. **Change the admin password** — click your name → Edit profile.
2. **Make your boards** — AdminCP → Structure: add categories and forums, then
   order them with the ↑ ↓ buttons.
3. **Pick your look** — the footer switcher changes skins instantly; set the
   default in AdminCP → Settings.
4. **Turn on spam protection** — AdminCP → Spam protection (start with the
   built-in human check; add Turnstile/hCaptcha keys when you go public).
5. **Appoint moderators** — AdminCP → Users → set someone to `mod`.
6. **Back up** — copy the database file regularly (SQLite: the `.sqlite` file
   in `storage/`; that's the whole forum).

---

## ❓ FAQ

**Is it really free?** Yes — MIT license. Use it, modify it, sell hosting for
it. No catch.

**Do I need to know code?** No. If you can unzip a file and use cPanel, you can
run RetroBB. The code part is only for people who want to build plugins.

**Can I move my old phpBB/SMF/MyBB board over?** Not yet — importers are on the
[roadmap](#-whats-next). Tell us which one you need in
[issues](https://github.com/techwiz18/retrobb/issues).

**Does it work on phones?** Yes — the retro look collapses into a mobile layout
automatically.

**How do I update?** Back up first, then overwrite the files with the new
release (keep `config.php` and `storage/`), and run `php bin/migrate.php`.
Your posts and users stay put.

**Something broke?** Check that `storage/` is writable by the web server, and
look at the PHP error log. Still stuck?
[Open an issue](https://github.com/techwiz18/retrobb/issues).

---

## 🧩 For developers

```
retrobb/
  core/        Router, DB, Auth, BBCode, Hooks, Captcha…
  controllers/ One per area (Forum, Topic, Admin, …)
  models/      Board, Topic, Post, User, Report, Moderation
  views/       Plain PHP templates, no build step
  public/      Web root (index.php, .htaccess, assets)
  migrations/  Plain SQL, SQLite + MySQL dialects
  plugins/     Drop-in extensions
```

**Plugins in 30 seconds** — add a folder in `plugins/` with `plugin.json` and
`bootstrap.php`:

```php
use RetroBB\Core\Hooks;
Hooks::add_action('footer', fn() => print('Hello from my plugin!'));
Hooks::add_filter('post_body_html', fn($html) => $html); // transform post HTML
```

**Contributing:** bug reports and pull requests welcome. The `scripts/`
folder has the test suites (`vulntest.py` covers 33 security checks) — they
need a running board on `http://localhost:8080`. PHP 8.1–8.4 supported.

---

## 🗺 What's next

- v0.3: alerts, mentions, reactions, private messages
- v0.4: search, importers (phpBB/SMF)
- v0.5: plugin directory, API, translations
- v1.0: the masses-ready release 🎉

Vote on features in [issues](https://github.com/techwiz18/retrobb/issues).

## 📄 License

MIT — see [LICENSE](LICENSE).
