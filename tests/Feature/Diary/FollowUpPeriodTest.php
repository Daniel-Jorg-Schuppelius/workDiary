<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FollowUpPeriodTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Models\Audit\AuditLog;
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Services\CarryForward\CarryForwardNotes;
use App\Services\Diary\FollowUpPeriodService;
use App\Support\MorphMap;
use App\Support\Period\PeriodKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Folgeperiode (Feature 198, MVP-1637) und Auftrag als Träger der Merker
 * (Feature 201, MVP-1645): Übernahmeregeln, Dublettenschutz, Sammelanlage;
 * Merker erscheinen in der Folgeperiode derselben Reihe.
 */
final class FollowUpPeriodTest extends TestCase {
    use RefreshDatabase;

    private User $lead;

    private User $worker;

    private EntryType $taxReturn;

    private FollowUpPeriodService $followUps;

    protected function setUp(): void {
        parent::setUp();
        $this->lead = User::factory()->teamleitung()->create();
        $this->worker = User::factory()->user()->create(['organization_id' => $this->lead->organization_id]);
        app()->instance('currentOrganization', $this->lead->organization);
        $this->taxReturn = EntryType::factory()->create([
            'organization_id' => $this->lead->organization_id,
            'slug' => 'steuererklaerung',
            'label' => 'Steuererklärung',
            'requires_period' => [PeriodKind::Year->value],
            'requires_review' => true,
        ]);
        $this->followUps = app(FollowUpPeriodService::class);
    }

    private function productGroup(string $code): Classification {
        return Classification::query()->create([
            'organization_id' => $this->lead->organization_id,
            'domain' => ClassificationDomain::ProductGroup,
            'code' => $code,
            'label' => ucfirst($code),
            'sort_order' => 10,
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function order(Customer $customer, string $period, ?string $productGroup = 'einkommensteuer', ?EntryType $type = null, array $attributes = []): DiaryEntry {
        $entry = DiaryEntry::factory()->for($this->worker)->create(array_merge([
            'organization_id' => $this->lead->organization_id,
            'entry_type_id' => ($type ?? $this->taxReturn)->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $this->worker->id,
            'title' => 'Einkommensteuer ' . $period,
            'content' => 'Unterlagen ' . $period . ' vollständig, interne Notiz.',
            'status' => Status::Completed,
            'period_key' => $period,
            'reviewer_user_id' => $this->lead->id,
        ], $attributes));
        if ($productGroup !== null) {
            $entry->assignProductGroup($productGroup);
        }

        return $entry->refresh();
    }

    public function test_follow_up_copies_the_agreed_fields_and_nothing_else(): void {
        $this->productGroup('einkommensteuer');
        $customer = Customer::factory()->create(['organization_id' => $this->lead->organization_id]);
        $source = $this->order($customer, '2025');
        TimeEntry::factory()->create(['organization_id' => $source->organization_id, 'diary_entry_id' => $source->id, 'user_id' => $this->worker->id]);

        $next = $this->followUps->create($source, $this->lead);

        $this->assertSame('2026', $next->period_key?->key());
        $this->assertSame($customer->id, $next->customer_id);
        $this->assertSame($this->taxReturn->id, $next->entry_type_id);
        $this->assertSame('einkommensteuer', $next->productGroupCode());
        $this->assertSame($this->worker->id, $next->assigned_user_id);
        $this->assertTrue($next->requires_review);
        $this->assertSame($this->lead->id, $next->reviewer_user_id);
        $this->assertSame('Einkommensteuer 2026', $next->title);
        $this->assertSame(Status::Planned, $next->status);
        $this->assertNull($next->due_date, 'Ohne Kundenfristen (MVP-1632) bleibt die Fälligkeit leer.');
        $this->assertStringNotContainsString('interne Notiz', (string) $next->content);
        $this->assertSame(0, $next->timeEntries()->count());
        $this->assertNull($next->completion_summary);
    }

    public function test_duplicate_follow_up_is_rejected_with_a_hint_to_the_existing_order(): void {
        $this->productGroup('einkommensteuer');
        $this->productGroup('gewerbesteuer');
        $customer = Customer::factory()->create(['organization_id' => $this->lead->organization_id]);
        $source = $this->order($customer, '2025');
        $existing = $this->followUps->create($source, $this->lead);

        try {
            $this->followUps->create($source, $this->lead);
            $this->fail('Dublette angelegt.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('#' . $existing->id, (string) collect($e->errors())->flatten()->first());
        }

        // Eine andere Produktgruppe derselben Art ist eine eigene Reihe.
        $trade = $this->order($customer, '2025', 'gewerbesteuer', attributes: ['title' => 'Gewerbesteuer 2025']);
        $this->assertSame('gewerbesteuer', $this->followUps->create($trade, $this->lead)->productGroupCode());

        // Über die Route: Hinweis statt zweitem Auftrag.
        $this->actingAs($this->lead)->post(route('diary.follow-up.store', $source))->assertSessionHasErrors('period_key');
        $this->assertSame(1, DiaryEntry::query()->where('customer_id', $customer->id)->where('period_key', '2026')->whereHas('classifications', static fn ($q) => $q->where('code', 'einkommensteuer'))->count());
    }

    /** Akzeptanzkriterium 4: 30 Aufträge „Einkommensteuer 2025“ ergeben 30 Aufträge 2026 und keinen doppelten. */
    public function test_bulk_creates_thirty_orders_and_no_duplicates(): void {
        $this->productGroup('einkommensteuer');
        $customers = Customer::factory()->count(30)->create(['organization_id' => $this->lead->organization_id]);
        foreach ($customers as $customer) {
            $this->order($customer, '2025');
        }

        $preview = $this->followUps->preview($this->taxReturn, PeriodKey::parse('2025'));
        $this->assertCount(30, $preview);
        $this->assertSame(30, count(array_filter($preview, static fn (array $row): bool => $row['blocked'] === null)));

        $this->actingAs($this->lead)->get(route('diary.follow-up.index', ['entry_type' => $this->taxReturn->sqid, 'period_key' => '2025']))
            ->assertOk()
            ->assertSee(__('diary.follow_up.bulk.submit', ['count' => 30]));
        $this->actingAs($this->lead)->post(route('diary.follow-up.bulk'), ['entry_type' => $this->taxReturn->sqid, 'period_key' => '2025'])
            ->assertRedirect();

        $this->assertSame(30, DiaryEntry::query()->where('entry_type_id', $this->taxReturn->id)->where('period_key', '2026')->count());

        $again = $this->followUps->createMany($this->taxReturn, PeriodKey::parse('2025'), $this->lead);
        $this->assertSame([], $again['created']);
        $this->assertSame(30, $again['skipped']);
        $this->assertSame(30, DiaryEntry::query()->where('entry_type_id', $this->taxReturn->id)->where('period_key', '2026')->count());
    }

    public function test_bulk_page_needs_the_rights_to_create_for_others(): void {
        $this->actingAs($this->worker)->get(route('diary.follow-up.index'))->assertForbidden();
        $this->actingAs($this->worker)->post(route('diary.follow-up.bulk'), ['entry_type' => $this->taxReturn->sqid, 'period_key' => '2025'])->assertForbidden();
    }

    public function test_order_is_occurrence_of_its_series_only_with_a_period(): void {
        $this->productGroup('einkommensteuer');
        $customer = Customer::factory()->create(['organization_id' => $this->lead->organization_id]);
        $entry = $this->order($customer, '2025');
        $this->assertSame('order:' . $customer->id . ':' . $this->taxReturn->id . ':einkommensteuer', $entry->carryForwardSeries());
        $this->assertSame('2025', $entry->carryForwardPeriod()?->key());

        $plain = DiaryEntry::factory()->for($this->worker)->create(['organization_id' => $this->lead->organization_id, 'customer_id' => $customer->id, 'entry_type_id' => $this->taxReturn->id]);
        $this->assertNull($plain->carryForwardSeries());
        $this->assertNull($plain->carryForwardPeriod());
    }

    /**
     * Feature 201, Akzeptanzkriterien 1 und 2: der Merker am Auftrag 2025
     * erscheint 2026 desselben Kunden, nicht beim Jahresabschluss; abgehakt
     * erscheint er 2027 nicht mehr, der Vermerk steht im Verlauf.
     */
    public function test_carry_forward_note_travels_to_the_next_period_of_the_same_series(): void {
        $this->productGroup('einkommensteuer');
        $this->productGroup('jahresabschluss');
        $closing = EntryType::factory()->create(['organization_id' => $this->lead->organization_id, 'slug' => 'abschluss', 'requires_period' => ['year']]);
        $customer = Customer::factory()->create(['organization_id' => $this->lead->organization_id]);
        $notes = app(CarryForwardNotes::class);

        $income2025 = $this->order($customer, '2025');
        $note = $notes->create($income2025, 'Spendenbescheinigung vorab anfordern.', $this->worker);
        $closing2026 = $this->order($customer, '2026', 'jahresabschluss', $closing, ['title' => 'Jahresabschluss 2026']);

        $income2026 = $this->followUps->create($income2025, $this->lead);
        $this->assertSame([$note->id], $notes->forOccurrence($income2026)['open']->modelKeys());
        $this->assertSame([], $notes->forOccurrence($closing2026)['open']->modelKeys());

        $this->actingAs($this->worker)->get(route('diary.show', $income2026))
            ->assertOk()
            ->assertSee('Spendenbescheinigung vorab anfordern.');

        // Abhaken darf, wer den Auftrag bearbeiten darf (Recht des Trägers) — hier die Teamleitung, die ihn angelegt hat.
        $this->actingAs($this->lead)->patch(route('carry-forward-notes.resolve', $note), [
            'subject_type' => $income2026->getMorphClass(),
            'subject_id' => $income2026->getRouteKey(),
            'resolution_note' => 'Bescheinigung liegt vor.',
        ])->assertRedirect();

        $income2027 = $this->followUps->create($income2026, $this->lead);
        $this->assertSame([], $notes->forOccurrence($income2027)['open']->modelKeys());
        $this->assertSame('Bescheinigung liegt vor.', CarryForwardNote::query()->findOrFail($note->id)->resolution_note);
        $this->assertTrue(AuditLog::query()->withoutGlobalScopes()
            ->where('auditable_type', MorphMap::stableKey(CarryForwardNote::class))
            ->where('auditable_id', $note->id)
            ->where('event', 'carry_forward_note.resolved')
            ->exists());
    }
}
