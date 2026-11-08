<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksConnectionHealthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Plugins\AbstractPlugin;
use App\Plugins\Gitea\Models\GiteaConnection;
use App\Plugins\Support\{ChecksConnectionHealth, PluginApiException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use Throwable;

/**
 * Gemeinsamer Healthcheck der Plugins mit Zugangsdaten je Organisation
 * (Konsolidierung kon-04): Einstellungen, Verbindung, Leseprobe — in dieser
 * Reihenfolge, mit den Codes, die die Plugin-Übersicht auswertet.
 */
class ChecksConnectionHealthTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_without_organization_context_the_system_run_is_ok(): void {
        $this->assertSame('ok', $this->plugin()->healthCheck()->status);
    }

    public function test_reports_setup_connection_and_probe_in_this_order(): void {
        $this->setUpOrganization();
        $plugin = $this->plugin();

        $plugin->configured = false;
        $plugin->failure = new RuntimeException('darf nicht laufen');
        $health = $plugin->healthCheck();
        $this->assertSame(['degraded', 'not_configured'], [$health->status, $health->code]);
        $this->assertNull($plugin->probedOrganization, 'Ohne Einstellungen keine Probe.');

        $plugin->configured = true;
        $plugin->model = GiteaConnection::class;
        $health = $plugin->healthCheck();
        $this->assertSame(['degraded', 'not_connected'], [$health->status, $health->code]);
        $this->assertNull($plugin->probedOrganization, 'Ohne geprüfte Verbindung keine Probe.');

        $plugin->model = null;
        $plugin->failure = null;
        $this->assertSame('ok', $plugin->healthCheck()->status);
        $this->assertSame((int) $this->organization->id, $plugin->probedOrganization);

        $plugin->failure = new PluginApiException('abgewiesen', 401);
        $health = $plugin->healthCheck();
        $this->assertSame(['failing', 'auth'], [$health->status, $health->code]);

        $plugin->failure = new PluginApiException('gedrosselt', 429);
        $this->assertSame('rate_limited', $plugin->healthCheck()->code);

        $plugin->failure = new RuntimeException('kaputt');
        $health = $plugin->healthCheck();
        $this->assertSame(['failing', 'error'], [$health->status, $health->code]);
        $this->assertStringNotContainsString('kaputt', $health->message, 'Nur die Ausnahmeklasse, nie der Inhalt.');
    }

    private function plugin(): AbstractPlugin {
        return new class extends AbstractPlugin {
            use ChecksConnectionHealth;

            public const string ID = 'kon-probe';

            public bool $configured = true;

            /** @var class-string<GiteaConnection>|null */
            public ?string $model = null;

            public ?Throwable $failure = null;

            public ?int $probedOrganization = null;

            public function name(): string {
                return 'Probe';
            }

            public function version(): string {
                return '0.0.0';
            }

            public function description(): string {
                return '';
            }

            public function capabilities(): array {
                return [];
            }

            public function settingsSchema(): array {
                return [];
            }

            protected function healthConfigured(int $organizationId): bool {
                return $this->configured;
            }

            protected function healthConnectionModel(): ?string {
                return $this->model;
            }

            protected function healthProbe(int $organizationId): void {
                $this->probedOrganization = $organizationId;
                if ($this->failure !== null) {
                    throw $this->failure;
                }
            }
        };
    }
}
