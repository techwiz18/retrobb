<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\Slug;
use RetroBB\Core\View;
use RetroBB\Models\Notification;
use RetroBB\Models\Post;
use RetroBB\Models\Reaction;
use RetroBB\Models\Topic;

class ReactionController
{
    public function toggle(int $postId): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        if (!feature('reactions')) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/post/' . $postId . '/react']);
            return;
        }
        $post = Post::find($postId);
        if (!$post) {
            redirect('/');
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $me = (int) Auth::user()['id'];
        $res = Reaction::toggle($postId, $me, (string) ($_POST['reaction'] ?? 'like'));
        // Notify only on a fresh add, not on removal or kind-switching,
        // so toggling between reactions doesn't spam the author's alerts.
        if (($res['action'] ?? '') === 'added' && (int) $post['user_id'] !== $me) {
            Notification::create((int) $post['user_id'], $me, 'reaction', (int) $post['topic_id'], $postId);
        }
        $topic = Topic::find((int) $post['topic_id']);
        redirect(($topic ? Slug::topicUrl($topic) : '/') . '#p' . $postId);
    }
}
