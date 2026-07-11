<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GapFillSuggesterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Dispatch;

use App\Enums\Diary\{Mode, Status};
use App\Models\{Customer, DiaryEntry, DiaryEntryEvent, Project, Qualification, User};
use App\Services\Dispatch\GapFillSuggester;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Epic 14.2 (MVP-245): Leerzeit-Vorschläge — freie Slots aus belegten
 * Auftragszeiten, Kandidatenfilter (Korridor, Dauer, Qualifikation,
 * Uhrzeitfenster), Haversine sichtbar als Schätzung, Übernahme/Ablehnung
 * als bewusste, im Auftragsverlauf nachvollziehbare Aktionen.
 */
final class GapFillSuggesterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;

    private User $dispatcher;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->dispatcher = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_lat' => 52.5200000,
            'address_lng' => 13.4050000,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeEntry(array $overrides = []): DiaryEntry {
        return DiaryEntry::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->dispatcher->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'title' => 'Wartung Heizung',
            'content' => 'Turnuswartung',
            'status' => Status::Planned->value,
            'mode' => Mode::Backlog->value,
            'service_minutes' => 90,
        ], $overrides));
    }

    public function test_free_slots_subtract_busy_assignments(): void {
        $date = CarbonImmutable::parse('2026-07-13'); // Montag
        // Belegter Vormittag: disponierter Auftrag 09:00–12:00.
        $this->makeEntry([
            'assigned_user_id' => $this->worker->id,
            'mode' => Mode::Fixed->value,
            'scheduled_for' => $date->toDateString(),
            'time_window_start' => '09:00',
            'time_window_end' => '12:00',
        ]);

        $slots = app(GapFillSuggester::class)->freeSlots($this->worker, $date);

        $this->assertSame([['start' => '08:00', 'end' => '09:00', 'net_minutes' => 60], ['start' => '12:00', 'end' => '17:00', 'net_minutes' => 300]], $slots);
    }

    public function test_suggestions_respect_duration_qualification_and_corridor(): void {
        $date = CarbonImmutable::parse('2026-07-13');

        // Passt: Backlog-Auftrag, 90 Min., ohne Pflichtqualifikation.
        $this->makeEntry(['title' => 'Passt']);
        // Zu lang für jeden Slot (10 h).
        $this->makeEntry(['title' => 'Zu lang', 'service_minutes' => 600]);
        // Korridor abgelaufen (Window bis gestern).
        $this->makeEntry(['title' => 'Abgelaufen', 'mode' => Mode::Window->value, 'window_start_date' => $date->subDays(10)->toDateString(), 'window_end_date' => $date->subDay()->toDateString()]);
        // Pflichtqualifikation, die der Mitarbeiter nicht hat.
        $qualified = $this->makeEntry(['title' => 'Quali nötig']);
        $qualification = Qualification::query()->create(['organization_id' => $this->organization->id, 'name' => 'Elektrofachkraft']);
        $qualified->requiredQualifications()->attach($qualification->id);

        $suggestions = app(GapFillSuggester::class)->suggestFor($this->worker, $date);

        $titles = array_map(fn(array $s): string => (string) $s['entry']->title, $suggestions);
        $this->assertContains('Passt', $titles);
        $this->assertNotContains('Zu lang', $titles);
        $this->assertNotContains('Abgelaufen', $titles);
        $this->assertNotContains('Quali nötig', $titles);

        // Begründung nennt Slot, Dauer und (ohne OSRM) die gekennzeichnete Schätzung.
        $suggestion = $suggestions[array_search('Passt', $titles, true)];
        $this->assertStringContainsString('Freier Slot', $suggestion['reasons'][0]);
        $this->assertTrue($suggestion['distance_is_estimate']);
    }

    public function test_apply_assigns_entry_and_writes_lifecycle_event(): void {
        $date = CarbonImmutable::parse('2026-07-13');
        $entry = $this->makeEntry();

        $this->actingAs($this->dispatcher)->post(route('dispatch.suggestions.apply', $entry), [
            'user_id' => $this->worker->sqid,
            'date' => $date->toDateString(),
            'start' => '13:00',
            'duration' => 90,
        ])->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertSame((int) $this->worker->id, (int) $fresh->assigned_user_id);
        $this->assertSame($date->toDateString(), $fresh->scheduled_for->toDateString());
        $this->assertSame('13:00', substr((string) $fresh->time_window_start, 0, 5));
        $this->assertSame('planned', (string) $fresh->getAttribute('dispatch_status'));

        $this->assertDatabaseHas('diary_entry_events', [
            'diary_entry_id' => $entry->id,
            'event' => 'dispatch.gap_fill_applied',
        ]);
    }

    public function test_dismiss_logs_and_suppresses_suggestion(): void {
        $date = CarbonImmutable::parse('2026-07-13');
        $entry = $this->makeEntry(['title' => 'Abgelehnt']);

        $this->actingAs($this->dispatcher)->post(route('dispatch.suggestions.dismiss', $entry), [
            'user_id' => $this->worker->sqid,
            'date' => $date->toDateString(),
            'reason' => 'Kunde erst nächste Woche vor Ort',
        ])->assertRedirect();

        $this->assertSame(1, DiaryEntryEvent::query()->where('event', 'dispatch.gap_fill_dismissed')->count());

        $titles = array_map(fn(array $s): string => (string) $s['entry']->title, app(GapFillSuggester::class)->suggestFor($this->worker, $date));
        $this->assertNotContains('Abgelehnt', $titles);
    }

    public function test_suggestions_page_requires_dispatch_permission(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->get(route('dispatch.suggestions'))->assertForbidden();

        $this->actingAs($this->dispatcher)
            ->get(route('dispatch.suggestions', ['user_id' => $this->worker->sqid, 'date' => '2026-07-13']))
            ->assertOk()
            ->assertSee('Leerzeit');
    }
}
