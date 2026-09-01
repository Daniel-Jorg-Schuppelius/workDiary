<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicLoaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Services\Help\HelpTopicLoader;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpTopicLoaderTest extends TestCase {
    private string $tmpRoot;

    protected function setUp(): void {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/help-loader-' . uniqid('', true);
        File::makeDirectory($this->tmpRoot . '/de', 0755, true);
        File::makeDirectory($this->tmpRoot . '/en', 0755, true);
    }

    protected function tearDown(): void {
        if (File::isDirectory($this->tmpRoot)) {
            File::deleteDirectory($this->tmpRoot);
        }
        parent::tearDown();
    }

    public function test_loader_parses_front_matter_and_body(): void {
        File::put($this->tmpRoot . '/de/sample.start.md', <<<'MD'
---
title: "Beispiel-Hilfe"
version: 2
audience:
    - admin
    - user
related:
    - sample.edit
---

# Hauptüberschrift

Erster Absatz mit **Markdown**.
MD);

        $loader = new HelpTopicLoader($this->tmpRoot);
        $loaded = $loader->load('sample.start', 'de');

        $this->assertIsArray($loaded);
        $this->assertSame('sample.start', $loaded['topic']);
        $this->assertSame('de', $loaded['locale']);
        $this->assertSame('Beispiel-Hilfe', $loaded['title']);
        $this->assertSame(2, $loaded['version']);
        $this->assertSame(['admin', 'user'], $loaded['audience']);
        $this->assertSame(['sample.edit'], $loaded['related']);
        $this->assertStringContainsString('<strong>Markdown</strong>', $loaded['body_html']);
        $this->assertStringContainsString('# Hauptüberschrift', $loaded['body_md']);
    }

    public function test_loader_returns_null_when_topic_missing(): void {
        $loader = new HelpTopicLoader($this->tmpRoot);

        $this->assertNull($loader->load('does-not.exist', 'de'));
    }

    public function test_loader_lists_locales_and_topics(): void {
        File::put($this->tmpRoot . '/de/topic.a.md', "---\ntitle: A\n---\nA");
        File::put($this->tmpRoot . '/de/topic.b.md', "---\ntitle: B\n---\nB");
        File::put($this->tmpRoot . '/en/topic.a.md', "---\ntitle: A\n---\nA");

        $loader = new HelpTopicLoader($this->tmpRoot);

        $this->assertSame(['de', 'en'], $loader->locales());
        $this->assertSame(['topic.a', 'topic.b'], $loader->topicsForLocale('de'));
        $this->assertSame(['topic.a'], $loader->topicsForLocale('en'));
        $this->assertSame(['topic.a', 'topic.b'], $loader->allTopicCodes());
    }

    public function test_loader_defaults_title_to_topic_when_front_matter_missing(): void {
        File::put($this->tmpRoot . '/de/raw.topic.md', 'Nur Body, kein Front-Matter.');

        $loader = new HelpTopicLoader($this->tmpRoot);
        $loaded = $loader->load('raw.topic', 'de');

        $this->assertNotNull($loaded);
        $this->assertSame('raw.topic', $loaded['title']);
        $this->assertSame(1, $loaded['version']);
        $this->assertSame([], $loaded['audience']);
    }

    public function test_loader_extracts_modules_and_heading_anchors(): void {
        File::put($this->tmpRoot . '/de/sample.center.md', <<<'MD'
---
title: "Hilfecenter-Beispiel"
version: 1
audience: []
modules:
    - helpdesk
    - accounting
related: []
---

Einleitung.

## Zweck und Hintergrund

Text.

## Ablauf

Text.

## Ablauf

Doppelte Überschrift (Kollisionsfall).

### Unterpunkt

Text.
MD);

        $loader = new HelpTopicLoader($this->tmpRoot);
        $loaded = $loader->load('sample.center', 'de');

        $this->assertIsArray($loaded);
        $this->assertSame(['helpdesk', 'accounting'], $loaded['modules']);

        // h2/h3 bekommen deterministische Anker; Kollisionen zählen hoch.
        $this->assertStringContainsString('<h2 id="sec-zweck-und-hintergrund">', $loaded['body_html']);
        $this->assertStringContainsString('<h2 id="sec-ablauf">', $loaded['body_html']);
        $this->assertStringContainsString('<h2 id="sec-ablauf-2">', $loaded['body_html']);
        $this->assertStringContainsString('<h3 id="sec-unterpunkt">', $loaded['body_html']);

        $this->assertSame([
            ['level' => 2, 'text' => 'Zweck und Hintergrund', 'anchor' => 'sec-zweck-und-hintergrund'],
            ['level' => 2, 'text' => 'Ablauf', 'anchor' => 'sec-ablauf'],
            ['level' => 2, 'text' => 'Ablauf', 'anchor' => 'sec-ablauf-2'],
            ['level' => 3, 'text' => 'Unterpunkt', 'anchor' => 'sec-unterpunkt'],
        ], $loaded['headings']);
    }
}
