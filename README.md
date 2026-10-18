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
- Quiet by default: fast pages, no tracking, no ads, mobile-friendly
- Alerts, mentions, reactions, and private messages (with drafts, trash,
  and reply threading) — every one toggleable board-wide in AdminCP → Features
- Real "new post" markers: per-user read tracking, not just recent activity
- Search across titles and bodies; importers from phpBB and SMF
- Forgot-password flow, user post deletes (configurable window), bare URLs link themselves
- Registration spam protection: invisible honeypot, built-in human check,
  or plug in Cloudflare Turnstile / hCaptcha / reCAPTCHA
- Moderation done right: reports + mod queue, warnings, temp/perm bans,
  move/split/merge topics, full mod log
- Community plugins via a simple hooks system (see `plugins/hello-world/`)

---

## 🚀 Install it (5 minutes)

**You need:** any shared host or VPS with **PHP 8.1+** and **MySQL 8+**
(or MariaDB 10.6+ — most hosts include one of these).

**Option A — shared hosting (e.g. cPanel):**
1. Download the latest zip from the
   [releases page](https://github.com/techwiz18/retrobb/releases).
2. In cPanel → File Manager, open `public_html` and upload the zip there, then
   **Extract** it. Browse to your domain: the installer starts itself. (If
   your host lets you point the domain at a subfolder instead, aim it at the
   inner `public` folder — stricter, since forum code and data then live
   above the web root. Either layout works.)
3. Click through the installer: database details → board + look → features →
   owner account. It creates a starter category, forum, and welcome thread,
   then disables itself when done — no cleanup needed. Go make some boards!

**Option B — local test drive:**
```bash
git clone https://github.com/techwiz18/retrobb.git
cd retrobb
cp config.example.php config.php   # then edit it with your MySQL details
php bin/migrate.php --seed
php -S localhost:8000 -t public public/router.php
# open http://localhost:8000
```
The trailing `public/router.php` matters: without it the built-in server
404s pretty URLs like `/topic/...`. Migrations need `config.php` to exist,
so copy it first.

**Prefer the command line?** Copy `config.example.php` to `config.php` and fill
in your MySQL details (or set the `RETROBB_MYSQL_*` environment variables),
then run `php bin/migrate.php --seed`.

---

## 🧭 First steps after installing

1. **Change the admin password** — click your name → Edit profile.
2. **Make your boards** — AdminCP → Structure: add categories and forums, then
   order them with the ↑ ↓ buttons.
3. **Pick your look** — the footer switcher changes skins instantly; set the
    default in AdminCP → Features.
4. **Turn on spam protection** — AdminCP → Spam protection (start with the
   built-in human check; add Turnstile/hCaptcha keys when you go public).
5. **Appoint moderators** — AdminCP → Users → set someone to `mod`.
6. **Back up** — dump your MySQL database regularly (`mysqldump retrobb >
   backup.sql`), and keep a copy of `config.php` somewhere safe.

---

## ❓ FAQ

**Is it really free?** Yes — MIT license. Use it, modify it, sell hosting for
it. No catch.

**Do I need to know code?** No. If you can unzip a file and use cPanel, you can
run RetroBB. The code part is only for people who want to build plugins.

**Can I move my old phpBB/SMF/MyBB board over?** phpBB and SMF, yes —
AdminCP → Import walks you through a test run before anything is written.
MyBB is not supported yet; say so in
[issues](https://github.com/techwiz18/retrobb/issues) if you need it.

**Does it work on phones?** Yes — the retro look collapses into a mobile layout
automatically.

**How do I update?** Back up first (database dump + a copy of `config.php`),
then delete everything **except** `config.php`, `storage/`, `plugins/`, and
`public/avatars/` — your plugins, uploaded avatars, and stored data live in
those. Upload the new release over what remains, then update the database:
one click in AdminCP → Dashboard (“Database updates”), or `php bin/migrate.php`
over SSH. Your posts, users, and settings stay put: migrations only ever add,
and the settings seeder never overwrites.

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
  migrations/  Plain SQL, one dialect (MySQL 8 / MariaDB)
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
folder has the test suites (`vulntest.py` covers 43 security checks) — they
need a running board on `http://localhost:8080`. PHP 8.1+ (tested on 8.1 and 8.3).

---

## 🗺 What's next

- v0.4: search, importers (phpBB/SMF), forgot-password flow — shipped
- v0.5: moderation & admin — ModCP bans page and home, staff shields,
  super-admin tier, unban notes, ban expiry states; avatars (upload or link,
  admin caps, auto-resize); subpath deployment behind a reverse proxy;
  security pass (installer gates, login throttle, redirect choke point);
  version stamp + update check; mobile wrapping fixes
- v0.5.1: security/performance follow-up — server-side session expiry,
  search guardrails, paginated members/bans, sitemap caching —
  **0.5.0-beta.2** is the current beta
- v0.6 — moderation remainder (per-forum permissions, mass actions, soft
  deletions, MFA, email verification, maintenance mode) + structure & look:
  sub-forums, appearance customization, visual breakup, optional homepage
  link in the nav
- v0.7 — platform: plugin directory, API, translations
- v1.0: the masses-ready release 🎉

Vote on features in [issues](https://github.com/techwiz18/retrobb/issues).

## 📄 License

MIT — see [LICENSE](LICENSE).

## 🙏 Acknowledgments

RetroBB is human-directed and human-reviewed, but most of the code, docs, and
testing was pair-programmed with an AI assistant — **Muse Spark** (via
[OpenCode](https://opencode.ai)). If you deploy it, treat it like any
community project: review changes, keep backups, report issues.
