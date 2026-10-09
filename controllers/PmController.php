<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\Csrf;
use RetroBB\Core\View;
use RetroBB\Models\Pm;

class PmController
{
    /** 404 when the owner disabled PMs board-wide. Returns false if handled. */
    private function guard(string $path): bool
    {
        if (!feature('pms')) {
            http_response_code(404);
            View::render('errors/404', ['path' => $path]);
            return false;
        }
        return true;
    }

    /** Counts for the Inbox/Sent/Drafts/Trash tabs. */
    private function tabs(int $me): array
    {
        return [
            'unread' => Pm::unreadCount($me),
            'sent' => Pm::sentCount($me),
            'drafts' => count(Pm::drafts($me)),
            'trash' => Pm::trashCount($me),
        ];
    }

    public function inbox(): void
    {
        if (!$this->guard('/pm')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm'));
        }
        $me = (int) Auth::user()['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Pm::inbox($me, $page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        View::render('pm/index', [
            'items' => $data['items'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages, 'tabs' => $this->tabs($me),
            'pageTitle' => 'Private messages — ' . board_name(),
        ]);
    }

    public function sent(): void
    {
        if (!$this->guard('/pm/sent')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm/sent'));
        }
        $me = (int) Auth::user()['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Pm::outbox($me, $page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        View::render('pm/sent', [
            'items' => $data['items'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages, 'tabs' => $this->tabs($me),
            'pageTitle' => 'Sent messages — ' . board_name(),
        ]);
    }

    public function trash(): void
    {
        if (!$this->guard('/pm/trash')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm/trash'));
        }
        $me = (int) Auth::user()['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = Pm::trash($me, $page);
        $pages = max(1, (int) ceil($data['total'] / 25));
        View::render('pm/trash', [
            'items' => $data['items'], 'total' => $data['total'],
            'page' => $page, 'pages' => $pages, 'tabs' => $this->tabs($me), 'me' => $me,
            'pageTitle' => 'Deleted messages — ' . board_name(),
        ]);
    }

    public function show(int $id): void
    {
        if (!$this->guard('/pm/' . $id)) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login');
        }
        $me = (int) Auth::user()['id'];
        $pm = Pm::findFor($id, $me);
        if (!$pm) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/pm/' . $id]);
            return;
        }
        if ((int) $pm['recipient_id'] === $me) {
            Pm::markRead($id, $me);
        }
        $parent = null;
        if (!empty($pm['reply_to_id'])) {
            $parent = Pm::findFor((int) $pm['reply_to_id'], $me);
        }
        $otherName = (int) $pm['sender_id'] === $me ? $pm['recipient_name'] : $pm['sender_name'];
        View::render('pm/show', [
            'pm' => $pm, 'parent' => $parent, 'replies' => Pm::replies($id, $me), 'otherName' => $otherName,
            'pageTitle' => 'PM — ' . board_name(),
        ]);
    }

    public function delete(int $id): void
    {
        if (!$this->guard('/pm/' . $id)) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login');
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $me = (int) Auth::user()['id'];
        if (Pm::deleteFor($id, $me)) {
            $_SESSION['flash_ok'] = 'Message deleted.';
        }
        redirect('/pm');
    }

    public function newForm(): void
    {
        if (!$this->guard('/pm/new')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm/new'));
        }
        $me = (int) Auth::user()['id'];
        $to = trim((string) ($_GET['to'] ?? ''));
        $subject = '';
        $body = '';
        $replyTo = max(0, (int) ($_GET['reply_to'] ?? 0));
        if ($replyTo > 0) {
            $parent = Pm::findFor($replyTo, $me);
            if ($parent) {
                $to = (int) $parent['sender_id'] === $me ? $parent['recipient_name'] : $parent['sender_name'];
                $subject = (string) $parent['subject'];
                if (!preg_match('/^re:/i', $subject)) {
                    $subject = 'Re: ' . $subject;
                }
                $quoted = \RetroBB\Core\BBCode::stripQuotes((string) $parent['body_bbcode']);
                $body = '[quote=' . (string) $parent['sender_name'] . ']' . $quoted . "[/quote]\n\n";
            } else {
                $replyTo = 0;
            }
        }
        View::render('pm/new', [
            'to' => $to, 'subject' => $subject, 'body' => $body, 'reply_to' => $replyTo, 'error' => null,
            'pageTitle' => 'New message — ' . board_name(),
        ]);
    }

    public function newSubmit(): void
    {
        if (!$this->guard('/pm/new')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login');
        }
        $me = (int) Auth::user()['id'];
        $toName = trim((string) ($_POST['to'] ?? ''));
        $replyTo = max(0, (int) ($_POST['reply_to'] ?? 0));
        $post = $_POST;
        $compose = function ($error) use ($toName, $replyTo, $post) {
            return [
                'to' => $toName,
                'subject' => (string) ($post['subject'] ?? ''),
                'body' => (string) ($post['body'] ?? ''),
                'reply_to' => $replyTo,
                'error' => $error,
                'pageTitle' => 'New message',
            ];
        };
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            View::render('pm/new', $compose('Session expired.'));
            return;
        }
        // "Save draft" keeps the half-written message (and its reply link).
        if (isset($_POST['save'])) {
            $res = Pm::saveDraft($me, $toName, (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''), 0, $replyTo);
            if (!$res['ok']) {
                View::render('pm/new', $compose($res['error']));
                return;
            }
            $_SESSION['flash_ok'] = 'Draft saved.';
            redirect('/pm/draft/' . $res['id']);
        }
        $to = Pm::resolveRecipient($toName);
        if (!$to) {
            View::render('pm/new', $compose('Recipient not found.'));
            return;
        }
        $res = Pm::send($me, (int) $to['id'], (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''), $replyTo);
        if (!$res['ok']) {
            View::render('pm/new', $compose($res['error']));
            return;
        }
        $_SESSION['flash_ok'] = 'Message sent.';
        redirect('/pm/' . $res['id']);
    }

    public function drafts(): void
    {
        if (!$this->guard('/pm/drafts')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm/drafts'));
        }
        $me = (int) Auth::user()['id'];
        View::render('pm/drafts', [
            'items' => Pm::drafts($me), 'tabs' => $this->tabs($me),
            'pageTitle' => 'Drafts — ' . board_name(),
        ]);
    }

    public function draftForm($id = null): void
    {
        if (!$this->guard('/pm/draft')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login?next=' . urlencode('/pm/drafts'));
        }
        $me = (int) Auth::user()['id'];
        $draft = $id ? Pm::findDraft((int) $id, $me) : null;
        if ($id && !$draft) {
            http_response_code(404);
            View::render('errors/404', ['path' => '/pm/draft/' . (int) $id]);
            return;
        }
        $blank = ['id' => 0, 'to_name' => trim((string) ($_GET['to'] ?? '')), 'subject' => '', 'body_bbcode' => '', 'reply_to_id' => 0];
        View::render('pm/draft', [
            'draft' => $draft ? $draft : $blank,
            'error' => null,
            'pageTitle' => ($draft ? 'Edit draft' : 'New draft') . ' — ' . board_name(),
        ]);
    }

    /** Save (or send) a draft. Two submit buttons: "Save draft" and "Send". */
    public function draftSubmit($id = null): void
    {
        if (!$this->guard('/pm/draft')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login');
        }
        $me = (int) Auth::user()['id'];
        $draftId = $id ? (int) $id : 0;
        $draftReplyTo = max(0, (int) ($_POST['reply_to'] ?? 0));
        $post = $_POST;
        $vars = [
            'draft' => [
                'id' => $draftId,
                'to_name' => (string) ($post['to'] ?? ''),
                'subject' => (string) ($post['subject'] ?? ''),
                'body_bbcode' => (string) ($post['body'] ?? ''),
                'reply_to_id' => $draftReplyTo,
            ],
            'error' => null,
            'pageTitle' => 'Draft — ' . board_name(),
        ];
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            $vars['error'] = 'Session expired.';
            View::render('pm/draft', $vars);
            return;
        }
        $send = ($_POST['send'] ?? '') !== '';
        if ($send) {
            $to = Pm::resolveRecipient((string) ($_POST['to'] ?? ''));
            if (!$to) {
                $vars['error'] = 'Recipient not found.';
                View::render('pm/draft', $vars);
                return;
            }
            $res = Pm::send($me, (int) $to['id'], (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''), $draftReplyTo);
            if (!$res['ok']) {
                $vars['error'] = $res['error'];
                View::render('pm/draft', $vars);
                return;
            }
            if ($draftId) {
                Pm::discardDraft($draftId, $me);
            }
            $_SESSION['flash_ok'] = 'Message sent.';
            redirect('/pm/' . $res['id']);
        }
        $res = Pm::saveDraft($me, (string) ($_POST['to'] ?? ''), (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''), $draftId, $draftReplyTo);
        if (!$res['ok']) {
            $vars['error'] = $res['error'];
            View::render('pm/draft', $vars);
            return;
        }
        $_SESSION['flash_ok'] = 'Draft saved.';
        redirect('/pm/draft/' . $res['id']);
    }

    public function draftDiscard(int $id): void
    {
        if (!$this->guard('/pm/draft')) {
            return;
        }
        if (!Auth::check()) {
            redirect('/login');
        }
        if (!Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        Pm::discardDraft($id, (int) Auth::user()['id']);
        $_SESSION['flash_ok'] = 'Draft discarded.';
        redirect('/pm/drafts');
    }
}
