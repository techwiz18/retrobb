<?php /** @var string $content */ /** @var string|null $pageTitle */ /** @var string|null $metaDesc */ /** @var string|null $canonical */
$user = \RetroBB\Core\Auth::user();
$skinClass = 'skin-' . skin() . ' theme-' . theme();
$title = $pageTitle ?? (board_name() . ' — ' . setting('board_tagline', ''));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if (!empty($metaDesc)): ?><meta name="description" content="<?= e(mb_substr($metaDesc, 0, 160)) ?>"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:type" content="website">
<link rel="stylesheet" href="/assets/style-retro.css">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect x='3' y='4' width='26' height='25' rx='2' fill='%233A6EA5'/%3E%3Crect x='9' y='4' width='14' height='9' fill='%23c9d2e4'/%3E%3Crect x='19' y='4' width='4' height='9' fill='%23232c40'/%3E%3Crect x='6' y='17' width='20' height='9' rx='1' fill='%23f4f6fb'/%3E%3Crect x='9' y='20' width='14' height='2' fill='%2398a5b3'/%3E%3C/svg%3E">
<?php \RetroBB\Core\Hooks::do_action('head'); ?>
</head>
<body class="<?= $skinClass ?>">
<div class="page-wrap">
  <div class="topbar">
    <div class="logo"><a href="/"><svg class="floppy" viewBox="0 0 32 32" aria-hidden="true"><rect x="3" y="4" width="26" height="25" rx="2" fill="#3A6EA5"/><rect x="9" y="4" width="14" height="9" fill="#c9d2e4"/><rect x="19" y="4" width="4" height="9" fill="#232c40"/><rect x="6" y="17" width="20" height="9" rx="1" fill="#f4f6fb"/><rect x="9" y="20" width="14" height="2" fill="#98a5b3"/></svg> <?= e(board_name()) ?></a> <span class="tagline"><?= e(setting('board_tagline', '')) ?></span></div>
    <div class="userbox">
      <?php if ($user): ?>
        <?php $showAlerts = feature('alerts'); $showPms = feature('pms'); ?>
        <?php $ac = $showAlerts ? \RetroBB\Models\Notification::unreadCount((int) $user['id']) : 0; ?>
        <?php $pc = $showPms ? \RetroBB\Models\Pm::unreadCount((int) $user['id']) : 0; ?>
        <span class="dropwrap"><a href="<?= e(\RetroBB\Core\Slug::memberUrl($user)) ?>"><?= e($user['username']) ?></a> <button type="button" class="dropbtn" id="userdrop-btn" aria-haspopup="true" aria-expanded="false" title="Your account">▾<?php if ($ac + $pc > 0): ?> <span class="pill"><?= $ac + $pc ?></span><?php endif; ?></button><span class="dropmenu" id="userdrop-menu"><a href="/settings/profile">Edit profile</a><?php if ($showAlerts): ?><a href="/alerts">Alerts<?php if ($ac > 0): ?> <span class="pill"><?= $ac ?></span><?php endif; ?></a><?php endif; ?><?php if ($showPms): ?><a href="/pm">Messages<?php if ($pc > 0): ?> <span class="pill"><?= $pc ?></span><?php endif; ?></a><?php endif; ?></span></span>
        <?php if ($user['user_group'] === 'admin'): ?> <a class="pill" href="/admin">AdminCP</a><?php endif; ?>
        <form method="post" action="/logout" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="linkbtn" type="submit">Log out</button></form>
      <?php else: ?>
        <a href="/login">Log in</a> · <a href="/register"><b>Register</b></a>
      <?php endif; ?>
    </div>
  </div>
  <nav class="navrow"><a href="/">Board Index</a> · <a href="/members">Members</a> · <a href="/sitemap.xml">Sitemap</a>
    <?php if (\RetroBB\Core\Auth::isMod()): ?>
      · <a href="/mod/reports"><b>Mod queue<?php $qc = \RetroBB\Models\Report::openCount(); if ($qc > 0): ?> (<?= $qc ?>)<?php endif; ?></b></a>
    <?php endif; ?>
    <?php $allowedThemes = allowed_themes(); ?>
    <?php if (feature('skin_selector') || count($allowedThemes) > 1): ?>
    <span class="skinswitch"><?php if (feature('skin_selector')): ?>Skin:
      <a href="/skin/classic">Classic</a> <a href="/skin/midnight">Midnight</a> <a href="/skin/silver">Silver</a><?php endif; ?><?php if (feature('skin_selector') && count($allowedThemes) > 1): ?> · <?php endif; ?><?php if (count($allowedThemes) > 1): ?>Theme:<?php foreach ($allowedThemes as $i => $tm): ?><?= $i > 0 ? ' ' : ' ' ?><a href="/theme/<?= $tm ?>"><?= ucfirst($tm) ?></a><?php endforeach; ?><?php endif; ?>
    </span>
    <?php endif; ?>
  </nav>

  <?php if (!empty($_SESSION['flash_error'])): ?><div class="flash-error"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['flash_ok'])): ?><div class="flash-ok"><?= e($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>
  <?php if ($user && ($wc = \RetroBB\Models\Moderation::warningCount((int) $user['id'])) > 0): ?><div class="flash-error">⚠ You have <?= $wc ?> warning<?= $wc === 1 ? '' : 's' ?> on record. <a href="<?= e(\RetroBB\Core\Slug::memberUrl($user)) ?>">View your profile</a> for details.</div><?php endif; ?>

  <main><?= $content ?></main>

  <footer class="footer">
    <div>Powered by <b>RetroBB</b> by <a href="https://techwiz.dad">techwiz.dad</a> · Old-school soul, modern code · <?= date('Y') ?></div>
    <?php \RetroBB\Core\Hooks::render_template_hook('footer', []); ?>
    <?php \RetroBB\Core\Hooks::do_action('footer'); ?>
  </footer>
</div>
<script src="/assets/forum.js"></script>
</body>
</html>
