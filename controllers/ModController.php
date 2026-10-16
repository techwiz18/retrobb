<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Auth;
use RetroBB\Core\View;
use RetroBB\Models\Moderation;

/** Moderator control panel: mod-visible lists. Admins see it too. */
class ModController
{
    private function needMod(): bool
    {
        if (!Auth::isMod()) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return false;
        }
        return true;
    }

    /** ModCP home: queue + bans at a glance. */
    public function index(): void
    {
        if (!$this->needMod()) {
            return;
        }
        $active = 0;
        foreach (Moderation::banList(100) as $b) {
            if (empty($b['lifted_at']) && (empty($b['expires_at']) || strtotime($b['expires_at']) > time())) {
                $active++;
            }
        }
        View::render('mod/index', [
            'openReports' => \RetroBB\Models\Report::openCount(),
            'activeBans' => $active,
            'pageTitle' => 'ModCP',
        ]);
    }

    /** Ban history with active / lifted / expired status + unban forms. */
    public function bans(): void
    {
        if (!$this->needMod()) {
            return;
        }
        View::render('mod/bans', [
            'bans' => Moderation::banList(100),
            'pageTitle' => 'ModCP — Bans',
        ]);
    }
}
