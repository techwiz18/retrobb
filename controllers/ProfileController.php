<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\User;
use RetroBB\Core\Db;

class ProfileController
{
    public function index(): void
    {
        View::render('profile/index', ['users' => User::all(200), 'pageTitle' => 'Members — ' . board_name()]);
    }

    public function show(string $segment): void
    {
        $parsed = Slug::parseSuffixed($segment, 'u');
        if (!$parsed) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/members/' . $segment]);
            return;
        }
        [$uname, $id] = $parsed;
        $user = User::find($id);
        if (!$user) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/members/' . $segment]);
            return;
        }
        $canonical = Slug::memberUrl($user);
        if (\RetroBB\Core\Router::currentPath() !== $canonical) {
            redirect($canonical, 301);
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT t.id, t.title, t.slug, p.created_at FROM posts p JOIN topics t ON t.id=p.topic_id WHERE p.user_id=? ORDER BY p.id DESC LIMIT 10');
        $st->execute([$id]);
        View::render('profile/show', [
            'profile' => $user,
            'recent' => $st->fetchAll(),
            'pageTitle' => $user['username'] . ' — ' . board_name(),
            'canonical' => canonical_url($canonical),
        ]);
    }
}
