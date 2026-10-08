<?php /** @var array $topic */ /** @var array|null $forum */ /** @var array $posts */ /** @var int $page */ /** @var int $pages */ /** @var int $perPage */ ?>
<div class="breadcrumb"><a href="/">Index</a> &raquo; <?php if ($forum): ?><a href="<?= e(\RetroBB\Core\Slug::forumUrl($forum)) ?>"><?= e($forum['name']) ?></a> &raquo;<?php endif; ?> <?= e($topic['title']) ?></div>
<div class="maintitle"><?= e($topic['title']) ?></div>
<div class="actionrow">
  <span class="muted"><?= e($topic['author']) ?> · <?= (int) $topic['views'] ?> views</span>
  <?php if (\RetroBB\Core\Auth::isMod()): ?>
  <form method="post" action="/topic/<?= (int) $topic['id'] ?>/pinned" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn"><?= ((int) $topic['pinned'] === 1) ? 'Unpin' : 'Pin' ?></button></form>
  <form method="post" action="/topic/<?= (int) $topic['id'] ?>/locked" style="display:inline"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn"><?= ((int) $topic['locked'] === 1) ? 'Unlock' : 'Lock' ?></button></form>
  <a class="smallbtn" href="/topic/<?= (int) $topic['id'] ?>/move" style="text-decoration:none">Move</a>
  <a class="smallbtn" href="/topic/<?= (int) $topic['id'] ?>/split" style="text-decoration:none">Split</a>
  <form method="post" action="/topic/<?= (int) $topic['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Delete topic?')"><?= \RetroBB\Core\Csrf::field() ?><button class="smallbtn danger">Delete</button></form>
  <?php endif; ?>
</div>
<?php if (\RetroBB\Core\Auth::isMod()): ?>
<div class="actionrow">
  <form method="post" action="/topic/<?= (int) $topic['id'] ?>/merge" onsubmit="return confirm('Merge this topic into the target? This topic will be removed.')">
    <?= \RetroBB\Core\Csrf::field() ?>
    <small class="muted">Merge into topic:</small>
    <input type="text" name="target" placeholder="id or URL, e.g. .t12" size="18" required>
    <button class="smallbtn">Merge</button>
  </form>
</div>
<?php endif; ?>
<?php foreach ($posts as $i => $p): ?>
<?php $hue = abs(crc32($p['username'])) % 360; $initial = mb_strtoupper(mb_substr($p['username'], 0, 1)); ?>
<div class="postbit" id="p<?= (int) $p['id'] ?>" data-username="<?= e($p['username']) ?>">
  <div class="posthead">
    <a class="postnum" href="#p<?= (int) $p['id'] ?>">#<?= (($page - 1) * $perPage) + $i + 1 ?></a>
    <span class="postdate"><?= e($p['created_at']) ?> (<?= e(time_ago($p['created_at'])) ?>)</span>
    <span class="postactions">
      <button type="button" class="smallbtn quotebtn" data-post="<?= (int) $p['id'] ?>">Quote</button>
      <?php $me = \RetroBB\Core\Auth::user(); ?>
      <?php if ($me && ((int) $p['user_id'] === (int) $me['id'] || \RetroBB\Core\Auth::isMod())): ?>
        <a class="smallbtn" href="/post/<?= (int) $p['id'] ?>/edit" style="text-decoration:none">Edit</a>
      <?php endif; ?>
      <?php if ($me && (int) $p['user_id'] !== (int) $me['id']): ?>
        <a class="smallbtn" href="/post/<?= (int) $p['id'] ?>/report" style="text-decoration:none">Report</a>
      <?php endif; ?>
    </span>
  </div>
  <div class="postbody">
    <div class="postleft">
      <span class="avatar" style="background:hsl(<?= $hue ?>,45%,55%)"><?= e($initial) ?></span>
      <b class="postuser"><?= e($p['username']) ?></b>
      <span class="group group-<?= e($p['user_group']) ?>"><?= e($p['user_group']) ?></span>
      <small class="postmeta"><?= (int) $p['user_posts'] ?> posts<br>Joined <?= e(substr($p['user_since'], 0, 10)) ?></small>
    </div>
    <div class="postright"><?= $p['body_html'] ?><?php if (!empty($p['edited_at'])): ?><div class="editedmark">Edited <?= e(time_ago($p['edited_at'])) ?></div><?php endif; ?></div>
  </div>
</div>
<?php \RetroBB\Core\Hooks::render_template_hook('postbit_after', ['post' => $p]); ?>
<?php endforeach; ?>
<?php \RetroBB\Core\View::partial('partials/pagination', ['page' => $page, 'pages' => $pages]); ?>

<div id="reply" class="replybox">
<?php if (\RetroBB\Core\Auth::check()): ?>
  <?php if ((int) $topic['locked'] === 1 && !\RetroBB\Core\Auth::isMod()): ?>
    <div class="locked">🔒 This topic is locked.</div>
  <?php else: ?>
  <div class="maintitle">Post a reply</div>
  <form method="post" action="<?= e(\RetroBB\Core\Slug::topicUrl($topic)) ?>/reply">
    <?= \RetroBB\Core\Csrf::field() ?>
    <div class="bbtoolbar">
      <button type="button" data-bb="[b][/b]"><b>B</b></button>
      <button type="button" data-bb="[i][/i]"><i>i</i></button>
      <button type="button" data-bb="[url][/url]">url</button>
      <button type="button" data-bb="[quote][/quote]">quote</button>
      <button type="button" data-bb="[code][/code]">code</button>
    </div>
    <textarea id="replybox-ta" name="body" rows="6" required placeholder="Write like it's 2003... (BBCode supported)"></textarea>
    <button class="btn" type="submit">Submit reply</button>
  </form>
  <?php endif; ?>
<?php else: ?>
  <a class="btn" href="/login?next=<?= urlencode(\RetroBB\Core\Slug::topicUrl($topic)) ?>">Log in to reply</a>
<?php endif; ?>
</div>
