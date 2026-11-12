<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldsWithoutSenderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\Asset\{Asset, MeterReading};
use App\Models\Platform\User;
use App\Models\Travel\PerDiemTrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1619 (G-08): Felder, die der Server annahm, aber kein Formular sendete —
 * jetzt mit Eingabe und Wirkung.
 */
class FieldsWithoutSenderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_meter_reading_photo_is_stored_as_attachment(): void {
        Storage::fake('local');
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('meter-readings.create', ['asset' => $asset->sqid]))
            ->assertOk()->assertSee('name="photo"', false);

        $this->actingAs($this->admin)->post(route('meter-readings.store'), [
            'asset_id' => $asset->id,
            'value' => '1234.5',
            'unit' => 'kWh',
            'photo' => UploadedFile::fake()->image('zaehler.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $reading = MeterReading::query()->where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame(1, $reading->attachments()->count());
        $this->actingAs($this->admin)->get(route('meter-readings.index'))
            ->assertSee(route('attachments.download', $reading->attachments()->firstOrFail()), false);
    }

    public function test_event_reminders_are_normalized_and_empty_means_category_default(): void {
        $payload = [
            'title' => 'Sicherheitsunterweisung',
            'event_type' => \App\Enums\Event\EventType::cases()[0]->value,
            'started_at' => now()->addWeek()->setTime(9, 0)->format('Y-m-d\TH:i'),
            'ended_at' => now()->addWeek()->setTime(11, 0)->format('Y-m-d\TH:i'),
            'status' => \App\Enums\Event\EventStatus::cases()[0]->value,
            'visibility' => \App\Enums\Event\EventVisibility::cases()[0]->value,
            'responsible_user_id' => $this->admin->sqid,
            'reminder_overrides' => ['60', '1440', '60'],
        ];

        $this->actingAs($this->admin)->post(route('events.store'), $payload)->assertSessionHasNoErrors();
        $event = \App\Models\Calendar\Event::query()->where('title', 'Sicherheitsunterweisung')->firstOrFail();
        $this->assertSame([1440, 60], $event->reminder_overrides);
        $this->actingAs($this->admin)->get(route('events.edit', $event))->assertOk()->assertSee('data-field="reminder_overrides"', false);

        unset($payload['reminder_overrides']);
        $this->actingAs($this->admin)->put(route('events.update', $event), $payload)->assertSessionHasNoErrors();
        $this->assertNull($event->fresh()?->reminder_overrides);
    }

    public function test_per_diem_workplace_key_is_normalized_so_the_three_month_rule_counts_per_place(): void {
        $trip = PerDiemTrip::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'location' => 'Werk Nord',
            'workplace_key' => '  Kunde Beispiel GmbH, Halle 3 ',
        ]);

        $this->assertSame('kunde beispiel gmbh, halle 3', $trip->fresh()?->workplace_key);
    }
}
