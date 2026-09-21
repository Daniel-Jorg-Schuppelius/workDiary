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

        // Selbstprüfung im Rhythmus des Retry-After; der Erneut-laden-Knopf hat einen Handler.
        $this->assertStringContainsString('data-auto-retry="60"', $html);
        $this->assertStringContainsString('data-auto-retry-countdown', $html);
        $this->assertStringContainsString("querySelectorAll('[data-reload]')", $html);
        $this->assertStringContainsString("method: 'HEAD'", $html);
    }

    public function test_selbstpruefung_faellt_ohne_retry_header_auf_dreissig_sekunden(): void {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('data-auto-retry="30"', $html);
    }

    public function test_andere_fehlerseiten_pruefen_nicht_selbst_haben_aber_den_knopf_handler(): void {
        $html = view('errors.500')->render();

        $this->assertStringNotContainsString('data-auto-retry=', $html);
        $this->assertStringContainsString("querySelectorAll('[data-reload]')", $html);
    }

    public function test_503_seite_rendert_auch_ohne_retry_header(): void {
        $html = view('errors.503')->render();

        $this->assertStringContainsString(__('Wartungsarbeiten'), $html);
        $this->assertStringNotContainsString('Nächster Versuch empfohlen', $html);
    }

    public function test_deploy_rendert_die_wartungsseite_vor(): void {
        $deploy = (string) file_get_contents(base_path('deploy.sh'));

        $this->assertStringContainsString('--render="errors::503"', $deploy, 'deploy.sh muss die Wartungsseite vorrendern, sonst fehlt sie, während Composer/Vite die App umbauen.');
    }

    public function test_deploy_build_laesst_die_assets_der_vorgerenderten_wartungsseite_liegen(): void {
        $deploy = (string) file_get_contents(base_path('deploy.sh'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('env.KEEP_PREVIOUS_ASSETS', $vite, 'vite.config.js muss emptyOutDir am Flag abschalten.');
        $this->assertDoesNotMatchRegularExpression('/^\s*npm run build/m', $deploy, 'Ein Build ohne KEEP_PREVIOUS_ASSETS leert public/build — die Wartungsseite verliert CSS und Fonts (503).');

        $build = strpos($deploy, 'KEEP_PREVIOUS_ASSETS=1 npm run build');
        $prune = strpos($deploy, '! -newer "$BUILD_STAMP" -delete');
        $advance = strpos($deploy, 'mv "$BUILD_STAMP.next" "$BUILD_STAMP"');
        $this->assertNotFalse($build);
        $this->assertNotFalse($prune);
        $this->assertNotFalse($advance);
        $this->assertLessThan($build, $prune, 'Aufgeräumt wird vor dem Build, gegen den Stempel des letzten erfolgreichen Builds.');
        $this->assertGreaterThan($build, $advance, 'Der Stempel darf erst nach erfolgreichem Build nachrücken.');
    }
}
