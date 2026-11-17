<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeMarkdownRendererTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\Pages\{KnowledgeMarkdownRenderer, KnowledgePageDiff};
use Tests\TestCase;

/** Markdown der Wissensseiten (Feature 191): sichere Ausgabe, Container, Bilder nur aus Anhängen; Zeilenvergleich. */
final class KnowledgeMarkdownRendererTest extends TestCase {
    public function test_html_and_unsafe_links_are_neutralised(): void {
        $html = (new KnowledgeMarkdownRenderer())->render("<img src=x onerror=alert(1)>\n\n[a](javascript:alert(1)) [b](https://example.test)");

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
    }

    public function test_nested_containers_and_code_fences(): void {
        $html = (new KnowledgeMarkdownRenderer())->render(implode("\n", [
            ':::tipp',
            'Außen',
            ':::details Innen',
            '**fett**',
            ':::',
            ':::',
            '```',
            ':::achtung',
            '```',
            ':::hinweis offen bis zum Ende',
            'Rest',
        ]));

        $this->assertSame(1, substr_count($html, 'alert alert-success'));
        $this->assertStringContainsString('<details class="collapse', $html);
        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString(':::achtung', $html, 'Im Code-Block bleibt die Zeile Text.');
        $this->assertSame(1, substr_count($html, 'alert alert-info'));
        $this->assertStringContainsString('offen bis zum Ende', $html);
    }

    public function test_images_come_only_from_attachments(): void {
        $html = (new KnowledgeMarkdownRenderer())->render("![Maske](anhang:Maske.PNG)\n\n![Fehlt](anhang:fehlt.png)\n\n![Fremd](https://cdn.example.test/a.png)", ['maske.png' => 'https://app.test/signed']);

        $this->assertStringContainsString('<img src="https://app.test/signed" alt="Maske"', $html);
        $this->assertStringContainsString('[Fehlt]', $html);
        $this->assertStringNotContainsString('<img src="https://cdn.example.test/a.png"', $html);
        $this->assertStringContainsString('href="https://cdn.example.test/a.png"', $html);
    }

    public function test_line_diff_marks_added_and_removed_lines(): void {
        $rows = (new KnowledgePageDiff())->lines("a\nb\nc", "a\nB\nc\nd");

        $this->assertSame([
            ['op' => 'same', 'text' => 'a'],
            ['op' => 'del', 'text' => 'b'],
            ['op' => 'add', 'text' => 'B'],
            ['op' => 'same', 'text' => 'c'],
            ['op' => 'add', 'text' => 'd'],
        ], $rows);
    }
}
