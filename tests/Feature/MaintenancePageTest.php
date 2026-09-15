<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Wartungsmodus des Frameworks (`php artisan down`, deploy.sh): die 503-Seite
 * ist die eigene, gebrandete Wartungsseite — nicht Laravels nackte
 * Standardseite. Sie muss ohne Datenbank und Session renderbar sein, weil
 * die Migration währenddessen läuft; deploy.sh rendert sie vor.
 */
class MaintenancePageTest extends TestCase {
    use RefreshDatabase;

    public function test_eigene_503_seite_existiert_und_traegt_den_wiederholungshinweis(): void {
        $this->assertTrue(view()->exists('errors.503'), 'Ohne errors/503.blade.php zeigt artisan down Laravels Standardseite.');

        $html = view('errors.503', ['exception' => new HttpException(503, 'Service Unavailable', null, ['Retry-After' => 60])])->render();

        $this->assertStringContainsString(__('Wartungsarbeiten'), $html);
        $this->assertStringContainsString(__('Nächster Versuch empfohlen in :seconds Sekunden.', ['seconds' => 60]), $html);
        $this->assertStringNotContainsString('Service Unavailable', $html, 'Der Framework-Text hat auf der Seite nichts verloren.');
    }

    public function test_503_seite_rendert_auch_ohne_retry_header(): void {
        $html = view('errors.503')->render();

        $this->assertStringContainsString(__('Wartungsarbeiten'), $html);
        $this->assertStringNotContainsString('Sekunden', $html);
    }

    public function test_deploy_rendert_die_wartungsseite_vor(): void {
        $deploy = (string) file_get_contents(base_path('deploy.sh'));

        $this->assertStringContainsString('--render="errors::503"', $deploy, 'deploy.sh muss die Wartungsseite vorrendern, sonst fehlt sie, während Composer/Vite die App umbauen.');
    }
}
