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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💾</text></svg>">
<?php \RetroBB\Core\Hooks::do_action('head'); ?>
</head>
<body class="<?= $skinClass ?>">
<div class="page-wrap">
  <div class="topbar">
    <div class="logo"><a href="/">💾 <?= e(board_name()) ?></a> <span class="tagline"><?= e(setting('board_tagline', '')) ?></span></div>
    <div class="userbox">
      <?php if ($user): ?>
        <a href="<?= e(\RetroBB\Core\Slug::memberUrl($user)) ?>"><?= e($user['username']) ?></a>
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
    <span class="skinswitch">Skin:
      <a href="/skin/classic">Classic</a> <a href="/skin/midnight">Midnight</a> <a href="/skin/silver">Silver</a>
      · Theme: <a href="/theme/light">Light</a> <a href="/theme/dark">Dark</a> <a href="/theme/auto">Auto</a>
    </span>
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
