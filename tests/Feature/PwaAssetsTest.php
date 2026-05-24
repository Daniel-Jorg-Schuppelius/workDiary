<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Filename     : PwaAssetsTest.php
 * License      : AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PwaAssetsTest extends TestCase {
    public function test_manifest_is_published_and_valid_json(): void {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $json = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($json);
        $this->assertSame('workDiary', $json['name'] ?? null);
        $this->assertSame('standalone', $json['display'] ?? null);
        $this->assertSame('/', $json['start_url'] ?? null);
        $this->assertNotEmpty($json['icons'] ?? []);
    }

    public function test_service_worker_keeps_push_handlers(): void {
        $path = public_path('sw.js');
        $this->assertFileExists($path);

        $code = (string) file_get_contents($path);
        // Push-Handler bleiben erhalten.
        $this->assertStringContainsString("addEventListener(\"push\"", $code);
        $this->assertStringContainsString("notificationclick", $code);
        // Es darf KEIN fetch-Handler / clients.claim() existieren, damit der SW
        // Navigationen NICHT abf\u00e4ngt und das Server-Rendering unangetastet l\u00e4sst.
        $this->assertStringNotContainsString("addEventListener(\"fetch\"", $code);
    }

    public function test_offline_fallback_page_exists(): void {
        $path = public_path('offline.html');
        $this->assertFileExists($path);
        $html = (string) file_get_contents($path);
        $this->assertStringContainsString('Du bist offline', $html);
        $this->assertStringContainsString('manifest.webmanifest', $html);
    }
}
