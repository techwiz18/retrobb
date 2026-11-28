<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Guard;
use RetroBB\Core\View;
use RetroBB\Models\Moderation;

/** Moderator control panel: mod-visible lists. Admins see it too. */
class ModController
{
    /** ModCP home: queue + bans at a glance. */
    public function index(): void
    {
        Guard::requireModerator();
        View::render('mod/index', [
            'openReports' => \RetroBB\Models\Report::openCount(),
            'activeBans' => Moderation::activeBanCount(),
            'pageTitle' => 'ModCP',
        ]);
    }

    /** Ban history with active / lifted / expired status + unban forms. */
    public function bans(): void
    {
        Guard::requireModerator();
        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil(Moderation::banCount() / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        View::render('mod/bans', [
            'bans' => Moderation::banPage($page, $perPage),
            'page' => $page,
            'pages' => $pages,
            'pageTitle' => 'ModCP — Bans',
        ]);
    }
}
