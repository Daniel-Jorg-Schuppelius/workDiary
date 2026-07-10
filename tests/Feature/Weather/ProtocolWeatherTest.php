<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolWeatherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Weather;

use App\Enums\Protocol\ProtocolStatus;
use App\Enums\User\Permission;
use App\Models\{Asset, Customer, Protocol, User, WeatherSnapshot};
use App\Services\Weather\Contracts\WeatherProvider;
use App\Services\Weather\WeatherService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 062, MVP-131: Anbindung der Wetterdaten an den Tagesbericht/
 * Bautagebuch (`Protocol`). Koordinaten aus dem Subjekt (Kunde/Projekt),
 * unveränderliche Verknüpfung, Endpoint zum Nachholen.
 */
final class ProtocolWeatherTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $editor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->editor = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->editor->givePermissionTo(Permission::ProtocolEditDraft->value);

        // Fake-Wetterprovider (kein HTTP) für Service und Endpoint.
        $this->app->bind(WeatherProvider::class, fn (): WeatherProvider => new class implements WeatherProvider {
            public function key(): string {
                return 'test';
            }

            public function daily(float $lat, float $lng, CarbonInterface $date): ?array {
                return ['temp_min' => 10.0, 'temp_max' => 20.0, 'precipitation_mm' => 1.5, 'wind_gust_kmh' => 33.0, 'weather_code' => 3, 'raw' => ['ok' => true]];
            }
        });
    }

    private function protocolForCustomer(Customer $customer): Protocol {
        return Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'occurred_at' => '2025-06-15 08:00:00',
            'status' => ProtocolStatus::Draft->value,
        ]);
    }

    public function test_attaches_weather_via_customer_coordinates(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $protocol = $this->protocolForCustomer($customer);

        $snapshot = app(WeatherService::class)->snapshotForProtocol($protocol, $this->editor);

        $this->assertNotNull($snapshot);
        $this->assertSame('20.00', $snapshot->temp_max);
        $this->assertSame($snapshot->id, (int) $protocol->fresh()->weather_snapshot_id);
    }

    public function test_returns_null_when_subject_has_no_coordinates(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'occurred_at' => '2025-06-15 08:00:00',
            'status' => ProtocolStatus::Draft->value,
        ]);

        $this->assertNull(app(WeatherService::class)->snapshotForProtocol($protocol, $this->editor));
        $this->assertNull($protocol->fresh()->weather_snapshot_id);
    }

    public function test_endpoint_attaches_weather(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $protocol = $this->protocolForCustomer($customer);

        $this->actingAs($this->editor)
            ->post(route('protocols.weather', $protocol))
            ->assertRedirect();

        $this->assertNotNull($protocol->fresh()->weather_snapshot_id);
    }

    public function test_endpoint_is_idempotent(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $protocol = $this->protocolForCustomer($customer);

        $this->actingAs($this->editor)->post(route('protocols.weather', $protocol))->assertRedirect();
        $first = (int) $protocol->fresh()->weather_snapshot_id;

        $this->actingAs($this->editor)->post(route('protocols.weather', $protocol))->assertRedirect();
        $second = (int) $protocol->fresh()->weather_snapshot_id;

        $this->assertSame($first, $second); // zweiter Klick = gleicher Snapshot
        $this->assertSame(1, WeatherSnapshot::query()->count());
    }

    public function test_endpoint_without_coordinates_flashes_error(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'occurred_at' => '2025-06-15 08:00:00',
            'status' => ProtocolStatus::Draft->value,
        ]);

        $this->actingAs($this->editor)
            ->post(route('protocols.weather', $protocol))
            ->assertRedirect()
            ->assertSessionHasErrors('weather');

        $this->assertNull($protocol->fresh()->weather_snapshot_id);
    }
}
