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
        if (!\is_canonical($canonical)) {
            redirect($canonical, 301);
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT t.id, t.title, t.slug, p.created_at FROM posts p JOIN topics t ON t.id=p.topic_id WHERE p.user_id=? ORDER BY p.id DESC LIMIT 10');
        $st->execute([$id]);
        $isMod = \RetroBB\Core\Auth::isMod();
        $me = \RetroBB\Core\Auth::user();
        $isSelf = $me !== null && (int) $me['id'] === $id;
        View::render('profile/show', [
            'profile' => $user,
            'recent' => $st->fetchAll(),
            'warnings' => ($isMod || $isSelf) ? \RetroBB\Models\Moderation::warningsFor($id) : [],
            'activeBan' => ($isMod || $isSelf) ? \RetroBB\Models\Moderation::activeBan($id) : null,
            'pageTitle' => $user['username'] . ' — ' . board_name(),
            'canonical' => canonical_url($canonical),
        ]);
    }

    private function needMod(): bool
    {
        if (!\RetroBB\Core\Auth::isMod()) {
            http_response_code(403);
            \RetroBB\Core\View::render('errors/403', ['pageTitle' => 'Forbidden']);
            return false;
        }
        return true;
    }

    public function warn(int $id): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $user = User::find($id);
        if (!$user) {
            redirect('/members');
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 3) {
            $_SESSION['flash_error'] = 'Give a short warning reason.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        if (!\RetroBB\Models\Moderation::warn($id, (int) \RetroBB\Core\Auth::user()['id'], $reason)) {
            $_SESSION['flash_error'] = $user['user_group'] === 'admin'
                ? 'Admins cannot be warned.'
                : 'Only super admins can warn staff.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $_SESSION['flash_ok'] = 'Warning recorded.';
        redirect(\RetroBB\Core\Slug::memberUrl($user));
    }

    public function ban(int $id): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $user = User::find($id);
        if (!$user) {
            redirect('/members');
        }
        if ($user['user_group'] === 'admin') {
            $_SESSION['flash_error'] = 'Admins cannot be banned here.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $daysRaw = trim((string) ($_POST['days'] ?? ''));
        $days = $daysRaw === '' ? null : max(1, min(3650, (int) $daysRaw));
        if (mb_strlen($reason) < 3) {
            $_SESSION['flash_error'] = 'Give a short ban reason.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $banId = \RetroBB\Models\Moderation::ban($id, (int) \RetroBB\Core\Auth::user()['id'], $reason, $days);
        if ($banId === 0) {
            $_SESSION['flash_error'] = $user['user_group'] === 'admin'
                ? 'Admins cannot be banned here.'
                : 'Only super admins can ban staff.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        \RetroBB\Core\Auth::refresh($user);
        $_SESSION['flash_ok'] = 'User banned.';
        redirect(\RetroBB\Core\Slug::memberUrl($user));
    }

    public function unban(int $banId): void
    {
        if (!$this->needMod()) {
            return;
        }
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF mismatch';
            return;
        }
        $unbannedId = \RetroBB\Models\Moderation::unban($banId, (int) \RetroBB\Core\Auth::user()['id'], (string) ($_POST['reason'] ?? ''));
        $_SESSION['flash_ok'] = 'Ban lifted.';
        // Land on the member profile: mods can see it, while /admin/bans
        // is admin-only (bouncing a mod there is a 403).
        $banUser = $unbannedId > 0 ? User::find($unbannedId) : null;
        redirect($banUser ? \RetroBB\Core\Slug::memberUrl($banUser) : '/members');
    }

    public function settingsForm(): void
    {
        $me = \RetroBB\Core\Auth::user();
        if (!$me) {
            redirect('/login?next=' . urlencode('/settings/profile'));
        }
        $user = User::find((int) $me['id']);
        \RetroBB\Core\View::render('profile/edit', [
            'edituser' => $user, 'pageTitle' => 'Edit profile — ' . board_name(),
            'error' => null,
        ]);
    }

    public function settingsSubmit(): void
    {
        $me = \RetroBB\Core\Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $user = User::find((int) $me['id']);
        if (!\RetroBB\Core\Csrf::verify($_POST['csrf'] ?? null)) {
            \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => 'Session expired.']);
            return;
        }
        $which = (string) ($_POST['form'] ?? 'profile');
        if ($which === 'password') {
            if ((string) ($_POST['new'] ?? '') !== (string) ($_POST['new2'] ?? '')) {
                \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => 'New passwords do not match.']);
                return;
            }
            $res = User::changePassword((int) $me['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''));
            if (!$res['ok']) {
                \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => $res['error']]);
                return;
            }
            $_SESSION['flash_ok'] = 'Password changed.';
            redirect(\RetroBB\Core\Slug::memberUrl($user));
        }
        $res = null;
        if ($which === 'avatar') {
            $err = $this->handleAvatar((int) $me['id'], $user);
            if ($err !== null) {
                \RetroBB\Core\View::render('profile/edit', ['edituser' => User::find((int) $me['id']), 'pageTitle' => 'Edit profile', 'error' => $err]);
                return;
            }
            $_SESSION['flash_ok'] = 'Avatar updated.';
            redirect(\RetroBB\Core\Slug::memberUrl(User::find((int) $me['id'])));
        }
        $res = User::updateProfile((int) $me['id'], trim((string) ($_POST['email'] ?? '')), (string) ($_POST['bio'] ?? ''));
        if (!$res['ok']) {
            \RetroBB\Core\View::render('profile/edit', ['edituser' => $user, 'pageTitle' => 'Edit profile', 'error' => $res['error']]);
            return;
        }
        $_SESSION['flash_ok'] = 'Profile updated.';
        redirect(\RetroBB\Core\Slug::memberUrl(User::find((int) $me['id'])));
    }

    /**
     * Avatar choice (own profile only — settingsSubmit already scoped).
     * Returns an error string, or null on success.
     */
    private function handleAvatar(int $id, array $user): ?string
    {
        $kind = (string) ($_POST['avatar_kind'] ?? 'none');
        if ($kind === 'upload' && !feature('avatars_upload')) {
            return 'Avatar uploads are disabled.';
        }
        if ($kind === 'link' && !feature('avatars_link')) {
            return 'Linked avatars are disabled.';
        }
        if ($kind !== 'upload' && $kind !== 'link') {
            self::dropAvatarFile((string) ($user['avatar_file'] ?? ''));
            User::updateAvatar($id, 'none', '', '');
            return null;
        }
        if ($kind === 'link') {
            $url = mb_substr(trim((string) ($_POST['avatar_url'] ?? '')), 0, 500);
            if (!preg_match('#^https?://[^/]+/.+#i', $url) || !preg_match('#\.(jpe?g|png|gif|webp|avif)(\?.*)?$#i', $url)) {
                return 'Give a direct https link ending in .jpg, .png, .gif, .webp or .avif.';
            }
            self::dropAvatarFile((string) ($user['avatar_file'] ?? ''));
            User::updateAvatar($id, 'link', $url, '');
            return null;
        }
        // Upload: validate the actual bytes, never the client-supplied name.
        $f = $_FILES['avatar'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Choose an image file to upload.';
        }
        $limits = avatar_limits();
        if ((int) ($f['size'] ?? 0) > $limits['bytes']) {
            return 'That file is over the ' . (int) round($limits['bytes'] / 1024) . ' KB limit.';
        }
        $info = @getimagesize((string) ($f['tmp_name'] ?? ''));
        $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
        if ($info === false || !isset($map[$info[2]])) {
            return 'Only JPG, PNG and GIF uploads are accepted.';
        }
        $dir = dirname(__DIR__) . '/public/avatars';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return 'Avatar storage is unavailable — tell the site owner.';
        }
        try {
            $name = 'u' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $map[$info[2]];
        } catch (\Throwable) {
            return 'Could not store that file — try again.';
        }
        $err = self::storeAvatarFile($f, $info, $map[$info[2]], $limits, $dir . '/' . $name);
        if ($err !== null) {
            return $err;
        }
        self::dropAvatarFile((string) ($user['avatar_file'] ?? ''));
        User::updateAvatar($id, 'upload', '', $name);
        return null;
    }

    /**
     * Move (or downscale) an upload into place. Returns an error or null.
     * Oversized images are resized when GD exists, rejected otherwise.
     * Animated GIFs collapse to their first frame on resize.
     */
    private static function storeAvatarFile(array $f, array $info, string $ext, array $limits, string $dest): ?string
    {
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($w <= $limits['width'] && $h <= $limits['height']) {
            if (!@move_uploaded_file($tmp, $dest)) {
                return 'Could not store that file — try again.';
            }
        } else {
            if (!avatar_resize_available()) {
                return 'That image is ' . $w . '×' . $h . ' — max is ' . $limits['width'] . '×' . $limits['height']
                    . 'px. Please shrink it and try again.';
            }
            if ($w <= 0 || $h <= 0 || $w * $h > 25000000) {
                return 'That image is too large to process.';
            }
            $scale = min($limits['width'] / $w, $limits['height'] / $h);
            $nw = max(1, (int) floor($w * $scale));
            $nh = max(1, (int) floor($h * $scale));
            $src = match ($ext) {
                'jpg' => @imagecreatefromjpeg($tmp),
                'png' => @imagecreatefrompng($tmp),
                default => @imagecreatefromgif($tmp),
            };
            if ($src === false) {
                return 'Could not read that image.';
            }
            $dst = @imagecreatetruecolor($nw, $nh);
            if ($dst === false) {
                imagedestroy($src);
                return 'Could not process that image.';
            }
            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            } elseif ($ext === 'gif') {
                $tr = imagecolortransparent($src);
                if ($tr >= 0) {
                    $tc = imagecolorsforindex($src, $tr);
                    $fill = imagecolorallocate($dst, $tc['red'], $tc['green'], $tc['blue']);
                    imagefill($dst, 0, 0, (int) $fill);
                    imagecolortransparent($dst, (int) $fill);
                }
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = match ($ext) {
                'jpg' => @imagejpeg($dst, $dest, 85),
                'png' => @imagepng($dst, $dest, 6),
                default => @imagegif($dst, $dest),
            };
            imagedestroy($src);
            imagedestroy($dst);
            if (!$ok) {
                return 'Could not store that file — try again.';
            }
        }
        if (@filesize($dest) > $limits['bytes']) {
            @unlink($dest);
            return 'That file is over the ' . (int) round($limits['bytes'] / 1024) . ' KB limit.';
        }
        return null;
    }

    /** Delete a stored avatar file. Basename-guarded: never touches anything else. */
    private static function dropAvatarFile(string $file): void
    {
        if (!preg_match('#^[A-Za-z0-9_-]+\.(jpg|jpeg|png|gif)$#', $file)) {
            return;
        }
        $path = dirname(__DIR__) . '/public/avatars/' . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
