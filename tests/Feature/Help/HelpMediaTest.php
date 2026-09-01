<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpMediaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\User;
use App\Services\Help\HelpTopicLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Bild-Auslieferung der Hilfeartikel (Feature 039, MVP-754):
 * auth-Pflicht, Extension-Whitelist, realpath-Containment, Cache-Header
 * und die Locale-Auflösung der Loader-Umschreibung.
 */
class HelpMediaTest extends TestCase {
    use RefreshDatabase;

    private string $mediaRoot;

    /** 1×1-PNG (67 Bytes) als kleinstmögliche echte Bilddatei. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void {
        parent::setUp();
        $this->mediaRoot = sys_get_temp_dir() . '/help-media-' . uniqid('', true);
        File::makeDirectory($this->mediaRoot . '/zeit', 0755, true);
        File::put($this->mediaRoot . '/zeit/stempeluhr.png', (string) base64_decode(self::PNG_BASE64, true));
        config()->set('help-center.media_path', $this->mediaRoot);
    }

    protected function tearDown(): void {
        if (File::isDirectory($this->mediaRoot)) {
            File::deleteDirectory($this->mediaRoot);
        }
        parent::tearDown();
    }

    public function test_media_requires_authentication(): void {
        $this->get('/hilfe/media/zeit/stempeluhr.png')->assertRedirect();
    }

    public function test_media_is_served_with_cache_headers(): void {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get('/hilfe/media/zeit/stempeluhr.png');

        $response->assertOk();
        $this->assertStringContainsString('image/png', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('cache-control'));
        $this->assertNotSame('', (string) $response->headers->get('etag'));
    }

    public function test_unknown_file_and_forbidden_extension_are_404(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get('/hilfe/media/zeit/fehlt.png')->assertNotFound();
        // SVG ist bewusst nicht auslieferbar (Skript-Risiko).
        File::put($this->mediaRoot . '/zeit/boese.svg', '<svg onload="alert(1)"></svg>');
        $this->actingAs($user)->get('/hilfe/media/zeit/boese.svg')->assertNotFound();
    }

    public function test_traversal_outside_media_root_is_404(): void {
        $user = User::factory()->user()->create();

        // Datei existiert außerhalb des Roots — Containment muss greifen.
        File::put($this->mediaRoot . '/../help-media-outside.png', (string) base64_decode(self::PNG_BASE64, true));

        $this->actingAs($user)
            ->get('/hilfe/media/zeit/../../help-media-outside.png')
            ->assertNotFound();

        File::delete($this->mediaRoot . '/../help-media-outside.png');
    }

    public function test_loader_rewrites_media_sources_with_locale_override(): void {
        // Basisdatei + de-Variante: de bekommt die Variante, en die Basis.
        File::put($this->mediaRoot . '/zeit/stempeluhr.de.png', (string) base64_decode(self::PNG_BASE64, true));

        $tmpRoot = sys_get_temp_dir() . '/help-loader-media-' . uniqid('', true);
        File::makeDirectory($tmpRoot . '/de', 0755, true);
        File::makeDirectory($tmpRoot . '/en', 0755, true);
        $markdown = <<<'MD'
---
title: "Bild-Beispiel"
version: 1
audience: []
related: []
---

![Die Stempeluhr](media/zeit/stempeluhr.png)
MD;
        File::put($tmpRoot . '/de/sample.media.md', $markdown);
        File::put($tmpRoot . '/en/sample.media.md', $markdown);

        try {
            $loader = new HelpTopicLoader($tmpRoot);

            $de = $loader->load('sample.media', 'de');
            $en = $loader->load('sample.media', 'en');

            $this->assertNotNull($de);
            $this->assertNotNull($en);
            $this->assertStringContainsString('src="/hilfe/media/zeit/stempeluhr.de.png"', $de['body_html']);
            $this->assertStringContainsString('alt="Die Stempeluhr"', $de['body_html']);
            $this->assertStringContainsString('src="/hilfe/media/zeit/stempeluhr.png"', $en['body_html']);
        } finally {
            File::deleteDirectory($tmpRoot);
        }
    }
}
