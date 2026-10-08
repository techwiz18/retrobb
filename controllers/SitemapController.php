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
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet href="/assets/sitemap.xsl" type="text/xsl"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $host = canonical_url('');
        $forums = $pdo->query('SELECT id, name FROM forums')->fetchAll();
        foreach ($forums as $f) {
            echo '<url><loc>' . e($host . rtrim(Slug::forumUrl($f), '/')) . '</loc></url>' . "\n";
        }
        $topics = $pdo->query('SELECT id, title FROM topics ORDER BY last_post_at DESC LIMIT 500')->fetchAll();
        foreach ($topics as $t) {
            echo '<url><loc>' . e($host . rtrim(Slug::topicUrl($t), '/')) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
    }
}
