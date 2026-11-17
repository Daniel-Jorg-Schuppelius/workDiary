<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNotesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CarryForward;

use App\Models\Audit\AuditLog;
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Contracts\PeriodicOccurrence;
use App\Models\Platform\{Organization, User};
use App\Services\CarryForward\CarryForwardNotes;
use App\Support\MorphMap;
use App\Support\Period\PeriodKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Blade, Gate, Schema};
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645) am Test-Träger: der
 * Baustein ohne Fachträger. Ein Träger braucht nur den Vertrag und die
 * Komponente — genau das bildet {@see CarryForwardTestOccurrence} nach.
 */
final class CarryForwardNotesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string ALIAS = 'carry_forward_test_occurrences';

    private CarryForwardNotes $notes;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Schema::create(self::ALIAS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->string('series', 191)->nullable();
            $table->string('period', 10)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });
        Relation::morphMap([self::ALIAS => CarryForwardTestOccurrence::class]);
        config(['morph-map.aliases.' . self::ALIAS => CarryForwardTestOccurrence::class]);
        MorphMap::flush();
        Gate::policy(CarryForwardTestOccurrence::class, CarryForwardTestOccurrencePolicy::class);

        $this->notes = app(CarryForwardNotes::class);
        $this->worker = $this->orgUser();
    }

    protected function tearDown(): void {
        MorphMap::flush();
        parent::tearDown();
    }

    private function occurrence(?string $period, ?string $series = 'order:7:income-tax', ?Organization $organization = null): CarryForwardTestOccurrence {
        return CarryForwardTestOccurrence::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'series' => $series,
            'period' => $period,
        ]);
    }

    /** @return list<string> */
    private function open(CarryForwardTestOccurrence $occurrence): array {
        return array_values(array_map(static fn (CarryForwardNote $n): string => $n->note, $this->notes->forOccurrence($occurrence)['open']->all()));
    }

    public function test_a_note_left_this_year_appears_next_year_in_the_same_series_only(): void {
        $y2025 = $this->occurrence('2025');
        $this->notes->create($y2025, 'Nächstes Jahr Spendenbescheinigung anfordern', $this->worker);

        $this->assertSame([], $this->open($y2025), 'nicht am eigenen Vorkommen');
        $this->assertSame(['Nächstes Jahr Spendenbescheinigung anfordern'], $this->notes->forOccurrence($y2025)['left']->pluck('note')->all());
        $this->assertSame(['Nächstes Jahr Spendenbescheinigung anfordern'], $this->open($this->occurrence('2026')));
        $this->assertSame([], $this->open($this->occurrence('2026', 'order:7:annual-accounts')), 'nicht in einer anderen Reihe');
        $this->assertSame([], $this->open($this->occurrence('2024')), 'nicht in einer früheren Periode');

        $foreign = Organization::factory()->create();
        $this->assertSame([], $this->open($this->occurrence('2026', 'order:7:income-tax', $foreign)), 'nicht in einer anderen Organisation');

        $note = CarryForwardNote::query()->sole();
        $this->assertSame('2026', $note->period_key?->key());
        $this->assertSame('2025', $note->origin_period_key?->key());
        $this->assertSame('2026-01-01', $note->valid_from->format('Y-m-d'));
        $this->assertSame(self::ALIAS, $note->origin_type);
        $this->assertSame((int) $this->worker->id, $note->created_by);
    }

    public function test_a_skipped_period_does_not_lose_the_note_and_ticking_off_ends_it(): void {
        $this->notes->create($this->occurrence('2025'), 'Vollmacht erneuern', $this->worker);
        $y2027 = $this->occurrence('2027');

        $this->assertSame(['Vollmacht erneuern'], $this->open($y2027), '2026 ausgelassen');

        $resolver = $this->orgUser();
        $this->notes->resolve(CarryForwardNote::query()->sole(), $y2027, $resolver, ' Vollmacht liegt vor ');

        $done = $this->notes->forOccurrence($y2027);
        $this->assertSame([], $done['open']->all());
        $this->assertSame(['Vollmacht erneuern'], $done['resolved']->pluck('note')->all());
        $this->assertSame([], $this->open($this->occurrence('2028')), 'abgehakt erscheint er nicht mehr');

        $note = CarryForwardNote::query()->sole();
        $this->assertSame('Vollmacht liegt vor', $note->resolution_note);
        $this->assertSame((int) $resolver->id, $note->resolved_by);
        $this->assertSame((int) $y2027->id, $note->resolved_occurrence_id);

        $audit = AuditLog::query()->withoutGlobalScopes()
            ->where('auditable_type', MorphMap::stableKey(CarryForwardNote::class))
            ->where('auditable_id', $note->id)
            ->pluck('event')->all();
        $this->assertContains('created', $audit);
        $this->assertContains('carry_forward_note.resolved', $audit);
    }

    public function test_periods_of_another_kind_compare_by_their_days(): void {
        // Zielperiode ab 2026 trifft den März 2026; ab dem zweiten Quartal trifft sie das Jahr, nicht das erste Quartal.
        $this->notes->create($this->occurrence('2025'), 'Jahresmerker', $this->worker);
        $this->notes->create($this->occurrence('2026-Q1'), 'Quartalsmerker', $this->worker);

        $this->assertSame(['Jahresmerker'], $this->open($this->occurrence('2026-03')));
        $this->assertSame(['Jahresmerker'], $this->open($this->occurrence('2026-Q1')));
        $this->assertSame(['Jahresmerker', 'Quartalsmerker'], $this->open($this->occurrence('2026')));
        $this->assertSame(['Jahresmerker', 'Quartalsmerker'], $this->open($this->occurrence('2026-05')));
    }

    public function test_the_target_period_must_lie_after_the_own_period(): void {
        $occurrence = $this->occurrence('2026-Q2');
        $this->notes->create($occurrence, 'Zwei Quartale später', $this->worker, PeriodKey::parse('2026-Q4'));
        $this->assertSame(['Zwei Quartale später'], $this->open($this->occurrence('2026-Q4')));
        $this->assertSame([], $this->open($this->occurrence('2026-Q3')));

        $this->expectException(ValidationException::class);
        $this->notes->create($occurrence, 'Gleiche Periode', $this->worker, PeriodKey::parse('2026'));
    }

    public function test_a_note_is_only_ticked_off_where_it_is_due_and_only_once(): void {
        $this->notes->create($this->occurrence('2025'), 'Merker', $this->worker);
        $note = CarryForwardNote::query()->sole();

        foreach ([$this->occurrence('2025'), $this->occurrence('2026', 'order:8:income-tax')] as $wrong) {
            try {
                $this->notes->resolve($note, $wrong, $this->worker, null);
                $this->fail('Abhaken außerhalb der Fälligkeit oder Reihe muss scheitern.');
            } catch (ValidationException) {
                $this->assertTrue($note->fresh()?->isOpen());
            }
        }

        $due = $this->occurrence('2026');
        $this->notes->resolve($note, $due, $this->worker, null);
        $this->assertNull($note->refresh()->resolution_note);

        $this->expectException(ValidationException::class);
        $this->notes->resolve($note, $due, $this->worker, null);
    }

    public function test_an_occurrence_without_series_or_period_has_no_notes(): void {
        $this->assertSame([], $this->open($this->occurrence(null)));
        $this->assertSame([], $this->open($this->occurrence('2026', null)));

        $this->expectException(ValidationException::class);
        $this->notes->create($this->occurrence(null), 'Ohne Periode', $this->worker);
    }

    public function test_routes_write_only_with_the_update_right_of_the_occurrence(): void {
        $y2025 = $this->occurrence('2025');
        $payload = ['subject_type' => self::ALIAS, 'subject_id' => (string) $y2025->id, 'note' => 'Über die Oberfläche', 'period_key' => '2027'];

        $this->actingAs($this->worker)->post(route('carry-forward-notes.store'), $payload)->assertRedirect();
        $note = CarryForwardNote::query()->sole();
        $this->assertSame('2027', $note->period_key?->key());

        $y2025->update(['is_locked' => true]);
        $this->actingAs($this->worker)->post(route('carry-forward-notes.store'), $payload)->assertForbidden();
        $this->actingAs($this->worker)->post(route('carry-forward-notes.store'), ['subject_type' => 'diary_entries'] + $payload)->assertNotFound();
        $this->actingAs($this->worker)->post(route('carry-forward-notes.store'), ['subject_type' => 'unbekannt'] + $payload)->assertNotFound();

        $foreign = $this->occurrence('2025', 'order:7:income-tax', Organization::factory()->create());
        $this->actingAs($this->worker)->post(route('carry-forward-notes.store'), ['subject_id' => (string) $foreign->id] + $payload)->assertNotFound();

        $y2027 = $this->occurrence('2027');
        $this->actingAs($this->worker)->patch(route('carry-forward-notes.resolve', $note), [
            'subject_type' => self::ALIAS,
            'subject_id' => (string) $y2027->id,
            'resolution_note' => 'Erledigt',
        ])->assertRedirect();
        $this->assertSame('Erledigt', $note->fresh()?->resolution_note);
        $this->assertSame(1, CarryForwardNote::query()->count());
    }

    public function test_only_the_author_withdraws_an_open_note(): void {
        $this->notes->create($this->occurrence('2025'), 'Tippfehler', $this->worker);
        $note = CarryForwardNote::query()->sole();

        $this->actingAs($this->orgUser())->delete(route('carry-forward-notes.destroy', $note))->assertForbidden();
        $this->actingAs($this->worker)->delete(route('carry-forward-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, CarryForwardNote::query()->count());

        $this->notes->create($this->occurrence('2025'), 'Erledigt', $this->worker);
        $done = CarryForwardNote::query()->sole();
        $this->notes->resolve($done, $this->occurrence('2026'), $this->worker, null);
        $this->actingAs($this->worker)->delete(route('carry-forward-notes.destroy', $done))->assertForbidden();
    }

    public function test_component_shows_open_notes_and_forms_only_with_rights(): void {
        $this->notes->create($this->occurrence('2025'), 'Bitte Belege anfordern', $this->worker);
        $y2026 = $this->occurrence('2026');

        $this->actingAs($this->worker);
        // Außerhalb einer Anfrage teilt keine Middleware den Fehler-Bag.
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-carry-forward-notes :subject="$s" />', ['s' => $y2026]);
        $this->assertStringContainsString('Bitte Belege anfordern', $html);
        $this->assertStringContainsString(route('carry-forward-notes.resolve', CarryForwardNote::query()->sole()), $html);
        $this->assertStringContainsString('name="period_key"', $html);
        $this->assertStringContainsString('value="2027"', $html);

        $y2026->update(['is_locked' => true]);
        $readOnly = Blade::render('<x-carry-forward-notes :subject="$s" />', ['s' => $y2026->fresh()]);
        $this->assertStringContainsString('Bitte Belege anfordern', $readOnly);
        $this->assertStringNotContainsString('name="resolution_note"', $readOnly);
        $this->assertStringNotContainsString(route('carry-forward-notes.store'), $readOnly);

        $this->assertSame('', trim(Blade::render('<x-carry-forward-notes :subject="$s" />', ['s' => $this->occurrence(null)])));
    }
}

/**
 * Test-Träger: ein Vorkommen mit Reihe und Periode aus eigenen Spalten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $series
 * @property string|null $period
 * @property bool $is_locked
 */
class CarryForwardTestOccurrence extends Model implements PeriodicOccurrence {
    use BelongsToOrganization;

    protected $table = 'carry_forward_test_occurrences';

    protected $fillable = ['organization_id', 'series', 'period', 'is_locked'];

    /** @var array<string, string> */
    protected $casts = ['is_locked' => 'boolean'];

    public function carryForwardSeries(): ?string {
        return $this->series;
    }

    public function carryForwardPeriod(): ?PeriodKey {
        return PeriodKey::tryParse($this->period);
    }
}

/** Sehen in der eigenen Organisation, bearbeiten nur, solange nicht gesperrt. */
class CarryForwardTestOccurrencePolicy {
    public function view(User $user, CarryForwardTestOccurrence $occurrence): bool {
        return (int) $user->organization_id === (int) $occurrence->organization_id;
    }

    public function update(User $user, CarryForwardTestOccurrence $occurrence): bool {
        return $this->view($user, $occurrence) && ! $occurrence->is_locked;
    }
}
