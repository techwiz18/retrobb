# RetroBB by techwiz.dad

An old-school forum with an early-2000s soul, written from scratch in pure PHP 8.3+.
No framework, no build step, shared-host safe. MIT licensed.

## Quick start (local)

```bash
cd retrobb
docker run --rm -v $PWD:/app -w /app php:8.3-cli php bin/migrate.php --seed
docker run --rm -p 8080:8000 -v $PWD:/app -w /app php:8.3-cli php -S 0.0.0.0:8000 -t public
# open http://localhost:8080 — login admin / admin123
```

Or with local PHP: `php bin/migrate.php --seed && php -S localhost:8000 -t public/`.
Or via browser: open `/install.php` once.

## SEO URLs (in v0.1)

- `/forum/{slug}.f{id}` · `/topic/{slug}.t{id}` (`/page-N` suffix) · `/members/{name}.u{id}`
- ID is authoritative; wrong slug 301s to canonical. Legacy `viewtopic.php?t=` 301s too.
- Apache: `public/.htaccess` included. Nginx: see `docs/nginx-snippet.conf`.

## Plugins

Drop a folder in `plugins/` with `plugin.json` + `bootstrap.php`:

```php
use RetroBB\Core\Hooks;
Hooks::add_action('footer', fn() => print('hi'));
Hooks::add_filter('post_body_html', fn($h) => $h);
```

See `plugins/hello-world/`.

## Skins

Footer switcher or `/skin/{classic,midnight,silver}` — original retro-inspired designs in `public/assets/style-retro.css`.

## Roadmap

- v0.1 core forum (this) · v0.2 trust & safety (reports, mod queue, bans) · v0.3 alerts/mentions/reactions/PMs
  · v0.4 search/importers · v0.5 marketplace/API/i18n · v1.0 masses-ready.
