# Changelog — RetroBB by techwiz.dad. Format follows keep-a-changelog loosely.

## [Unreleased]

### Added
- Continuous integration (`.github/workflows/ci.yml`): on every push and PR it
  runs the README/source doccheck, the main/live branch invariant, PHP lint,
  and the full regression suite against a fresh seeded board.
- Deterministic regression suite. `scripts/testenv.sh` builds a fresh, seeded
  `retrobb_test` board (canonical ids) on an isolated port, and
  `scripts/runtests.py` runs every suite against it in any order and exits
  non-zero on the first broken check. The suites share `scripts/_harness.py`
  and are self-contained — no hardcoded fixture ids, no dependence on another
  suite's leftovers — so they no longer rot against a drifted database.

### Changed
- The README opens with a **Status** section: RetroBB is beta, with a plain list
  of what is not built yet (email verification, maintenance mode, per-forum
  permissions, mass actions, soft deletions, sub-forums, MFA), the mobile
  caveat, importer gaps, and the GD requirement for avatar resizing. Invalid
  claims are now caught mechanically: `doccheck` fails if the README advertises
  stability while `RETROBB_VERSION` is a pre-release.

### Security
- Stored XSS in bare `[url]…[/url]` links. The decoded URL was emitted as the
  link text without escaping, so HTML in the URL executed (`[url]http://x/…`).
  The fallback label is now escaped; `vulntest` covers the script and
  `img onerror` payloads; `php bin/rebuild-html.php` re-renders already-stored
  post/PM HTML (run it once after upgrading an existing board).
- Password-reset links no longer fall back to the request `Host`. With
  `board_url` blank, canonicals are relative, the sitemap omits its locs (its
  cache key now includes `board_url`), and reset mail refuses to send rather
  than mailing a link an attacker could point at their own host. The dashboard
  always warns when Board URL is unset. `scripts/hostcheck.py` covers it.
- State-changing GET routes now require POST plus a CSRF token. Import runs
  (`/admin/import/run`) no longer accept GET, so the session token stops
  leaking through Referer and server logs. The skin and theme switchers
  (`/skin/…`, `/theme/…`) likewise move to POST, closing the
  preference-fixation path where any page could embed the URL and pin a
  visitor's skin or theme. Read-markers (alerts, PM open, view counts) stay on
  GET by design.

## [0.5.0-beta.2] — 2026-10-18 (pre-release)
Security and performance follow-up to 0.5.0-beta.1, plus the first batch of
moderation fixes. Sessions now expire on the server, and the scans and
listings that a busy board hits hardest are bounded. No migration changes —
drop the files over any 0.5 beta.

### Added
- AdminCP plugin page: enable or disable installed plugins, super-admin only.
- ModCP home page and navigation, so the mod queue, bans, and reports have a
  front door instead of being reachable only by direct URL.

### Changed
- Sessions expire server-side: 30 minutes idle, 7 days absolute. Configurable
  with `session_idle_seconds` / `session_absolute_seconds` in `config.php`
  (or `RETROBB_SESSION_IDLE` / `RETROBB_SESSION_ABSOLUTE`); `0` disables
  either limit. Existing sessions adopt the clock on their next request
  instead of being logged out by the upgrade.
- Search stops at 50 pages. Deep `OFFSET`s over a leading-wildcard scan cost
  more than the results are worth.
- Members index pages at 50 per page instead of loading 200 accounts per hit.
- PM drafts tab counts with `COUNT(*)` instead of fetching 50 full rows.
- Ban history pages at 50 per page in both ModCP and AdminCP.
- Sitemap sends `Cache-Control` (one hour), `ETag`, and `Last-Modified`, and
  answers conditional requests with `304 Not Modified`.
- AdminCP dashboard warns when `board_url` is blank, since canonical links
  and password-reset emails otherwise build from the request `Host` header.
- Login, registration, mention, and import lookups no longer wrap the column
  in `LOWER()`. The collation is already case-insensitive, so the wrapper only
  forced a full table scan and ignored the unique indexes.

### Fixed
- Topic pagination stuck past page 2: page links used `?page=N`, which the
  `/topic/…/page-N` route never matched.
- Links dropped the deployment subpath when the board runs under one
  (e.g. `/forum`): pagination, the profile Edit button, and password-reset
  links in mail.
- Thread deletion now removes its posts, so post counts refund correctly.
- Super-admin flag could resurrect when a demoted user was re-promoted.
- CAPTCHA secret was readable by non-super admins on the spam page.
- Installer trusted a stale session, and `?force=0` did not re-run setup.
- Linked avatars accepted beacons disguised behind an image extension.
- `public/avatars/` had no `.htaccess` guard against script execution.
- Topic merge search treated `%` and `_` in the query as SQL wildcards.

## [0.5.0-beta.1] — 2026-10-15 (pre-release)
Moderation, avatars, subpath deployment, and a security pass. Tinkerers
welcome; back up before upgrading.

### Added
- ModCP bans page (mod-visible, admins included) with active / ended /
  expired states; unban notes on both mod and admin paths.
- Staff shields: nobody warns admins, mods can't ban staff, all group
  changes and site settings gated to the new super-admin tier (first admin
  bootstrapped, grant/revoke in AdminCP → Users).
- Avatars via upload (JPG/PNG/GIF, byte-validated, GD auto-resize when
  available) or link, with admin caps for size, dimensions, and per-mode
  toggles; display follows the caps in posts, profiles, and members.
- Subpath deployment (`RETROBB_BASE_PATH`) for reverse-proxy setups, with
  canonical-host enforcement and proxy-header exemption.
- Installer hardening: silent bounce for outsiders, super-admin + password
  + emailed code step-up for reinstalls, dashboard delete-installer notice.
- Login brute-force throttle (IP-backed), native DB prepares, secure
  session cookies under HTTPS, session rotation on password change.
- Version stamp plus cached latest-release check on the dashboard.
- Mobile: long titles/URLs wrap, stacked stats left-align, balanced footer.

### Changed
- Stock palette tracks the shared hub design tokens (all skins × themes,
  contrast-checked); active skin/theme now marked in the switcher.
- Forum topbar/category bars render from theme variables; footer unboxed.
- Release zips no longer ship the hello-world example or site plugins.

### Upgrading
- Run the database updates after upload (AdminCP → Dashboard, or
  `php bin/migrate.php`): migrations 013–016 add the super flag, avatar
  columns, login throttle table, and hot-path indexes. Back up first.

## [0.4.0-beta.3] — 2026-10-11 (pre-release)
Follow-up: deleting posts and topics hands each author their post count
back (floored at zero). No migration changes — drop over any 0.4 beta,
no database update needed.

## [0.4.0-beta.2] — 2026-10-11 (pre-release)
Small follow-up: staff can delete any post (was author-only), topic/reply
creation rejects user id 0, uniform button sizing in postbit and modbar.
No migration changes since beta.1 — drop the files over beta.1 and go
(no database update needed unless skipped).

## [0.4.0-beta.1] — 2026-10-11 (pre-release)
Search, importers, and account recovery. Tinkerers welcome; not production-hardened yet.

### Added
- Search across topic titles and post bodies (multi-term, snippets,
  query-preserving pagination, nav link).
- Importers for phpBB 3.x and SMF 2.x: stepped AdminCP run (users, forums,
  topics, posts, recount) with counts preview, id mapping, guest fallback,
  conflict renaming, and skipped-content notes. Legacy passwords keep working
  and upgrade silently on first login.
- Forgot-password flow (single-use emailed tokens, 1-hour expiry, throttles,
  enumeration-safe replies) plus password-confirm on profile changes.
- User post deletes with a configurable window (opening posts protected).
- Bare URLs auto-link; BBCode toolbar on the new-topic form.
- Shared-hosting front door (package-root `.htaccess` + fallback) and a lean
  release packager (`scripts/package-release.sh`).
- Drop-in upgrades: settings seeder never overwrites, migrations heal
  charsets, one-click database updates in AdminCP → Dashboard.

### Fixed
- Password storage review: no plaintext anywhere — modern hashes, one-way
  legacy imports, hashed reset tokens.
- Emoticon emoji no longer degrade to `?` (forced utf8mb4 connections,
  database/table healing in migrate).
- `Edited NULL` / `NULL` bio phantoms can no longer render; saves canonicalize.
- Fresh uploads without `config.php` route to the installer (was a 500).

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
- Fixed: `php bin/migrate.php --seed` on a fresh database created the demo
  `admin` account without the super-admin tier, so AdminCP → Settings,
  Features, and Spam protection returned 403 on a brand-new board until
  `migrate.php` was run a second time. The super-admin bootstrap now also runs
  after the demo seed.
- MySQL 8 / MariaDB 10.6+ is now the only backend (SQLite removed pre-1.0).
  Beta SQLite boards can be rescued with `php bin/import-sqlite.php`.
- Settings saves use native upserts (fixes unchanged-value saves failing).

### Ideas (noted 2026-10-09, not scheduled)
- Drag-and-drop forum/category reordering (replaces ↑ ↓ buttons).
- Permissions on categories and forums by group; general permission system.
- Plugin management UI (add/edit/remove from AdminCP).
- Appearance customization beyond the shipped skins.
- More visual breakup across forum appearance (experiment).
- Forgot-password flow (email reset tokens).
- Mass actions on topics/posts (bulk move/delete/lock/pin from forum and mod views).
- Soft deletions (recoverable hides instead of hard deletes) → v0.5.
- Sub-forums (nested forum hierarchy; importers currently flatten).
- Optional homepage link in the nav (for boards with a dedicated home page) → v0.6.
- MFA via TOTP (opt-in per user, enforceable for staff roles) → v0.5.
- Query batching pass (mention lookups, PM tab counts, board subselects).
- Login throttle that survives cookie drops (persistent/IP-based).
- Reply alerts to all thread participants (currently the starter only).
- Import re-run guard (retries currently duplicate).
- Email verification (activation links; gated posting) → v0.5.

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
