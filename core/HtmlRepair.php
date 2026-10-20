<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * Re-render stored post/PM HTML from its BBCode source.
 *
 * Built for the C1 stored-XSS remediation (issue #13): the renderer used to
 * emit a bare [url] URL unescaped, so already-stored `body_html` can contain
 * injected markup. Re-rendering with the fixed renderer neutralises it.
 *
 * Default mode is TARGETED: only rows whose stored HTML shows the injection
 * signature are rewritten, so legitimate historical output (mentions, plugin
 * filters, renderer drift) is left alone. `$all` re-renders everything, which
 * is only appropriate on a board whose renderer has been stable.
 */
class HtmlRepair
{
    /** table, BBCode column, rendered-HTML column */
    private const TABLES = [
        ['posts', 'body_bbcode', 'body_html'],
        ['pms', 'body_bbcode', 'body_html'],
    ];

    /**
     * Stored HTML the renderer could not have produced: raw script-ish tags,
     * inline event handlers, or javascript:/vbscript:/data: URLs in an
     * attribute. The renderer escapes all user text and emits a fixed tag set,
     * so any of these is legacy injected markup.
     *
     * Attribute values are entity-decoded and whitespace-stripped first, so
     * obfuscated forms (`java&#115;cript:`, `jav&#x09;ascript:`,
     * `javascript&#58;`) are caught too.
     */
    public static function looksSuspicious(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        if (preg_match('/<\s*(script|iframe|svg|object|embed|form|meta|link|style|base|math|template)\b/i', $html)) {
            return true;
        }
        foreach (self::tagRanges($html) as $tag) {
            // Inline event handlers only count inside a tag, not in prose.
            if (preg_match('/\son[a-z]+\s*=/i', $tag)) {
                return true;
            }
            if (preg_match_all(
                '/\b(?:href|src|action|formaction|poster|data|xlink:href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                $tag,
                $m
            )) {
                foreach ($m[1] as $raw) {
                    $val = trim($raw, "\"'");
                    $val = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $val = (string) preg_replace('/[\x00-\x20\x7f]+/', '', $val);
                    if (preg_match('/^(?:javascript|vbscript|data):/i', $val)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Split HTML into tag substrings. Quote mode starts only after `=`, so a
     * quote inside an unquoted attribute value (a browser parse error that is
     * appended to the value) does not swallow the rest of the tag —
     * `<img src=x" onerror=…>` must still be scanned. A `>` outside quotes
     * ends the tag; EOF ends an unterminated one.
     *
     * @return list<string>
     */
    private static function tagRanges(string $html): array
    {
        $tags = [];
        $len = strlen($html);
        $i = 0;
        while ($i < $len) {
            $lt = strpos($html, '<', $i);
            if ($lt === false) {
                break;
            }
            $j = $lt + 1;
            $quote = '';
            $expectValue = false;
            while ($j < $len) {
                $c = $html[$j];
                if ($quote !== '') {
                    if ($c === $quote) {
                        $quote = '';
                    }
                    $j++;
                    continue;
                }
                if ($expectValue) {
                    if (ctype_space($c)) {
                        $j++;
                        continue;
                    }
                    $expectValue = false;
                    if ($c === '"' || $c === "'") {
                        $quote = $c;
                        $j++;
                        continue;
                    }
                }
                if ($c === '=') {
                    $expectValue = true;
                    $j++;
                    continue;
                }
                if ($c === '>') {
                    break;
                }
                $j++;
            }
            $end = $j < $len ? $j : $len - 1;
            $tags[] = substr($html, $lt, $end - $lt + 1);
            $i = $j + 1;
        }
        return $tags;
    }

    /**
     * @param int $limit     stop after this many changed rows (0 = no limit)
     * @param bool $dry      report only; write nothing
     * @param bool $all      re-render every row, not just suspicious ones
     * @param int $batch     rows read per SELECT
     * @param int $scanLimit stop after scanning this many rows (0 = no limit),
     *                       for the resumable AdminCP action
     * @param array<string,int> $cursors per-table resume ids
     * @return array{scanned:int,changed:int,skipped:int,failed:int,conflicted:int,more:bool,next:array<string,int|bool>}
     */
    public static function run(
        int $limit = 0,
        bool $dry = false,
        bool $all = false,
        int $batch = 500,
        int $scanLimit = 0,
        array $cursors = []
    ): array {
        $pdo = Db::pdo();
        $stats = ['scanned' => 0, 'changed' => 0, 'skipped' => 0, 'failed' => 0, 'conflicted' => 0, 'more' => false];
        $next = [
            'posts' => (int) ($cursors['posts'] ?? 0),
            'pms' => (int) ($cursors['pms'] ?? 0),
            'done' => true,
        ];
        $batch = max(1, min(5000, $batch));

        foreach (self::TABLES as [$table, $src, $dst]) {
            try {
                $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            } catch (\Throwable) {
                continue; // pre-migration board without this table
            }
            $last = (int) ($cursors[$table] ?? 0);
            while (true) {
                $st = $pdo->prepare("SELECT id, `$src` AS bb, `$dst` AS html FROM `$table` WHERE id > ? ORDER BY id LIMIT ?");
                $st->bindValue(1, $last, \PDO::PARAM_INT);
                $st->bindValue(2, $batch, \PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll();
                if (!$rows) {
                    break;
                }
                foreach ($rows as $row) {
                    // Stop BEFORE consuming the next row so a resume continues here.
                    if ($scanLimit > 0 && $stats['scanned'] >= $scanLimit) {
                        $next['done'] = false;
                        $stats['more'] = true;
                        return $stats + ['next' => $next];
                    }
                    $last = (int) $row['id'];
                    $next[$table] = $last;
                    $stats['scanned']++;
                    $bb = (string) $row['bb'];
                    $old = (string) $row['html'];

                    // Never blank HTML that has no BBCode source.
                    if (trim($bb) === '') {
                        $stats['skipped']++;
                        continue;
                    }
                    // Ghost "Moved:" stubs carry a marker, not real BBCode.
                    if ($bb === 'moved-stub') {
                        $stats['skipped']++;
                        continue;
                    }
                    if (!$all && !self::looksSuspicious($old)) {
                        $stats['skipped']++;
                        continue;
                    }

                    try {
                        $new = BBCode::toHtml($bb);
                    } catch (\Throwable $t) {
                        error_log("RetroBB html repair: $table #$last failed: " . $t->getMessage());
                        $stats['failed']++;
                        continue;
                    }
                    if ($new === $old) {
                        $stats['skipped']++;
                        continue;
                    }
                    $stats['changed']++;
                    if (!$dry) {
                        // Compare-and-swap: don't clobber a concurrent edit.
                        $up = $pdo->prepare("UPDATE `$table` SET `$dst` = ? WHERE id = ? AND BINARY `$dst` = BINARY ?");
                        $up->execute([$new, $last, $old]);
                        if ($up->rowCount() === 0) {
                            $stats['changed']--;
                            $stats['conflicted']++;
                        }
                    }
                    if ($limit > 0 && $stats['changed'] >= $limit) {
                        $next['done'] = false;
                        $stats['more'] = true;
                        return $stats + ['next' => $next];
                    }
                }
            }
        }
        return $stats + ['next' => $next];
    }
}
