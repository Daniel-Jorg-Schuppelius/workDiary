<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerOrgAppRegistrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\Calendly\{CalendlyConfig, CalendlyPlugin};
use App\Plugins\GoogleCalendar\{GoogleCalendarConfig, GoogleCalendarPlugin};
use App\Plugins\Todoist\{TodoistConfig, TodoistPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\TestCase;

/**
 * Eigene App-Registrierung je Organisation für Google Kalender, Todoist und
 * Calendly — dasselbe Muster, das Microsoft Graph schon trägt (Feature 102
 * Variante B).
 *
 * Bis zum Sicherheitsaudit 2026-09-13 waren `client_id`/`client_secret` dieser
 * drei Plugins installationsweit: Jede Organisation meldete sich unter dem
 * Briefkopf des Betreibers an. Das ist kein Datenzugriff — die Konten liegen
 * weiter je Organisation — aber es zwingt jeden Mandanten in die
 * Zustimmungs-, Marken- und Kontingentgrenzen einer fremden App. Jetzt kann
 * jede Organisation eine eigene hinterlegen; ohne eigene gilt die Instanz-App.
 *
 * Endpunkte und Scopes bleiben BEWUSST config-only: Sonst könnte ein
 * Org-Admin den Token-Fluss umlenken oder Scopes eskalieren.
 */
final class PerOrgAppRegistrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Instanz-Ebene bleibt in der Konfiguration — genau die Unterscheidung,
        // um die es hier geht.
        foreach (['google_calendar', 'todoist', 'calendly'] as $plugin) {
            config()->set('plugins.' . $plugin . '.client_id', 'instanz-client');
            config()->set('plugins.' . $plugin . '.client_secret', 'instanz-secret');
        }
    }

    /** @return list<array{0: string, 1: class-string, 2: string}> */
    public static function plugins(): array {
        return [
            'Google Kalender' => [GoogleCalendarPlugin::ID, GoogleCalendarConfig::class, 'https://www.googleapis.com/calendar/v3'],
            'Todoist' => [TodoistPlugin::ID, TodoistConfig::class, 'https://api.todoist.com/api/v1'],
            'Calendly' => [CalendlyPlugin::ID, CalendlyConfig::class, 'https://api.calendly.com'],
        ];
    }

    /**
     * @param  class-string  $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('plugins')]
    public function test_the_organization_can_bring_its_own_app(string $pluginId, string $config, string $apiBase): void {
        $orgId = (int) $this->organization->id;

        // Ohne eigene Registrierung gilt die Instanz-App — auch bei
        // geschlossenem Geheimnis-Rückfall (App-Registrierung ≠ Zugangsdaten).
        config(['plugins.allow_env_secret_fallback' => false]);
        $this->assertSame('instanz-client', $config::resolve($orgId)['client_id']);
        $this->assertSame('instanz-secret', $config::resolve($orgId)['client_secret']);
        $this->assertTrue($config::isConfigured($orgId));

        $this->pluginSecret($pluginId, ['client_id' => 'org-client', 'client_secret' => 'org-secret']);

        $resolved = $config::resolve($orgId);
        $this->assertSame('org-client', $resolved['client_id']);
        $this->assertSame('org-secret', $resolved['client_secret']);

        // Der Instanz-Sentinel ignoriert das Overlay.
        $this->assertSame('instanz-client', $config::resolve($config::INSTANCE)['client_id']);

        // Endpunkte bleiben config-only.
        $this->assertSame($apiBase, $resolved['api_base']);
    }

    /**
     * @param  class-string  $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('plugins')]
    public function test_endpoints_cannot_be_redirected_from_the_settings(string $pluginId, string $config, string $apiBase): void {
        $before = $config::resolve((int) $this->organization->id);

        $this->pluginSecret($pluginId, [
            'authorize_url' => 'https://angreifer.example/authorize',
            'token_url' => 'https://angreifer.example/token',
            'api_base' => 'https://angreifer.example',
            'scopes' => 'alles',
        ]);

        $after = $config::resolve((int) $this->organization->id);

        $this->assertSame($before['authorize_url'], $after['authorize_url']);
        $this->assertSame($before['token_url'], $after['token_url']);
        $this->assertSame($apiBase, $after['api_base']);
        $this->assertSame($before['scopes'], $after['scopes']);
    }

    public function test_the_settings_dialog_offers_the_app_registration(): void {
        foreach ([new GoogleCalendarPlugin(), new TodoistPlugin(), new CalendlyPlugin()] as $plugin) {
            $schema = $plugin->settingsSchema();
            $keys = array_column($schema, 'key');

            $this->assertContains('client_id', $keys, $plugin::ID);
            $this->assertContains('client_secret', $keys, $plugin::ID);

            $secretField = $schema[array_search('client_secret', $keys, true)];
            $this->assertTrue((bool) ($secretField['secret'] ?? false), $plugin::ID . ': Secret muss als solches gekennzeichnet sein.');

            foreach ($schema as $field) {
                $this->assertNotSame('', trim((string) $field['label']), $plugin::ID . ': Beschriftung fehlt.');
                $this->assertStringNotContainsString('.settings.', (string) $field['label'], $plugin::ID . ': Übersetzung fehlt.');
            }
        }
    }
}
