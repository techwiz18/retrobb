# Changelog — RetroBB by techwiz.dad. Format follows keep-a-changelog loosely.

## [0.3.0-beta.1] — 2026-10-09 (pre-release)
Social batch plus board control. Tinkerers welcome; not production-hardened yet.

### Added
- Mentions (@username links + alerts), alerts bell/dropdown (mention, reply,
  reaction, warning), reactions (like/thanks/funny), PMs (inbox/sent/trash,
  drafts, reply threading with quotes, unsent-changes guard).
- Per-user read tracking: real New markers on the index and forum lists
  (posts predating your account are never "new").
- Board-wide feature flags (alerts, PMs, reactions, mentions, skin selector,
  theme modes) with a stepped installer (all-on opt-outs) and a dedicated
  AdminCP → Features page; default theme always coerced to an allowed mode.
- SMF-style index: per-forum status icons, one-line Last-post cells, Info
  Center with newest-member link.
- Installer: stepped wizard (database → board → features → owner) with
  per-step validation, skin previews, welcome category/forum/thread, and
  self-disabling on success.
- Moderation: resolving a report requires a note; queue warn uses a
  mod-written reason; warnings link their reported post and fire alerts;
  unbans lift (history kept in Bans + mod log).
- BBCode: nested tags pair innermost-first, block-aware paragraphs, reply
  quotes collapse to one level, pre-fix stored HTML repairs itself on display.
- Migrations 005–010 (notifications, reactions, PMs, drafts, threading,
  read tracking, notification detail, warning context).

### Fixed (found by review + vulntest this cycle)
- Mentions no longer fire inside tag attributes, code blocks, or emails.
- Reactions notify on fresh adds only; PMs share the post flood control;
  blank drafts rejected; `.uID` recipient must match the name/slug.
- Installer writes an allowlisted skin; urlencode hrefs and layout class escaped.

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
