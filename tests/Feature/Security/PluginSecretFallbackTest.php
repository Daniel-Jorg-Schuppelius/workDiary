<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSecretFallbackTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Plugins\Support\PluginSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13, entschieden am selben Tag: Plugin-Geheimnisse
 * fielen standardmäßig auf die `.env` des Betreibers zurück. Die gehört aber
 * dem Betreiber, nicht einem Mandanten — jede Organisation ohne eigene
 * Zugangsdaten arbeitete damit über die des Betreibers, in einer
 * Mehrmandanten-Installation ein mandantenübergreifender Zugriff.
 *
 * Die Vorgabe im Code ist jetzt geschlossen; die Plugin-Tests hinterlegen ihre
 * Zugangsdaten seither dort, wo sie im Betrieb stehen — in den Einstellungen
 * der Organisation ({@see \Tests\Concerns\WithPluginSecrets}).
 *
 * Eine Ausnahme bleibt und wird hier mitgeprüft: die App-Registrierung des
 * Betreibers. Sie öffnet kein fremdes Konto, sondern ist nur der Briefkopf des
 * OAuth-Flusses — ohne diese Ausnahme fiele die Anmeldung in jeder
 * Installation aus, die ihre Instanz-App über die .env pflegt.
 */
class PluginSecretFallbackTest extends TestCase {
    use RefreshDatabase;

    private function resolver(): PluginSettingsResolver {
        // Ohne Organisation loest der Resolver rein aus der Konfiguration auf —
        // genau der Weg, um den es hier geht.
        return PluginSettingsResolver::for('demo', 0);
    }

    public function test_the_shipped_default_keeps_secrets_out_of_the_operator_env(): void {
        config([
            'plugins.allow_env_secret_fallback' => false,
            'plugins.demo.api_key' => 'BETREIBER-SCHLUESSEL',
            'plugins.demo.label' => 'Aus der Konfiguration',
        ]);

        $resolver = $this->resolver();

        $this->assertNull($resolver->string('api_key'), 'Ein Geheimnis darf nicht auf die Betreiber-Datei zurückfallen.');
        // Nicht-Geheimnisse fallen weiterhin zurück — sonst wären Plugins ohne
        // Org-Konfiguration gar nicht mehr benutzbar.
        $this->assertSame('Aus der Konfiguration', $resolver->string('label'));
    }

    public function test_the_fallback_can_still_be_switched_on_deliberately(): void {
        config([
            'plugins.allow_env_secret_fallback' => true,
            'plugins.demo.api_key' => 'BETREIBER-SCHLUESSEL',
        ]);

        $this->assertSame('BETREIBER-SCHLUESSEL', $this->resolver()->string('api_key'));
    }

    public function test_an_own_value_of_the_organization_always_wins(): void {
        config([
            'plugins.allow_env_secret_fallback' => false,
            'plugins.demo.api_key' => 'BETREIBER-SCHLUESSEL',
        ]);

        $organization = \App\Models\Organization::factory()->create();
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'demo',
            'enabled' => true,
            'settings' => ['api_key' => 'EIGENER-SCHLUESSEL'],
        ]);

        $this->assertSame('EIGENER-SCHLUESSEL', PluginSettingsResolver::for('demo', (int) $organization->id)->string('api_key'));
    }

    public function test_the_app_registration_of_the_operator_still_reaches_an_organization(): void {
        config([
            'plugins.allow_env_secret_fallback' => false,
            'plugins.msgraph.client_secret' => 'INSTANZ-APP',
            'plugins.demo.client_secret' => 'INSTANZ-APP',
        ]);

        $organization = \App\Models\Organization::factory()->create();
        // Zeile vorhanden, aber ohne eigene App-Registrierung — genau der Fall,
        // in dem die Organisation die Instanz-App des Betreibers nutzt.
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'msgraph',
            'enabled' => true,
            'settings' => [],
        ]);
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'demo',
            'enabled' => true,
            'settings' => [],
        ]);

        $this->assertSame(
            'INSTANZ-APP',
            PluginSettingsResolver::for('msgraph', (int) $organization->id)->string('client_secret'),
            'Ohne die Instanz-App fiele die Anmeldung in jeder Installation aus, die sie über die .env pflegt.',
        );
        $this->assertNull(
            PluginSettingsResolver::for('demo', (int) $organization->id)->string('client_secret'),
            'Die Ausnahme gilt nur für die ausdrücklich gelisteten Plugins.',
        );
    }

    public function test_an_own_app_registration_wins_over_the_instance_app(): void {
        config([
            'plugins.allow_env_secret_fallback' => false,
            'plugins.msgraph.client_secret' => 'INSTANZ-APP',
        ]);

        $organization = \App\Models\Organization::factory()->create();
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => 'msgraph',
            'enabled' => true,
            'settings' => ['client_secret' => 'EIGENE-APP'],
        ]);

        $this->assertSame('EIGENE-APP', PluginSettingsResolver::for('msgraph', (int) $organization->id)->string('client_secret'));
    }
}
