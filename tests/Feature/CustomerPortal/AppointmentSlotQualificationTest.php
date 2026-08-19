<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentSlotQualificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Models\{BookableService, Qualification, User};
use App\Services\Appointments\AppointmentSlotService;
use App\Services\Dispatch\GapFillSuggester;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Qualifikations-Filter der Slot-Suche (Feature 087, Folgepunkt):
 * Verlangt die Leistungsart eine Qualifikation, zählen nur Fenster von
 * Kräften, die sie am Termin-Tag gültig halten — ein Slot, den niemand
 * fahren darf, wäre ein leeres Versprechen an den Kunden.
 */
final class AppointmentSlotQualificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    private Qualification $qualification;

    private BookableService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->qualification = Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Elektrofachkraft',
            'is_active' => true,
            'created_by' => $this->worker->id,
        ]);
        $this->service = BookableService::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Elektroprüfung',
            'duration_minutes' => 60,
            'lead_time_hours' => 0,
            'cancel_hours' => 24,
            'buffer_minutes' => 0,
            'active' => true,
            'required_qualification_id' => $this->qualification->id,
        ]);

        // Freie Fenster stellt der Dispositions-Dienst — hier fixiert, es geht
        // ausschließlich um den Qualifikations-Filter davor.
        $gaps = $this->createMock(GapFillSuggester::class);
        $gaps->method('freeSlots')->willReturn([['start' => '08:00', 'end' => '12:00']]);
        $this->app->instance(GapFillSuggester::class, $gaps);
    }

    private function slots(): array {
        return app(AppointmentSlotService::class)
            ->slotsFor($this->service, CarbonImmutable::now()->addDays(7));
    }

    public function test_unqualified_staff_yields_no_slots(): void {
        $this->assertSame([], $this->slots());
    }

    public function test_valid_qualification_opens_the_slots(): void {
        $this->worker->qualifications()->attach($this->qualification->id, ['valid_until' => null]);

        $this->assertNotSame([], $this->slots());
    }

    public function test_expired_qualification_counts_as_missing(): void {
        $this->worker->qualifications()->attach($this->qualification->id, [
            'valid_until' => CarbonImmutable::now()->subDay()->toDateString(),
        ]);

        $this->assertSame([], $this->slots());
    }
}
