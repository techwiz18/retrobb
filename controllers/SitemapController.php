<?php
declare(strict_types=1);

namespace RetroBB\Controllers;

use RetroBB\Core\Db;
use RetroBB\Core\Slug;

class SitemapController
{
    public function xml(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $pdo = Db::pdo();

        // Crawlers re-fetch sitemaps constantly; the content only changes when
        // a topic or forum does. Cache for an hour and answer conditional GETs
        // with 304 instead of rebuilding both result sets every hit.
        $lastMod = 0;
        try {
            $row = $pdo->query('SELECT MAX(last_post_at) m FROM topics')->fetch();
            if (!empty($row['m'])) {
                $lastMod = (int) strtotime((string) $row['m']);
            }
        } catch (\Throwable) {
        }
        if ($lastMod <= 0) {
            $lastMod = time();
        }
        // Include board_url in the validator: changing it changes every loc, so
        // a cached sitemap from before (or from a Host-poisoned era) must not
        // be answered with a 304.
        $etag = 'sitemap-' . $lastMod . '-' . substr(md5((string) setting('board_url', '')), 0, 10);
        header('Cache-Control: public, max-age=3600');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
        header('ETag: "' . $etag . '"');
        $ifMod = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        $ifNone = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        // If both validators arrive, ETag wins (RFC 9110) — so a changed
        // board_url cannot be masked by a stale If-Modified-Since.
        $notModified = $ifNone !== ''
            ? trim($ifNone, '"') === $etag
            : ($ifMod !== '' && (int) strtotime($ifMod) >= $lastMod);
        if ($notModified) {
            http_response_code(304);
            return;
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet href="' . \e(\url('/sitemap.xsl')) . '" type="text/xsl"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        // Sitemap locs must be absolute. Without board_url there is no safe
        // origin (never the request Host — H1), so omit them instead.
        $forums = $pdo->query('SELECT id, name FROM forums')->fetchAll();
        foreach ($forums as $f) {
            $loc = canonical_absolute_url(rtrim(Slug::forumUrl($f), '/'));
            if ($loc !== '') {
                echo '<url><loc>' . e($loc) . '</loc></url>' . "\n";
            }
        }
        $topics = $pdo->query('SELECT id, title FROM topics ORDER BY last_post_at DESC LIMIT 500')->fetchAll();
        foreach ($topics as $t) {
            $loc = canonical_absolute_url(rtrim(Slug::topicUrl($t), '/'));
            if ($loc !== '') {
                echo '<url><loc>' . e($loc) . '</loc></url>' . "\n";
            }
        }
        echo '</urlset>';
    }

    /** Human-readable stylesheet for the sitemap, follows the user's theme. */
    public function xsl(): void
    {
        header('Content-Type: application/xslt+xml; charset=utf-8');
        $t = theme();
        $home = \e(\url('/'));
        // Explicit dark follows the toggle; auto falls back to the OS media query in the XSL.
        $dark = $t === 'dark';
        $bg = $dark ? '#14161b' : '#dfe3ee';
        $panel = $dark ? '#1f232b' : '#fff';
        $text = $dark ? '#d7dbe2' : '#222';
        $link = $dark ? '#8fb4ff' : '#1a3f7a';
        $muted = $dark ? '#9aa1b0' : '#667';
        $border = $dark ? '#3a4354' : '#7a96c2';
        echo <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html>
      <head>
        <title>RetroBB Sitemap</title>
        <style>
          body{font-family:Verdana,Tahoma,Arial,sans-serif;background:$bg;color:$text;margin:0}
          .wrap{max-width:800px;margin:16px auto;background:$panel;border:1px solid $border;padding:16px}
          h1{background:linear-gradient(180deg,#7db3e8,#3A6EA5);color:#fff;padding:8px 12px;margin:0 0 12px;font-size:16px}
          li{margin:4px 0} a{color:$link}
          .note{color:$muted;font-size:12px}
          @media (prefers-color-scheme: dark){
            body{background:#14161b;color:#d7dbe2}
            .wrap{background:#1f232b;border-color:#3a4354}
            h1{background:linear-gradient(180deg,#3d6a99,#2b4a6e)}
            a{color:#8fb4ff} .note{color:#9aa1b0}
          }
        </style>
      </head>
      <body><div class="wrap">
        <h1>RetroBB Sitemap</h1>
        <p class="note">This XML sitemap is for search engines — <a href="{$home}">back to the board index</a>.</p>
        <ul>
          <xsl:for-each select="s:urlset/s:url">
            <li><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></li>
          </xsl:for-each>
        </ul>
      </div></body>
    </html>
  </xsl:template>
</xsl:stylesheet>
XSL;
    }
}
