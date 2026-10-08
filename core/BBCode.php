<?php
declare(strict_types=1);

namespace RetroBB\Core;

class BBCode
{
    public static function toHtml(string $bbcode): string
    {
        // 1. escape everything, then un-escape our allowed tags
        $html = htmlspecialchars($bbcode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $patterns = [
            '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is' => '<s>$1</s>',
            '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote">$1</blockquote>',
            '/\[quote=&#039;(.*?)&#039;\](.*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[quote=&quot;(.*?)&quot;\](.*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[quote=([^\]]+)\](.*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[code\](.*?)\[\/code\]/is' => '<pre class="bbcode-code">$1</pre>',
            '/\[list\](.*?)\[\/list\]/is' => '<ul class="bbcode-list">$1</ul>',
            '/\[\*\](.*?)(?=\[\*\]|<\/ul>)/is' => '<li>$1</li>',
        ];
        foreach ($patterns as $re => $rep) {
            $html = (string) preg_replace($re, $rep, $html);
        }

        // [url]http://...[/url] and [url=http://...]label[/url]
        $html = (string) preg_replace_callback(
            '/\[url=&quot;(.*?)&quot;\](.*?)\[\/url\]/is',
            fn($m) => self::linkTag(html_entity_decode($m[1]), $m[2]),
            $html
        );
        $html = (string) preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/is',
            fn($m) => self::linkTag(html_entity_decode($m[1]), null),
            $html
        );
        // [img]http://...[/img] — http(s) only
        $html = (string) preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/is',
            function ($m) {
                $src = trim(html_entity_decode($m[1]));
                if (!preg_match('#^https?://#i', $src)) {
                    return '[blocked image]';
                }
                $safe = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                return '<img class="bbcode-img" src="' . $safe . '" alt="user image" loading="lazy">';
            },
            $html
        );

        // emoticons :) :( :D ;) :P
        $emos = [
            ':)' => '🙂', ':(' => '🙁', ':D' => '😀',
            ';)' => '😉', ':P' => '😛', ':o' => '😮',
        ];
        $html = str_replace(array_keys($emos), array_values($emos), $html);

        // paragraphs: double newline => <p>, single => <br>
        $parts = preg_split("/\n\s*\n/", $html) ?: [$html];
        $out = [];
        foreach ($parts as $p) {
            $p = nl2br(trim($p));
            // don't wrap block elements
            if (preg_match('#^\s*<(blockquote|pre|ul)#i', $p)) {
                $out[] = $p;
            } else {
                $out[] = '<p>' . $p . '</p>';
            }
        }
        $html = implode("\n", $out);

        return Hooks::apply_filters('post_body_html', $html, $bbcode);
    }

    private static function linkTag(string $url, ?string $label): string
    {
        $url = trim($url);
        // Absolute http(s) links, plus local "/..." links (used by move-ghosts).
        // Reject "//host" protocol-relative URLs.
        if (str_starts_with($url, '//') || !preg_match('#^(https?://|/)#i', $url)) {
            return htmlspecialchars($label ?? $url, ENT_QUOTES, 'UTF-8');
        }
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $text = $label ?? $url;
        return '<a href="' . $safe . '" rel="nofollow ugc noopener" target="_blank">' . $text . '</a>';
    }

    public static function excerpt(string $bbcode, int $len = 160): string
    {
        $t = trim(preg_replace('/\[(.*?)\]/', '', $bbcode) ?? '');
        $t = (string) preg_replace('/\s+/', ' ', $t);
        if (mb_strlen($t) > $len) {
            $t = mb_substr($t, 0, $len - 1) . '…';
        }
        return $t;
    }
}
