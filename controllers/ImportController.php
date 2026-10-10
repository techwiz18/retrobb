<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\BBCode;
use RetroBB\Core\Csrf;
use RetroBB\Core\Db;
use RetroBB\Core\Slug;
use RetroBB\Core\View;

class ImportController
{
    private const CHUNK = 500;

    private function guard(): void
    {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            exit;
        }
    }

    /** @return \RetroBB\Core\Import\Base */
    private function reader(array $cfg, \PDO $src): object
    {
        $cls = $cfg['source'] === 'smf' ? \RetroBB\Core\Import\Smf::class : \RetroBB\Core\Import\Phpbb::class;
        return new $cls($src, (string) ($cfg['prefix'] ?? ''), (string) $cfg['db']);
    }

    public function form(): void
    {
        $this->guard();
        View::render('admin/import', ['stage' => 'form', 'error' => null, 'pageTitle' => 'Import — AdminCP']);
    }

    /** Check the source database and preview counts. Credentials live in the session only. */
    public function test(): void
    {
        $this->guard();
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            redirect('/admin/import');
        }
        $source = (string) ($_POST['source'] ?? '');
        if (!in_array($source, ['phpbb', 'smf'], true)) {
            View::render('admin/import', ['stage' => 'form', 'error' => 'Pick phpBB or SMF.', 'pageTitle' => 'Import — AdminCP']);
            return;
        }
        $cfg = [
            'source' => $source,
            'host' => trim((string) ($_POST['host'] ?? '')),
            'port' => max(1, (int) ($_POST['port'] ?? 3306)),
            'db' => trim((string) ($_POST['db'] ?? '')),
            'user' => trim((string) ($_POST['user'] ?? '')),
            'pass' => (string) ($_POST['pass'] ?? ''),
            'prefix' => trim((string) ($_POST['prefix'] ?? '')),
        ];
        try {
            $src = \RetroBB\Core\Import\Base::connect($cfg);
            $reader = $this->reader($cfg, $src);
            $tables = $source === 'smf' ? \RetroBB\Core\Import\Smf::TABLES : \RetroBB\Core\Import\Phpbb::TABLES;
            $missing = $reader->missingTables($tables);
        } catch (\Throwable $t) {
            View::render('admin/import', ['stage' => 'form', 'error' => $t->getMessage(), 'pageTitle' => 'Import — AdminCP']);
            return;
        }
        if ($missing) {
            View::render('admin/import', [
                'stage' => 'form', 'error' => 'That database is missing: ' . implode(', ', $missing) . '. Wrong prefix?',
                'pageTitle' => 'Import — AdminCP',
            ]);
            return;
        }
        try {
            $counts = $reader->counts();
        } catch (\Throwable $t) {
            View::render('admin/import', ['stage' => 'form', 'error' => 'Could not read that board (' . $t->getMessage() . ')', 'pageTitle' => 'Import — AdminCP']);
            return;
        }
        $_SESSION['import_cfg'] = $cfg;
        $_SESSION['import_token'] = bin2hex(random_bytes(16));
        $_SESSION['import_tally'] = ['users' => 0, 'forums' => 0, 'topics' => 0, 'posts' => 0, 'skipped' => 0];
        // Imported logins need migration 011 on THIS board.
        try {
            $cols = Db::pdo()->query('SHOW COLUMNS FROM users LIKE \'auth_scheme\'')->fetchAll();
        } catch (\Throwable) {
            $cols = [];
        }
        if (!$cols) {
            View::render('admin/import', ['stage' => 'form', 'error' => 'This board needs `php bin/migrate.php` first (user accounts lack the login columns).', 'pageTitle' => 'Import — AdminCP']);
            return;
        }
        View::render('admin/import', [
            'stage' => 'counts', 'error' => null, 'counts' => $counts, 'source' => $source,
            'next' => '/admin/import/run?step=users&offset=0&t=' . $_SESSION['import_token'],
            'pageTitle' => 'Import — AdminCP',
        ]);
    }

    /**
     * Chunked import runner. Each request moves ≤500 rows then hands a
     * continuation link (auto-followed by JS, clickable without it). The
     * token binds every step to the admin session that started the run.
     */
    public function run(): void
    {
        $this->guard();
        $cfg = $_SESSION['import_cfg'] ?? null;
        $token = (string) ($_GET['t'] ?? '');
        if (!$cfg || !isset($_SESSION['import_token']) || !hash_equals((string) $_SESSION['import_token'], $token)) {
            redirect('/admin/import');
        }
        $step = (string) ($_GET['step'] ?? 'users');
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        if (!in_array($step, ['users', 'forums', 'topics', 'posts', 'recount'], true)) {
            redirect('/admin/import');
        }
        try {
            $src = \RetroBB\Core\Import\Base::connect($cfg);
            $reader = $this->reader($cfg, $src);
        } catch (\Throwable $t) {
            View::render('admin/import', ['stage' => 'form', 'error' => $t->getMessage(), 'pageTitle' => 'Import — AdminCP']);
            return;
        }
        $next = function (string $s, int $o) use ($token): string {
            return '/admin/import/run?step=' . $s . '&offset=' . $o . '&t=' . urlencode($token);
        };
        try {
            if ($step === 'users') {
                if ($offset === 0) {
                    $this->mapTable();
                    $this->ensureGuest();
                }
                $rows = $reader->fetchUsers($offset, self::CHUNK);
                foreach ($rows as $r) {
                    $this->importUser($r);
                }
                $this->bump('users', count($rows));
                $to = count($rows) === self::CHUNK
                    ? $next('users', $offset + self::CHUNK)
                    : $next('forums', 0);
                $this->progress('users', $to);
                return;
            }
            if ($step === 'forums') {
                [$cats, $forums] = $reader->fetchStructure();
                $catMap = [];
                foreach ($cats as $c) {
                    Db::pdo()->prepare('INSERT INTO categories (title, sort) VALUES (?,0)')->execute([$c['title']]);
                    $newId = (int) Db::pdo()->lastInsertId();
                    $this->putMap('cat', $c['old_id'], $newId);
                    $catMap[$c['old_id']] = $newId;
                }
                if (!$catMap) {
                    // A board with forums but no categories still needs a home.
                    Db::pdo()->prepare('INSERT INTO categories (title, sort) VALUES (?,0)')->execute(['Imported']);
                    $fallbackCat = (int) Db::pdo()->lastInsertId();
                } else {
                    $fallbackCat = (int) reset($catMap);
                }
                $n = 0;
                foreach ($forums as $f) {
                    $catId = $catMap[$f['catkey']] ?? $fallbackCat;
                    Db::pdo()->prepare('INSERT INTO forums (category_id, name, slug, description, sort) VALUES (?,?,?,?,0)')
                        ->execute([$catId, $f['name'], Slug::make($f['name']), $f['desc']]);
                    $this->putMap('forum', $f['old_id'], (int) Db::pdo()->lastInsertId());
                    $n++;
                }
                $this->bump('forums', count($cats) + $n);
                $this->progress('forums', $next('topics', 0));
                return;
            }
            if ($step === 'topics') {
                $rows = $reader->fetchTopics($offset, self::CHUNK);
                foreach ($rows as $r) {
                    $this->importTopic($r);
                }
                $this->bump('topics', count($rows));
                $to = count($rows) === self::CHUNK
                    ? $next('topics', $offset + self::CHUNK)
                    : $next('posts', 0);
                $this->progress('topics', $to);
                return;
            }
            if ($step === 'posts') {
                $rows = $reader->fetchPosts($offset, self::CHUNK);
                foreach ($rows as $r) {
                    $this->importPost($r);
                }
                $this->bump('posts', count($rows));
                $to = count($rows) === self::CHUNK
                    ? $next('posts', $offset + self::CHUNK)
                    : $next('recount', 0);
                $this->progress('posts', $to);
                return;
            }
            // recount: exact per-topic/forum counters (imports estimate first).
            $topics = Db::pdo()->query('SELECT id FROM topics ORDER BY id LIMIT 200 OFFSET ' . $offset)->fetchAll();
            foreach ($topics as $t) {
                \RetroBB\Models\Topic::recountTopic((int) $t['id']);
            }
            if (count($topics) === 200) {
                $this->progress('recount', $next('recount', $offset + 200));
                return;
            }
            foreach (Db::pdo()->query('SELECT id FROM forums')->fetchAll() as $f) {
                \RetroBB\Models\Topic::recountForum((int) $f['id']);
            }
            $tally = $_SESSION['import_tally'] ?? [];
            try {
                Db::pdo()->exec('DROP TABLE IF EXISTS import_map');
            } catch (\Throwable) {
            }
            $source = $cfg['source'];
            unset($_SESSION['import_cfg'], $_SESSION['import_token'], $_SESSION['import_tally']);
            \RetroBB\Core\Modlog::log((int) Auth::user()['id'], 'import', 'board', 0, $source . ' import finished');
            View::render('admin/import', [
                'stage' => 'done', 'error' => null, 'tally' => $tally, 'source' => $source,
                'pageTitle' => 'Import — AdminCP',
            ]);
        } catch (\Throwable $t) {
            View::render('admin/import', [
                'stage' => 'form', 'error' => 'Import stopped with an error (' . $t->getMessage() . '). Fix it and re-run — already-imported rows stay put.',
                'pageTitle' => 'Import — AdminCP',
            ]);
        }
    }

    private function progress(string $step, string $next): void
    {
        View::render('admin/import', [
            'stage' => 'run', 'error' => null, 'step' => $step,
            'tally' => $_SESSION['import_tally'] ?? [], 'next' => $next,
            'pageTitle' => 'Import — AdminCP',
        ]);
    }

    private function mapTable(): void
    {
        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS import_map (kind VARCHAR(20) NOT NULL, old_id INT NOT NULL, new_id INT NOT NULL, PRIMARY KEY (kind, old_id))');
    }

    private function putMap(string $kind, int $old, int $new): void
    {
        Db::pdo()->prepare('INSERT INTO import_map (kind, old_id, new_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE new_id=VALUES(new_id)')
            ->execute([$kind, $old, $new]);
    }

    private function getMap(string $kind, int $old): int
    {
        try {
            $st = Db::pdo()->prepare('SELECT new_id FROM import_map WHERE kind=? AND old_id=?');
            $st->execute([$kind, $old]);
            $row = $st->fetch();
            return $row ? (int) $row['new_id'] : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function guestId(): int
    {
        try {
            $row = Db::pdo()->query("SELECT id FROM users WHERE username='Guest' LIMIT 1")->fetch();
            if ($row) {
                return (int) $row['id'];
            }
        } catch (\Throwable) {
        }
        return 0;
    }

    /** Placeholder account owning guest-authored imports. Reused across runs. */
    private function ensureGuest(): void
    {
        if ($this->guestId() > 0) {
            return;
        }
        $name = 'Guest';
        $email = 'guest@example.invalid';
        $chk = Db::pdo()->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) LIMIT 1');
        $chk->execute([$name, $email]);
        if ($chk->fetch()) {
            $name = 'Guest_imported';
        }
        Db::pdo()->prepare('INSERT INTO users (username, email, password_hash, user_group, posts_count, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, password_hash_safe(bin2hex(random_bytes(16))), 'member', 0, date('Y-m-d H:i:s')]);
    }

    /** Free username+email for an import, suffixing on collision. */
    private function uniqueUser(string $name, string $email, int $oldId): array
    {
        $name = $name !== '' ? mb_substr($name, 0, 50) : 'imported-user-' . $oldId;
        $email = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : '';
        $chk = Db::pdo()->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) LIMIT 1');
        $try = $name;
        for ($i = 0; $i < 50; $i++) {
            $tryEmail = $i === 0 ? $email : '';
            $chk->execute([$try, $tryEmail]);
            if (!$chk->fetch()) {
                return [$try, $tryEmail !== '' ? $tryEmail : 'imported-' . $oldId . '@example.invalid'];
            }
            $try = mb_substr($name, 0, 40) . '_imported' . ($i > 0 ? $i : '');
        }
        return ['imported-user-' . $oldId, 'imported-' . $oldId . '@example.invalid'];
    }

    private function importUser(array $r): void
    {
        [$name, $email] = $this->uniqueUser($r['username'], $r['email'], $r['old_id']);
        Db::pdo()->prepare(
            "INSERT INTO users (username, email, password_hash, auth_scheme, passwd_salt, user_group, posts_count, created_at) VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$name, $email, $r['passhash'], $r['scheme'], $r['salt'], $r['group'], $r['posts'], $r['created']]);
        $this->putMap('user', $r['old_id'], (int) Db::pdo()->lastInsertId());
    }

    private function importTopic(array $r): void
    {
        $forumId = $this->getMap('forum', $r['forumkey']);
        if ($forumId <= 0) {
            $this->bump('skipped', 1);
            return;
        }
        $starter = $this->getMap('user', $r['starter_old']);
        if ($starter <= 0) {
            $starter = $this->guestId();
        }
        $title = $r['title'] !== '' ? mb_substr($r['title'], 0, 120) : '(no subject)';
        Db::pdo()->prepare(
            'INSERT INTO topics (forum_id, user_id, title, slug, pinned, locked, views, posts_count, created_at, last_post_at, last_post_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([$forumId, $starter, $title, Slug::make($title), $r['pinned'], $r['locked'], $r['views'], 0, $r['created'], $r['created'], $starter]);
        $this->putMap('topic', $r['old_id'], (int) Db::pdo()->lastInsertId());
    }

    private function importPost(array $r): void
    {
        $topicId = $this->getMap('topic', $r['topickey']);
        if ($topicId <= 0) {
            $this->bump('skipped', 1);
            return;
        }
        $author = $this->getMap('user', $r['author_old']);
        if ($author <= 0) {
            $author = $this->guestId();
        }
        Db::pdo()->prepare(
            'INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)'
        )->execute([$topicId, $author, $r['body'], BBCode::toHtml($r['body']), $r['created']]);
        Db::pdo()->prepare('UPDATE users SET posts_count=posts_count+1 WHERE id=?')->execute([$author]);
    }

    private function bump(string $key, int $n): void
    {
        $_SESSION['import_tally'][$key] = (($_SESSION['import_tally'][$key] ?? 0)) + $n;
    }
}
