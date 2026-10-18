<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\View;
use RetroBB\Models\Search;

class SearchController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $terms = Search::terms($q);
        $page = max(1, min(Search::MAX_PAGE, (int) ($_GET['page'] ?? 1)));
        $data = ['topics' => [], 'total' => 0];
        $error = null;
        if ($q !== '' && !$terms) {
            $error = 'Search for 2 or more characters.';
        } elseif ($terms) {
            $data = Search::query($terms, $page);
        }
        $pages = Search::pageCount($data['total']);
        View::render('search/index', [
            'q' => mb_substr($q, 0, 100), 'terms' => $terms,
            'topics' => $data['topics'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages, 'error' => $error,
            'pageTitle' => ($q !== '' ? 'Search: ' . mb_substr($q, 0, 40) . ' — ' : 'Search — ') . board_name(),
        ]);
    }
}
