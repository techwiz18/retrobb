<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Guard;
use RetroBB\Core\View;
use RetroBB\Models\Notification;

class AlertController
{
    public function index(): void
    {
        Guard::requireLogin('/alerts');
        Guard::requireFeature('alerts', '/alerts');
        $me = (int) Auth::user()['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Notification::list($me, $page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        // Visiting alerts clears the bell badge.
        Notification::markAllRead($me);
        View::render('alerts/index', [
            'items' => $data['items'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages,
            'pageTitle' => 'Alerts — ' . board_name(),
        ]);
    }
}
