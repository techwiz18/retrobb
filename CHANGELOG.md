# Changelog — RetroBB by techwiz.dad. Format follows keep-a-changelog loosely.

## [Unreleased]
- MySQL 8 / MariaDB 10.6+ is now the only backend (SQLite removed pre-1.0).
  Beta SQLite boards can be rescued with `php bin/import-sqlite.php`.
- Settings saves use native upserts (fixes unchanged-value saves failing).

### Ideas (noted 2026-10-09, not scheduled)
- Drag-and-drop forum/category reordering (replaces ↑ ↓ buttons).
- Permissions on categories and forums by group; general permission system.
- Plugin management UI (add/edit/remove from AdminCP).
- Appearance customization beyond the shipped skins.
- More visual breakup across forum appearance (experiment).

## [0.2.0-beta.1] — 2026-10-08 (pre-release)
First public pre-release. Tinkerers welcome; not production-hardened yet.

### Added
- Core forum: categories, forums, topics, posts, BBCode, profiles, member list.
- SEO URLs (`/forum/slug.f1`, `/topic/slug.t1`, `/members/name.u1`) with canonicals, sitemap.
- Hook/filter plugin API with hello-world example.
- Trust & safety: reports + mod queue, move/split/merge, warnings, temp/perm bans, mod log, flood control.
- Register hardening: honeypot plus pluggable CAPTCHA (builtin math, Turnstile, hCaptcha, reCAPTCHA).
- Category/forum ordering, post editing with window, dark mode (light/dark/auto).
- Profile editor (bio, email, password), member badges, SVG logo.
- MySQL 8 support (dialect migrations), SQLite WAL mode, lazy guest sessions.

### Fixed (found by testing this pre-release)
- Web installer fatal (double require), installer lock vs empty-DB dead end.
- Moderation 500s from strict-types route params; canonical pagination; open redirects.
- View-count inflation; ghost topics now redirect to the new location.
