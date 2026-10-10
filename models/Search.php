<?php
declare(strict_types=1);

namespace RetroBB\Models;

use RetroBB\Core\Db;

/**
 * Keyword search over topic titles and post bodies (LIKE-based: predictable
 * at beta scale, no FULLTEXT stopword/min-length surprises). Every term must
 * appear in the title or in at least one post of the topic.
 */
class Search
{
    /** Split a query into up to 5 usable terms. Returns [] when unusable. */
    public static function terms(string $q): array
    {
        $q = trim(mb_substr($q, 0, 100));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $words = preg_split('/\s+/', $q) ?: [];
        $terms = [];
        foreach ($words as $w) {
            $w = trim(mb_substr($w, 0, 60));
            if (mb_strlen($w) >= 2) {
                $terms[] = $w;
            }
            if (count($terms) >= 5) {
                break;
            }
        }
        return $terms;
    }

    /** @return array{topics: array, total: int} */
    public static function query(array $terms, int $page = 1, int $perPage = 15): array
    {
        $pdo = Db::pdo();
        if (!$terms) {
            return ['topics' => [], 'total' => 0];
        }
        $where = [];
        $whereParams = [];
        foreach ($terms as $t) {
            // LIKE metacharacters in user input match literally, not as wildcards.
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $where[] = '(t.title LIKE ? OR EXISTS (SELECT 1 FROM posts p WHERE p.topic_id=t.id AND p.body_bbcode LIKE ?))';
            $whereParams[] = $like;
            $whereParams[] = $like;
        }
        $whereSql = implode(' AND ', $where);
        $cnt = $pdo->prepare("SELECT COUNT(DISTINCT t.id) c FROM topics t WHERE $whereSql");
        $cnt->execute($whereParams);
        $total = (int) $cnt->fetch()['c'];
        $offset = max(0, ($page - 1) * $perPage);
        // Title hits first, then most recently active.
        $orderParams = [];
        $titleOrd = [];
        foreach ($terms as $t) {
            $titleOrd[] = '(t.title LIKE ?)';
            $orderParams[] = '%' . addcslashes($t, '%_\\') . '%';
        }
        $orderSql = implode(' + ', $titleOrd);
        $st = $pdo->prepare(
            "SELECT t.id, t.title, t.slug, t.posts_count, t.last_post_at, f.id AS forum_id, f.name AS forum_name,
              ($orderSql) AS title_hits,
              (SELECT p.body_bbcode FROM posts p WHERE p.topic_id=t.id ORDER BY p.id LIMIT 1) AS snippet
             FROM topics t JOIN forums f ON f.id=t.forum_id
             WHERE $whereSql ORDER BY title_hits DESC, t.last_post_at DESC LIMIT ? OFFSET ?"
        );
        $i = 1;
        foreach (array_merge($whereParams, $orderParams) as $p) {
            $st->bindValue($i++, $p, \PDO::PARAM_STR);
        }
        $st->bindValue($i++, $perPage, \PDO::PARAM_INT);
        $st->bindValue($i++, $offset, \PDO::PARAM_INT);
        $st->execute();
        return ['topics' => $st->fetchAll(), 'total' => $total];
    }
}
