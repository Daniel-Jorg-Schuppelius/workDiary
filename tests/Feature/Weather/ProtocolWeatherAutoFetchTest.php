<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolWeatherAutoFetchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Weather;

use App\Enums\Protocol\ProtocolStatus;
use App\Jobs\FetchProtocolWeatherJob;
use App\Models\{Customer, Project, Protocol, WeatherSnapshot};
use App\Services\Weather\Contracts\WeatherProvider;
use App\Services\Weather\WeatherService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 062, MVP-131 (Ränge 11+12): Auto-Abruf des Wetter-Snapshots bei
 * Protokoll-Anlage per Queue-Job, gesteuert durch den Org-/Projekt-Schalter
 * (Präzedenz Projekt > Org, Default aus).
 */
final class ProtocolWeatherAutoFetchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Fake-Wetterprovider (kein HTTP).
        $this->app->bind(WeatherProvider::class, fn (): WeatherProvider => new class implements WeatherProvider {
            public function key(): string {
                return 'test';
            }

            public function daily(float $lat, float $lng, CarbonInterface $date): ?array {
                return ['temp_min' => 10.0, 'temp_max' => 20.0, 'precipitation_mm' => 1.5, 'wind_gust_kmh' => 33.0, 'weather_code' => 3, 'raw' => ['ok' => true]];
            }
        });
    }

    private function enableOrgAutoFetch(bool $on): void {
        $settings = (array) ($this->organization->settings ?? []);
        $settings['weather'] = ['auto_fetch' => $on ? '1' : '0'];
        $this->organization->settings = $settings;
        $this->organization->save();
        app()->instance('currentOrganization', $this->organization);
    }

    private function customerWithGeo(): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_lat' => 52.52,
            'address_lng' => 13.405,
        ]);
    }

    private function protocolFor(string $type, int $id): Protocol {
        return Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => $type,
            'subject_id' => $id,
            'occurred_at' => '2025-06-15 08:00:00',
            'status' => ProtocolStatus::Draft->value,
        ]);
    }

    public function test_org_switch_on_dispatches_job(): void {
        $this->enableOrgAutoFetch(true);
        $customer = $this->customerWithGeo();

        Bus::fake();
        $protocol = $this->protocolFor(Customer::class, $customer->id);

        Bus::assertDispatched(FetchProtocolWeatherJob::class, fn (FetchProtocolWeatherJob $job): bool => $job->protocolId === $protocol->id);
    }

    public function test_org_switch_off_does_not_dispatch(): void {
        // Default: aus.
        $customer = $this->customerWithGeo();

        Bus::fake();
        $this->protocolFor(Customer::class, $customer->id);

        Bus::assertNotDispatched(FetchProtocolWeatherJob::class);
    }

    public function test_project_override_off_beats_org_on(): void {
        $this->enableOrgAutoFetch(true);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'weather_auto_fetch' => false]);

        Bus::fake();
        $this->protocolFor(Project::class, $project->id);

        Bus::assertNotDispatched(FetchProtocolWeatherJob::class);
    }

    public function test_project_override_on_beats_org_off(): void {
        $this->enableOrgAutoFetch(false);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'weather_auto_fetch' => true]);

        Bus::fake();
        $protocol = $this->protocolFor(Project::class, $project->id);

        Bus::assertDispatched(FetchProtocolWeatherJob::class, fn (FetchProtocolWeatherJob $job): bool => $job->protocolId === $protocol->id);
    }

    public function test_job_creates_snapshot_for_protocol_with_geo(): void {
        $this->enableOrgAutoFetch(true);
        $customer = $this->customerWithGeo();

        Bus::fake(); // Auto-Dispatch abfangen, Job kontrolliert selbst ausführen.
        $protocol = $this->protocolFor(Customer::class, $customer->id);

        (new FetchProtocolWeatherJob($protocol->id))->handle(app(WeatherService::class));

        $this->assertNotNull($protocol->fresh()->weather_snapshot_id);
        $this->assertSame(1, WeatherSnapshot::query()->count());
    }

    public function test_job_is_noop_without_geo(): void {
        $this->enableOrgAutoFetch(true);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]); // keine Koordinaten

        Bus::fake();
        $protocol = $this->protocolFor(Customer::class, $customer->id);

        (new FetchProtocolWeatherJob($protocol->id))->handle(app(WeatherService::class));

        $this->assertNull($protocol->fresh()->weather_snapshot_id);
        $this->assertSame(0, WeatherSnapshot::query()->count());
    }

    public function test_effective_weather_auto_fetch_precedence(): void {
        $this->enableOrgAutoFetch(true);

        // Projekt „erben" (null) → Org-Setting greift (an).
        $inherit = Project::factory()->create(['organization_id' => $this->organization->id, 'weather_auto_fetch' => null]);
        $this->assertTrue($inherit->effectiveWeatherAutoFetch());

        // Projekt „aus" schlägt Org „an".
        $off = Project::factory()->create(['organization_id' => $this->organization->id, 'weather_auto_fetch' => false]);
        $this->assertFalse($off->effectiveWeatherAutoFetch());

        // Org „aus", Projekt „an".
        $this->enableOrgAutoFetch(false);
        $on = Project::factory()->create(['organization_id' => $this->organization->id, 'weather_auto_fetch' => true]);
        $this->assertTrue($on->effectiveWeatherAutoFetch());

        // Vererbung: Kind „erben" folgt dem Parent „an" (Org aus).
        $child = Project::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $on->id, 'weather_auto_fetch' => null]);
        $this->assertTrue($child->effectiveWeatherAutoFetch());
    }
}
