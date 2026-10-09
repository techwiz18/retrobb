<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\View;
use RetroBB\Models\Board;

class HomeController
{
    public function index(): void
    {
        $cats = Board::index();
        $stats = Board::stats();
        $me = \RetroBB\Core\Auth::user();
        View::render('home/index', [
            'cats' => $cats,
            'stats' => $stats,
            'unreadForums' => $me ? \RetroBB\Models\TopicRead::forumsUnread((int) $me['id']) : [],
            'pageTitle' => board_name() . ' — ' . setting('board_tagline', ''),
            'metaDesc' => setting('board_tagline', 'An old-school forum'),
        ]);
    }
}
