<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Rendert Chat-Nachrichtentext zu sicherem HTML:
 *   - ```fenced``` Codeblöcke  → <pre><code> (ohne Zeilenumbruch-/Mention-Verarbeitung)
 *   - `inline`-Code            → <code>
 *   - @Mentions                → hervorgehoben
 *   - Zeilenumbrüche           → <br>
 *
 * Alles wird vor dem Einbetten escaped (htmlspecialchars), daher XSS-sicher.
 */
final class ChatText {
    public static function render(?string $body): HtmlString {
        $text = (string) $body;

        // 1) Fenced-Codeblöcke herauslösen und durch Platzhalter ersetzen,
        //    damit nl2br/Mentions sie nicht verändern.
        $blocks = [];
        $text = preg_replace_callback('/```[ \t]*([\w+#.\-]*)\r?\n?(.*?)```/s', static function (array $m) use (&$blocks): string {
            $i = count($blocks);
            $key = "\x00CB{$i}\x00";
            $blocks[$key] = '<pre class="chat-code my-1 max-w-full overflow-x-auto rounded-lg bg-neutral/90 p-2 font-mono text-xs leading-snug text-neutral-content"><code>'
                . e(rtrim($m[2], "\r\n")) . '</code></pre>';

            return $key;
        }, $text) ?? $text;

        // 2) Inline-Code (einzelne Backticks auf einer Zeile).
        $inline = [];
        $text = preg_replace_callback('/`([^`\r\n]+)`/', static function (array $m) use (&$inline): string {
            $i = count($inline);
            $key = "\x00CI{$i}\x00";
            $inline[$key] = '<code class="rounded bg-base-300/70 px-1 py-0.5 font-mono text-[0.85em]">' . e($m[1]) . '</code>';

            return $key;
        }, $text) ?? $text;

        // 3) Restlichen Text escapen, Mentions hervorheben, Markdown, Zeilenumbrüche.
        $html = e($text);
        $html = preg_replace('/@([\p{L}\p{N}_.\-]+)/u', '<span class="font-semibold underline decoration-current/40">@$1</span>', $html) ?? $html;
        // **fett** und _kursiv_ / *kursiv* (fett zuerst, damit ** nicht als * gilt).
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/s', '<em>$1</em>', $html) ?? $html;
        // ~~durchgestrichen~~
        $html = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $html) ?? $html;
        // URLs klickbar machen (auf dem bereits escapten Text → sicher).
        $html = preg_replace(
            '~\bhttps?://[^\s<]+~',
            '<a href="$0" target="_blank" rel="noopener noreferrer" class="underline decoration-current/50 hover:opacity-80">$0</a>',
            $html,
        ) ?? $html;
        $html = nl2br($html, false);

        // 4) Platzhalter durch das fertige (bereits sichere) Code-HTML ersetzen.
        $html = strtr($html, $inline);
        $html = strtr($html, $blocks);

        return new HtmlString($html);
    }
}
