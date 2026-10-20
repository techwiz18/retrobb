<?php
declare(strict_types=1);

namespace RetroBB\Core;

class BBCode
{
    public static function toHtml(string $bbcode): string
    {
        // 1. escape everything, then un-escape our allowed tags
        $html = htmlspecialchars($bbcode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Tag contents refuse to span an opener of the same construct
        // (tempered dot): innermost pairs resolve first, so nesting pairs
        // correctly no matter how deep it goes.
        $patterns = [
            '/\[b\]((?:(?!\[b\])[\s\S])*?)\[\/b\]/is' => '<strong>$1</strong>',
            '/\[i\]((?:(?!\[i\])[\s\S])*?)\[\/i\]/is' => '<em>$1</em>',
            '/\[u\]((?:(?!\[u\])[\s\S])*?)\[\/u\]/is' => '<u>$1</u>',
            '/\[s\]((?:(?!\[s\])[\s\S])*?)\[\/s\]/is' => '<s>$1</s>',
            '/\[quote\]((?:(?!\[quote[\s\]])[\s\S])*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote">$1</blockquote>',
            '/\[quote=&#039;(.*?)&#039;\]((?:(?!\[quote[\s\]])[\s\S])*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[quote=&quot;(.*?)&quot;\]((?:(?!\[quote[\s\]])[\s\S])*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[quote=([^\]]+)\]((?:(?!\[quote[\s\]])[\s\S])*?)\[\/quote\]/is' => '<blockquote class="bbcode-quote"><cite>$1 wrote:</cite>$2</blockquote>',
            '/\[code\]((?:(?!\[code\])[\s\S])*?)\[\/code\]/is' => '<pre class="bbcode-code">$1</pre>',
            '/\[list\]((?:(?!\[list\])[\s\S])*?)\[\/list\]/is' => '<ul class="bbcode-list">$1</ul>',
            '/\[\*\](.*?)(?=\[\*\]|<\/ul>)/is' => '<li>$1</li>',
        ];
        // Loop until stable (max 10): each pass resolves the currently
        // innermost pairs, working outward.
        for ($pass = 0; $pass < 10; $pass++) {
            $before = $html;
            foreach ($patterns as $re => $rep) {
                $html = (string) preg_replace($re, $rep, $html);
            }
            if ($html === $before) {
                break;
            }
        }

        // [url]http://...[/url] and [url=http://...]label[/url] (quoted or bare)
        $html = (string) preg_replace_callback(
            '/\[url=&quot;(.*?)&quot;\](.*?)\[\/url\]/is',
            fn($m) => self::linkTag(html_entity_decode($m[1]), $m[2]),
            $html
        );
        $html = (string) preg_replace_callback(
            '/\[url=([^\]]+)\](.*?)\[\/url\]/is',
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

        // bare URLs become links (explicit [url]/[img] already handled above)
        $html = self::autolink($html);

        // emoticons :) :( :D ;) :P
        $emos = [
            ':)' => '🙂', ':(' => '🙁', ':D' => '😀',
            ';)' => '😉', ':P' => '😛', ':o' => '😮',
        ];
        $html = str_replace(array_keys($emos), array_values($emos), $html);

        // paragraphs: double newline => <p>, single => <br>. Never split
        // inside block elements: blank lines there collapse, so quotes and
        // code blocks keep valid nesting.
        $chunks = preg_split('/(\n[ \t]*\n)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $out = [];
        $buf = '';
        $depth = 0;
        $flush = function () use (&$out, &$buf): void {
            $p = nl2br(trim($buf));
            $buf = '';
            if ($p === '') {
                return;
            }
            // don't wrap block elements
            $out[] = preg_match('#^\s*<(blockquote|pre|ul)#i', $p) ? $p : '<p>' . $p . '</p>';
        };
        foreach ($chunks as $i => $ch) {
            if ($i % 2 === 1) {
                if ($depth > 0) {
                    $buf .= "\n";
                } else {
                    $flush();
                }
                continue;
            }
            $buf .= $ch;
            $depth += substr_count($ch, '<blockquote') + substr_count($ch, '<pre') + substr_count($ch, '<ul');
            $depth -= substr_count($ch, '</blockquote>') + substr_count($ch, '</pre>') + substr_count($ch, '</ul>');
            if ($depth < 0) {
                $depth = 0;
            }
        }
        $flush();
        $html = implode("\n", $out);

        // @mentions link to member profiles (existing users only; safe pre-install).
        $html = Mentions::renderHtml($html);

        return Hooks::apply_filters('post_body_html', $html, $bbcode);
    }

    /**
     * Linkify bare http(s) URLs in text segments only — never inside tags or
     * attributes, link labels (no nested anchors), or code blocks. Trailing
     * punctuation is left outside the link.
     */
    private static function autolink(string $html): string
    {
        $segs = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $skip = 0; // depth inside <pre> or <a>
        foreach ($segs as &$s) {
            if (str_starts_with($s, '<')) {
                if (preg_match('#^<(pre|a)[\s>]#i', $s)) {
                    $skip++;
                } elseif (preg_match('#^</(pre|a)[\s>]#i', $s)) {
                    $skip = max(0, $skip - 1);
                }
                continue;
            }
            if ($skip > 0) {
                continue;
            }
            $s = (string) preg_replace_callback(
                '#https?://[^\s<>]+#i',
                function ($m) {
                    $raw = $m[0];
                    // Peel trailing punctuation (and unbalanced closing parens).
                    $trail = '';
                    while ($raw !== '' && str_contains('.,;:!?\'"', substr($raw, -1))) {
                        $trail = substr($raw, -1) . $trail;
                        $raw = substr($raw, 0, -1);
                    }
                    while (str_ends_with($raw, ')') && substr_count($raw, '(') < substr_count($raw, ')')) {
                        $trail = ')' . $trail;
                        $raw = substr($raw, 0, -1);
                    }
                    if ($raw === '') {
                        return $m[0];
                    }
                    return self::linkTag(html_entity_decode($raw), $raw) . $trail;
                },
                $s
            );
        }
        unset($s);
        return implode('', $segs);
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
        // A bare [url]X[/url] has no label: show the URL, escaped. $label, when
        // present, is the already-escaped inner text (plus our own tags), so it
        // stays raw. Emitting the decoded URL raw here was a stored-XSS hole
        // ([url]http://x/</a><script>…[/url]) — see issue #13 (C1).
        $text = $label ?? $safe;
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

    /** Stored HTML predates a renderer fix if it has leftover BBCode or a
        paragraph opened inside a quote (the old splitter broke there). */
    public static function needsRepair(string $html): bool
    {
        if (str_contains($html, '[quote')) {
            return true;
        }
        return (bool) preg_match('/<blockquote[^>]*>((?:(?!<\/?blockquote)[\s\S])*)<p[\s>]/i', $html);
    }

    /** Collapse nested [quote] blocks (deepest first) so replies quote one level. */    public static function stripQuotes(string $bbcode, string $placeholder = '[…]'): string
    {
        for ($i = 0; $i < 10; $i++) {
            $next = (string) preg_replace('/\[quote[^\]]*\].*?\[\/quote\]/is', $placeholder, $bbcode);
            if ($next === $bbcode) {
                break;
            }
            $bbcode = $next;
        }
        return trim((string) preg_replace('/\s*\[…\]\s*/', ' […] ', $bbcode));
    }
}
