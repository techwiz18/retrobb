<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Guard;
use RetroBB\Core\Slug;
use RetroBB\Models\Notification;
use RetroBB\Models\Post;
use RetroBB\Models\Reaction;
use RetroBB\Models\Topic;

class ReactionController
{
    public function toggle(int $postId): void
    {
        Guard::requireFeature('reactions', '/post/' . $postId . '/react');
        $post = Post::find($postId);
        if (!$post) {
            redirect('/');
        }
        $topic = Topic::find((int) $post['topic_id']);
        $back = ($topic ? Slug::topicUrl($topic) : '/') . '#p' . $postId;
        Guard::requireLogin($back);
        Guard::csrf($back);
        $me = (int) Auth::user()['id'];
        $res = Reaction::toggle($postId, $me, (string) ($_POST['reaction'] ?? 'like'));
        // Notify only on a fresh add, not on removal or kind-switching,
        // so toggling between reactions doesn't spam the author's alerts.
        if (($res['action'] ?? '') === 'added' && (int) $post['user_id'] !== $me) {
            Notification::create((int) $post['user_id'], $me, 'reaction', (int) $post['topic_id'], $postId);
        }
        redirect($back);
    }
}
