<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Filename     : PwaAssetsTest.php
 * License      : AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Feature;

use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Tests\TestCase;

final class PwaAssetsTest extends TestCase {
    public function test_manifest_is_published_and_valid_json(): void {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $json = JsonHelper::decode(ToolkitFile::read($path));
        $this->assertIsArray($json);
        $this->assertSame('workDiary', $json['name'] ?? null);
        $this->assertSame('standalone', $json['display'] ?? null);
        $this->assertSame('/', $json['start_url'] ?? null);
        $this->assertNotEmpty($json['icons'] ?? []);
    }

    public function test_service_worker_keeps_push_handlers_and_minimal_offline_fallback(): void {
        $path = public_path('sw.js');
        $this->assertFileExists($path);

        $code = ToolkitFile::read($path);
        // Push-Handler bleiben erhalten.
        $this->assertStringContainsString("addEventListener(\"push\"", $code);
        $this->assertStringContainsString("notificationclick", $code);
        // MVP-368 (offline-sync-architektur.md Phase 4): Der fetch-Handler ist
        // BEWUSST minimal \u2014 nur Navigationen, strikt network-first mit
        // offline.html-Fallback. Online kommt damit weiterhin immer das
        // frische Server-Rendering an; authentifizierte Seiten werden nie
        // gecacht.
        $this->assertStringContainsString("addEventListener(\"fetch\"", $code);
        $this->assertStringContainsString('request.mode !== "navigate"', $code);
        $this->assertStringContainsString('fetch(event.request).catch', $code);
        $this->assertStringContainsString('OFFLINE_URL', $code);
        // Kein Caching von Navigations-Antworten (nur das install-Precache).
        $this->assertStringNotContainsString('cache.put', $code);
    }

    public function test_offline_fallback_page_exists(): void {
        $path = public_path('offline.html');
        $this->assertFileExists($path);
        $html = ToolkitFile::read($path);
        $this->assertStringContainsString('Du bist offline', $html);
        $this->assertStringContainsString('manifest.webmanifest', $html);
    }

    public function test_offline_fallback_page_is_self_contained(): void {
        // Der Service Worker cacht NUR offline.html (kein Asset-Caching, siehe
        // sw.js). Jede externe Referenz der Seite scheitert daher genau dann,
        // wenn die Seite gebraucht wird \u2014 sichtbar zuletzt als kaputtes
        // Logo. Erlaubt sind nur data:-URIs; das Manifest ist reine
        // PWA-Metainfo und beeinflusst die Darstellung nicht.
        $html = ToolkitFile::read(public_path('offline.html'));

        $this->assertSame(1, preg_match_all('/<img\\b/i', $html), 'Erwartet genau ein Bild auf der Offline-Seite.');

        preg_match_all('/(?:src|href)\\s*=\\s*"([^"]+)"/i', $html, $matches);
        foreach ($matches[1] as $url) {
            if ($url === '/manifest.webmanifest') {
                continue;
            }

            $this->assertStringStartsWith(
                'data:',
                $url,
                sprintf('Offline-Seite referenziert die netzabhaengige Ressource "%s" - bitte als data:-URI einbetten.', $url),
            );
        }
    }
}
