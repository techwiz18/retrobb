<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\Board;

class ForumController
{
    public function show(string $segment): void
    {
        $parsed = Slug::parseSuffixed($segment, 'f');
        if (!$parsed) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/forum/' . $segment]);
            return;
        }
        [$slug, $id] = $parsed;
        $forum = Board::forum($id);
        if (!$forum) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/forum/' . $segment]);
            return;
        }
        $canonical = Slug::forumUrl($forum);
        $path = \RetroBB\Core\Router::currentPath();
        $base = strtok($path, '?');
        if ($base !== $canonical) {
            redirect($canonical, 301);
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(5, min(50, (int) setting('topics_per_page', '25')));
        $data = Board::topics($id, $page, $perPage);
        $pages = (int) ceil($data['total'] / $perPage);
        View::render('forum/show', [
            'forum' => $forum,
            'topics' => $data['topics'],
            'total' => $data['total'],
            'page' => $page,
            'pages' => $pages,
            'pageTitle' => $forum['name'] . ' — ' . board_name(),
            'metaDesc' => $forum['description'] ?: ('Topics in ' . $forum['name']),
            'canonical' => canonical_url($canonical . ($page > 1 ? '?page=' . $page : '')),
        ]);
    }
}
