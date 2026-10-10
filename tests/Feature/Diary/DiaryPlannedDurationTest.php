<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryPlannedDurationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * „Geplante Dauer“ im Auftragsdialog (E13, MVP-1101): Eingabe als
 * Stunden:Minuten, leer → aus Zeitfenster bzw. Termindauer abgeleitet.
 */
class DiaryPlannedDurationTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'content' => 'Wartung Heizung',
            'status' => 2,
            'start_at' => '2030-01-15 09:00:00',
            'end_at' => '2030-01-15 10:00:00',
        ], $overrides);
    }

    public function test_dialog_shows_and_stores_the_planned_duration(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('diary.create'))
            ->assertOk()
            ->assertSee('name="planned_duration"', false)
            ->assertSee(__('diary.planned_duration.label'));

        $this->actingAs($user)->post(route('diary.store'), $this->payload(['planned_duration' => '1:30']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $entry = DiaryEntry::query()->where('content', 'Wartung Heizung')->firstOrFail();
        $this->assertSame(90, $entry->planned_minutes);

        $this->actingAs($user)->get(route('diary.edit', $entry))
            ->assertOk()
            ->assertSee('value="1:30"', false);
    }

    public function test_empty_duration_clears_and_missing_field_keeps_the_value(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create(['planned_minutes' => 120]);

        $this->actingAs($user)->put(route('diary.update', $entry), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(120, $entry->refresh()->planned_minutes);

        $this->actingAs($user)->put(route('diary.update', $entry), $this->payload(['planned_duration' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($entry->refresh()->planned_minutes);
    }

    public function test_invalid_duration_is_rejected(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('diary.store'), $this->payload(['planned_duration' => '90']))
            ->assertSessionHasErrors(['planned_duration' => __('diary.planned_duration.format')]);
        $this->actingAs($user)->post(route('diary.store'), $this->payload(['planned_duration' => '200:00']))
            ->assertSessionHasErrors(['planned_minutes' => __('diary.planned_duration.range')]);
        $this->assertDatabaseMissing('diary_entries', ['content' => 'Wartung Heizung']);
    }

    public function test_effective_duration_falls_back_to_service_time_window_then_appointment(): void {
        $entry = new DiaryEntry([
            'start_at' => '2030-01-15 09:00:00',
            'end_at' => '2030-01-15 11:15:00',
        ]);
        $this->assertSame(135, $entry->effectivePlannedMinutes());

        $entry->time_window_start = '08:00';
        $entry->time_window_end = '12:00';
        $this->assertSame(240, $entry->effectivePlannedMinutes());

        // E30: Servicedauer der Disposition vor dem Zeitfenster.
        $entry->service_minutes = 90;
        $this->assertSame(90, $entry->effectivePlannedMinutes());

        $entry->planned_minutes = 45;
        $this->assertSame(45, $entry->effectivePlannedMinutes());

        $this->assertNull((new DiaryEntry(['start_at' => '2030-01-15 09:00:00']))->effectivePlannedMinutes());
    }
}
