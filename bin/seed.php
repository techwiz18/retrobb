<?php
declare(strict_types=1);
// Demo seed: admin + sample boards. Idempotent-ish (skips if users exist unless --force).

$root = dirname(__DIR__);
require_once $root . '/core/Db.php';
require_once $root . '/core/helpers.php';
require_once $root . '/core/Slug.php';
require_once $root . '/core/BBCode.php';
require_once $root . '/core/Hooks.php';

use RetroBB\Core\BBCode;
use RetroBB\Core\Db;
use RetroBB\Core\Slug;

$force = in_array('--force', $argv ?? [], true);
$pdo = Db::pdo();
$count = (int) $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
if ($count > 0 && !$force) {
    echo "Seed skipped: users already exist (use --force to reseed demo content).\n";
    return;
}

$now = date('Y-m-d H:i:s');

function mkuser(PDO $pdo, string $username, string $email, string $group, string $now): int
{
    $plain = $group === 'admin' ? 'admin123' : 'password123';
    $hash = password_hash_safe($plain);
    try {
        $st = $pdo->prepare('INSERT INTO users (username, email, password_hash, user_group, created_at) VALUES (?,?,?,?,?)');
        $st->execute([$username, $email, $hash, $group, $now]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $t) {
        $st = $pdo->prepare('SELECT id FROM users WHERE username=?');
        $st->execute([$username]);
        return (int) $st->fetch()['id'];
    }
}

$adminId = mkuser($pdo, 'admin', 'admin@example.com', 'admin', $now);
$modId = mkuser($pdo, 'PixelMod', 'mod@example.com', 'mod', $now);
$u1 = mkuser($pdo, 'dialup_dan', 'dan@example.com', 'member', $now);
$u2 = mkuser($pdo, 'Guest_2003', 'guest@example.com', 'member', $now);

$pdo->exec('DELETE FROM posts');
$pdo->exec('DELETE FROM topics');
$pdo->exec('DELETE FROM forums');
$pdo->exec('DELETE FROM categories');

$cats = ['RetroBB Community', 'Old Internet Lounge'];
$catIds = [];
foreach ($cats as $i => $c) {
    $st = $pdo->prepare('INSERT INTO categories (title, sort) VALUES (?,?)');
    $st->execute([$c, $i]);
    $catIds[] = (int) $pdo->lastInsertId();
}

$forums = [
    [$catIds[0], 'Announcements', 'News, releases and roadmap updates from the team.', 0],
    [$catIds[0], 'General Chat', 'Pull up a chair. Anything goes (within the rules).', 1],
    [$catIds[1], 'Test Zone', 'Try BBCode, embeds and skins here. Go wild.', 2],
];
$forumIds = [];
foreach ($forums as $f) {
    $st = $pdo->prepare('INSERT INTO forums (category_id, name, slug, description, sort) VALUES (?,?,?,?,?)');
    $st->execute([$f[0], $f[1], Slug::make($f[1]), $f[2], $f[3]]);
    $forumIds[] = (int) $pdo->lastInsertId();
}

function mktopic(PDO $pdo, int $forumId, int $userId, string $title, string $bbcode, string $now, int $views = 0): int
{
    $st = $pdo->prepare('INSERT INTO topics (forum_id, user_id, title, slug, views, posts_count, created_at, last_post_at, last_post_user_id) VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([$forumId, $userId, $title, Slug::make($title), $views, 0, $now, $now, $userId]);
    $tid = (int) $pdo->lastInsertId();
    $html = BBCode::toHtml($bbcode);
    $p = $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)');
    $p->execute([$tid, $userId, $bbcode, $html, $now]);
    $pdo->prepare('UPDATE topics SET posts_count=1 WHERE id=?')->execute([$tid]);
    return $tid;
}

function reply(PDO $pdo, int $topicId, int $userId, string $bbcode, string $now): void
{
    $html = BBCode::toHtml($bbcode);
    $p = $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)');
    $p->execute([$topicId, $userId, $bbcode, $html, $now]);
    $pdo->prepare('UPDATE topics SET posts_count=posts_count+1, last_post_at=?, last_post_user_id=? WHERE id=?')->execute([$now, $userId, $topicId]);
    $pdo->prepare('UPDATE users SET posts_count=posts_count+1 WHERE id=?')->execute([$userId]);
}

$t1 = mktopic($pdo, $forumIds[0], $adminId, 'Welcome to RetroBB — read this first!', "Welcome to [b]RetroBB[/b]! :D\n\nThis is a from-scratch forum with an old-school soul:\n\n[list][*]Gradient title bars and post rows[*]BBCode, quotes and code blocks[*]SEO-friendly URLs like /topic/welcome-to-retrobb.t1[/list]\n\nTry a reply below. Be kind.", $now, 128);
reply($pdo, $t1, $u1, 'First! This takes me right back to 2003. The gradient bars... [i]chef kiss[/i] :)', $now);
reply($pdo, $t1, $modId, '[quote=dialup_dan]This takes me right back[/quote] Right? Wait until you try the [b]Midnight[/b] and [b]Silver[/b] skins in the footer switcher.', $now);

$t2 = mktopic($pdo, $forumIds[1], $u1, 'What was your first forum?', "Mine was a grainy IPB 1.3 board about skateboarding. 56k modem, one phone line, mum shouting to get off the internet. :P\n\nWhat was yours?", $now, 42);
reply($pdo, $t2, $u2, 'SMF board about flip phones. My signature was 12 lines long and nobody stopped me.', $now);
reply($pdo, $t2, $adminId, 'phpBB2 + Atomic Tangerine skin. Never forget.', $now);

$t3 = mktopic($pdo, $forumIds[2], $u2, 'BBCode test thread', "Testing:\n\n[b]bold[/b], [i]italic[/i], [u]underline[/u]\n\n[code]echo \"hello 2003\";[/code]\n\n[url=https://techwiz.dad]techwiz.dad[/url]\n\n:) :( :D", $now, 7);

// counters
foreach ($forumIds as $fid) {
    $tc = (int) $pdo->query("SELECT COUNT(*) c FROM topics WHERE forum_id=$fid")->fetch()['c'];
    $pc = (int) $pdo->query("SELECT COUNT(*) c FROM posts p JOIN topics t ON t.id=p.topic_id WHERE t.forum_id=$fid")->fetch()['c'];
    $last = $pdo->query("SELECT id FROM topics WHERE forum_id=$fid ORDER BY last_post_at DESC LIMIT 1")->fetch();
    $pdo->prepare('UPDATE forums SET topics_count=?, posts_count=?, last_topic_id=? WHERE id=?')->execute([$tc, $pc, $last['id'] ?? null, $fid]);
}
$pdo->exec('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');

echo "Seeded. Login: admin / admin123  (members: password123)\n";
