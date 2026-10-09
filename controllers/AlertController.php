<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\View;
use RetroBB\Models\Notification;

class AlertController
{
    public function index(): void
    {
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/alerts'));
        }
        if (!feature('alerts')) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/alerts']);
            return;
        }
        $me = (int) Auth::user()['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Notification::list($me, $page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        Notification::markAllRead($me);
        View::render('alerts/index', [
            'items' => $data['items'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages,
            'pageTitle' => 'Alerts — ' . board_name(),
        ]);
    }
}
