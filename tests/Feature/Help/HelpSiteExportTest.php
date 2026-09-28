<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSiteExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Help;

use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** MVP-971: öffentliche Doku-Website als statischer Export. */
final class HelpSiteExportTest extends TestCase {
    private string $output;

    protected function setUp(): void {
        parent::setUp();
        $this->output = sys_get_temp_dir() . '/help-site-' . uniqid('', true);
    }

    protected function tearDown(): void {
        File::deleteDirectory($this->output);
        parent::tearDown();
    }

    public function test_command_exports_public_topics_with_search_index_and_media(): void {
        $this->artisan('help:site', ['--output' => $this->output, '--locale' => ['de', 'en']])
            ->expectsOutputToContain('2 Sprachen')
            ->assertSuccessful();

        $this->assertFileExists($this->output . '/index.html');
        $this->assertFileExists($this->output . '/site.css');
        $this->assertFileExists($this->output . '/search.js');
        $this->assertStringContainsString('de/index.html', File::get($this->output . '/index.html'));

        // Thema ohne Zielgruppe mit Bild: Bild relativ und mitkopiert, Sprachumschalter.
        $page = File::get($this->output . '/de/dashboard.overview.html');
        $this->assertStringContainsString('src="../media/erste-schritte/dashboard-uebersicht', $page);
        $this->assertStringNotContainsString('/hilfe/media/', $page);
        $this->assertStringContainsString('href="../en/dashboard.overview.html"', $page);
        $this->assertNotEmpty(File::glob($this->output . '/media/erste-schritte/dashboard-uebersicht*'));

        // Admin-Themen bleiben intern — auch als Verweis.
        $this->assertFileDoesNotExist($this->output . '/de/admin.license.html');
        $this->assertFileExists($this->output . '/de/help.errors.html');
        $this->assertStringNotContainsString('admin.license.html', File::get($this->output . '/de/index.html'));

        $script = File::get($this->output . '/de/search-index.js');
        $this->assertStringStartsWith('window.HELP_SITE_INDEX = ', $script);
        $index = JsonHelper::decode(substr(trim($script), strlen('window.HELP_SITE_INDEX = '), -1));
        $this->assertContains('help.errors.html', array_column($index, 'u'));
        $this->assertNotContains('admin.license.html', array_column($index, 'u'));
    }

    public function test_help_site_keeps_the_fixed_anchors_of_the_error_help(): void {
        $before = app()->getLocale();
        $this->artisan('help:site', ['--output' => $this->output, '--locale' => ['fr']])->assertSuccessful();
        $this->assertSame($before, app()->getLocale());

        $page = File::get($this->output . '/fr/help.errors.html');
        $this->assertStringContainsString('id="sec-forbidden"', $page);
        $this->assertStringContainsString('lang="fr"', $page);
    }
}
