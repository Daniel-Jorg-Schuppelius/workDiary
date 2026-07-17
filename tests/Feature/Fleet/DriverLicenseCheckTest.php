<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheckTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Fleet;

use App\Exceptions\DriverLicenseCheckOverdueException;
use App\Models\{DriverLicenseCheck, User, Vehicle};
use App\Services\Dispatch\VehicleReservationService;
use App\Services\Fleet\DriverLicenseCheckService;
use App\Support\Sqid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-417 Führerscheinkontrolle: Fälligkeitsberechnung (Org-Intervall,
 * Gültigkeitsablauf), nutzerbezogener Reservierungs-Guard (kein
 * Big-Bang ohne Erstkontrolle), Erfassung inkl. verschlüsselter Notiz
 * und Rechte-/Mandantengrenzen.
 */
class DriverLicenseCheckTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $driver;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Carbon::setTestNow('2030-06-15');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeVehicle(): Vehicle {
        return Vehicle::create([
            'organization_id' => $this->organization->id,
            'name' => 'Transporter 1',
            'license_plate' => 'B-WD 417',
        ]);
    }

    public function test_due_date_follows_org_interval_and_overdue_logic(): void {
        $service = app(DriverLicenseCheckService::class);

        // Default-Intervall 6 Monate.
        $check = $service->record($this->driver, $this->admin, Carbon::parse('2030-01-10'), 'B, BE');
        $this->assertSame('2030-07-10', $check->next_due_on->toDateString());
        $this->assertFalse($service->isOverdue((int) $this->driver->id));

        // Org-Override: 3 Monate → Kontrolle vom Februar ist im Juni überfällig.
        $this->organization->update(['settings' => ['fleet' => ['license_check_interval_months' => 3]]]);
        $this->driver->unsetRelation('organization'); // Relation-Cache des ersten record()-Aufrufs verwerfen
        $second = $service->record($this->driver, $this->admin, Carbon::parse('2030-02-01'));
        $this->assertSame('2030-05-01', $second->next_due_on->toDateString());
        $this->assertTrue($service->isOverdue((int) $this->driver->id));
    }

    public function test_expired_license_validity_counts_as_overdue(): void {
        $service = app(DriverLicenseCheckService::class);
        $service->record($this->driver, $this->admin, Carbon::parse('2030-06-01'), 'B', Carbon::parse('2030-06-10'));

        $this->assertTrue($service->isOverdue((int) $this->driver->id));
    }

    public function test_driver_without_any_check_is_not_blocked(): void {
        $vehicle = $this->makeVehicle();

        $reservation = app(VehicleReservationService::class)->reserve(
            $vehicle,
            '2030-06-16 08:00:00',
            '2030-06-16 17:00:00',
            (int) $this->driver->id,
        );

        $this->assertNotNull($reservation->id);
    }

    public function test_overdue_check_blocks_vehicle_reservation_until_new_check(): void {
        $service = app(DriverLicenseCheckService::class);
        // Kontrolle vor >6 Monaten → überfällig.
        $service->record($this->driver, $this->admin, Carbon::parse('2029-11-01'));
        $vehicle = $this->makeVehicle();

        try {
            app(VehicleReservationService::class)->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id);
            $this->fail('Erwartete DriverLicenseCheckOverdueException wurde nicht geworfen.');
        } catch (DriverLicenseCheckOverdueException) {
            // erwartet
        }

        // Neue Sichtprüfung hebt die Sperre auf.
        $service->record($this->driver, $this->admin, Carbon::parse('2030-06-15'));
        $reservation = app(VehicleReservationService::class)->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id);
        $this->assertNotNull($reservation->id);
    }

    public function test_store_documents_check_with_encrypted_note(): void {
        $this->actingAs($this->admin)
            ->post(route('driver-license-checks.store'), [
                'user_id' => Sqid::encode(User::class, (int) $this->driver->id),
                'checked_at' => '2030-06-15',
                'license_classes' => 'B, BE',
                'note' => 'Original vorgelegt',
            ])
            ->assertRedirect(route('driver-license-checks.index'));

        $check = DriverLicenseCheck::query()->firstOrFail();
        $this->assertSame('Original vorgelegt', $check->note);
        // At-rest verschlüsselt: Rohwert in der DB ist nicht der Klartext.
        $raw = (string) DB::table('driver_license_checks')->value('note');
        $this->assertNotSame('Original vorgelegt', $raw);
    }

    public function test_permissions_and_tenant_isolation(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)
            ->post(route('driver-license-checks.store'), [
                'user_id' => Sqid::encode(User::class, (int) $this->driver->id),
                'checked_at' => '2030-06-15',
            ])
            ->assertForbidden();

        $foreignOrg = \App\Models\Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($foreignAdmin)
            ->get(route('driver-license-checks.show', $this->driver))
            ->assertNotFound();
    }
}
