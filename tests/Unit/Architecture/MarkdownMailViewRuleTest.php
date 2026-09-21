<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkdownMailViewRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (UI-Fuzz 2026-09-21): Den `mail::`-Namespace registriert
 * Laravel nur beim Markdown-Rendering. 13 Mailables renderten ihre
 * `@component('mail::message')`-Vorlage per `Content(view: …)` — jede dieser
 * Mails (2FA-Code, Portal-Einladung, Stundenzettel-Signatur …) scheiterte mit
 * „No hint path defined for [mail]“. Mail::fake() in Tests rendert nicht,
 * daher dieses statische Gate.
 */
class MarkdownMailViewRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_markdown_mail_templates_are_rendered_as_markdown(): void {
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match_all("/Content\(\s*(?:view|html)\s*:\s*'([^']+)'/", $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[1] as [$view, $offset]) {
                $template = $this->repoRoot() . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';
                if (! is_file($template)) {
                    continue;
                }
                if (preg_match("/@component\(\s*'mail::|<x-mail::/", (string) file_get_contents($template)) === 1) {
                    $violations[] = sprintf('%s:%d — %s', $this->relativePath($file), $this->lineOf($source, (int) $offset), $view);
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Markdown-Mailvorlage per Content(view: …) gerendert — Content(markdown: …) verwenden:\n"
            . implode("\n", $violations));
    }
}
