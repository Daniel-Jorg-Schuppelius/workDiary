<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchCalendarAndMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\{Mode, Status};
use App\Models\{DiaryEntry, Qualification, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Ränge 52/53: Dispositions-Kalender (Zeilen=Mitarbeitende, Zellen=Tage)
 * und Auftrags-Qualifikationsmatrix (erfüllt/läuft ab/fehlt via
 * QualificationGate).
 */
class DispatchCalendarAndMatrixTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function dispatcher(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function worker(string $name = 'Monteur Meier'): User {
        return User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
        ]);
    }

    private function entry(User $assignee, string $start, string $end, string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $assignee->id,
            'assigned_user_id' => $assignee->id,
            'title' => $title,
            'mode' => Mode::Fixed->value,
            'status' => Status::Open->value,
            'start_at' => Carbon::parse($start),
            'end_at' => Carbon::parse($end),
        ]);
    }

    public function test_calendar_requires_permission_and_places_entries_on_days(): void {
        $worker = $this->worker();
        // Mehrtägiger Auftrag erscheint an beiden überlappten Tagen.
        $this->entry($worker, '2026-07-01 09:00', '2026-07-02 12:00', 'Anlagenmontage Halle 3');

        $this->actingAs($this->worker('Ohne Recht'))
            ->get(route('dispatch.calendar'))
            ->assertForbidden();

        $response = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.calendar', ['from' => '2026-07-01', 'to' => '2026-07-03']));

        $response->assertOk();
        $response->assertSee('Monteur Meier');
        $response->assertSee('Anlagenmontage Halle 3');
        // Zwei Tageszellen mit demselben Auftrag (mehrtägig) — je Zelle
        // erscheint der Titel im title-Attribut UND als Text (2 × 2 = 4).
        $this->assertSame(4, substr_count((string) $response->getContent(), 'Anlagenmontage Halle 3'));
    }

    public function test_calendar_caps_long_ranges_to_14_days(): void {
        $response = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.calendar', ['from' => '2026-07-01', 'to' => '2026-09-30']));

        $response->assertOk();
        $response->assertSee(__('Zeitraum auf 14 Tage gekappt — für längere Zeiträume das Board nutzen.'));
    }

    public function test_requirements_can_be_managed_with_dispatch_permission_only(): void {
        $worker = $this->worker();
        $entry = $this->entry($worker, '2026-07-01 09:00', '2026-07-01 12:00', 'Wartung');
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);

        // Ohne Recht: verboten (weder DispatchManage noch update am fremden Auftrag).
        $this->actingAs($this->worker('Fremd'))
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$qualification->sqid]])
            ->assertForbidden();

        $this->actingAs($this->dispatcher())
            ->put(route('diary.qualifications.update', $entry), ['qualifications' => [$qualification->sqid]])
            ->assertRedirect();

        $this->assertDatabaseHas('diary_entry_qualifications', [
            'diary_entry_id' => $entry->id,
            'qualification_id' => $qualification->id,
        ]);

        // Leere Auswahl räumt die Anforderungen.
        $this->actingAs($this->dispatcher())
            ->put(route('diary.qualifications.update', $entry), [])
            ->assertRedirect();
        $this->assertSame(0, $entry->requiredQualifications()->count());
    }

    public function test_matrix_shows_ok_expiring_and_missing(): void {
        $assignee = $this->worker('Monteur Ok');
        $expiringUser = $this->worker('Monteur Bald');
        $this->worker('Monteur Ohne');

        $entry = $this->entry($assignee, '2026-07-10 08:00', '2026-07-10 16:00', 'Prüfung Kessel');
        $qualification = Qualification::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kesselwart',
            'abbreviation' => 'KW',
        ]);
        $entry->requiredQualifications()->sync([$qualification->id]);

        // Gültig ohne baldigen Ablauf.
        $assignee->qualifications()->attach($qualification->id, ['valid_from' => '2026-01-01', 'valid_until' => '2027-12-31']);
        // Gültig am Stichtag, läuft aber binnen 30 Tagen ab.
        $expiringUser->qualifications()->attach($qualification->id, ['valid_from' => '2026-01-01', 'valid_until' => '2026-07-20']);

        $response = $this->actingAs($this->dispatcher())->get(route('dispatch.qualifications', $entry));

        $response->assertOk();
        // Kopfzeile zeigt das Kürzel (abbreviation vor name).
        $response->assertSee('KW');
        $response->assertSee(__('erfüllt'));
        $response->assertSee(__('läuft ab'));
        $response->assertSee(__('fehlt'));
        $response->assertSee(__('zugewiesen'));
    }
}
