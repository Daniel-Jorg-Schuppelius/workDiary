<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Periode am Auftrag (Feature 198, MVP-1634): Pflicht je Eintragsart beim
 * Anlegen, kanonischer Schlüssel, Filter und Anzeige; Branchenprofile
 * Steuerberatung und ambulante Pflege setzen die Pflicht.
 */
final class PeriodOrderTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->organization = Organization::query()->findOrFail($this->admin->organization_id);
        app()->instance('currentOrganization', $this->organization);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(EntryType $type, Customer $customer, array $extra = []): array {
        return $extra + [
            'content' => 'Buchhaltung des Monats',
            'status' => Status::Planned->value,
            'mode' => 'backlog',
            'entry_type_id' => $type->sqid,
            'customer_id' => $customer->sqid,
        ];
    }

    /** Akzeptanzkriterium 5: ein Auftrag `fibu` ohne Periode lässt sich im Steuerberater-Profil nicht anlegen. */
    public function test_steuerberater_profile_requires_a_month_for_fibu(): void {
        (new BranchProfileInstaller)->install($this->organization, 'steuerberater', $this->admin);
        $fibu = EntryType::query()->where('organization_id', $this->admin->organization_id)->where('slug', 'fibu')->firstOrFail();
        $customer = Customer::factory()->create(['organization_id' => $this->admin->organization_id]);

        $this->actingAs($this->admin)->post(route('diary.store'), $this->payload($fibu, $customer))
            ->assertSessionHasErrors('period_key');
        $this->actingAs($this->admin)->post(route('diary.store'), $this->payload($fibu, $customer, ['period_key' => '2026']))
            ->assertSessionHasErrors('period_key');
        $this->assertSame(0, DiaryEntry::query()->where('entry_type_id', $fibu->id)->count());

        $this->actingAs($this->admin)->post(route('diary.store'), $this->payload($fibu, $customer, ['period_key' => ' 2026-03 ']))
            ->assertSessionHasNoErrors();
        $entry = DiaryEntry::query()->where('entry_type_id', $fibu->id)->firstOrFail();
        $this->assertSame('2026-03', $entry->period_key?->key());
        $this->assertSame(PeriodKind::Month, $entry->period_key->kind);
    }

    public function test_profiles_create_entry_types_with_period_kinds_and_keep_the_stock(): void {
        $organization = $this->organization;
        // Bestand: die Organisation hat „lohn“ schon ohne Pflicht — das bleibt so.
        EntryType::factory()->create(['organization_id' => $organization->id, 'slug' => 'lohn', 'label' => 'Lohn (eigen)', 'requires_period' => null]);

        $result = (new BranchProfileInstaller)->install($organization, 'steuerberater', $this->admin);
        // v3 Leistungsort (MVP-1688), v4 (Feature 198), v5 Kundenfristen (Feature 197), v6 Leistungsvorlagen (Feature 199), v7 Prüfpaket GwG (Feature 200).
        $this->assertSame(7, $result['version']);
        $kinds = EntryType::query()->where('organization_id', $organization->id)->get()->mapWithKeys(
            static fn (EntryType $t): array => [$t->slug => array_map(static fn (PeriodKind $k): string => $k->value, $t->requiredPeriodKinds())],
        )->all();
        $this->assertSame(['month'], $kinds['fibu']);
        $this->assertSame(['month', 'quarter'], $kinds['voranmeldung']);
        $this->assertSame(['year'], $kinds['steuererklaerung']);
        $this->assertSame(['year'], $kinds['abschluss']);
        $this->assertSame([], $kinds['lohn'], 'Bestehende Eintragsart still geändert.');
        $this->assertSame('Lohn (eigen)', EntryType::query()->where('organization_id', $organization->id)->where('slug', 'lohn')->value('label'));

        // Erneute Installation legt nichts doppelt an.
        $count = EntryType::query()->where('organization_id', $organization->id)->count();
        (new BranchProfileInstaller)->install($organization, 'steuerberater', $this->admin);
        $this->assertSame($count, EntryType::query()->where('organization_id', $organization->id)->count());

        $care = (new BranchProfileInstaller)->install($organization, 'pflege', $this->admin);
        // v7 (Feature 198), v8 Beratungsbesuch als Kundenfrist (Feature 197, MVP-1673): Quartal für Pflegegrad 4–5 auf Wunsch.
        // v9: Pflegevisite und Leistungsvorlagen (Feature 199, MVP-1674); v10: Prüfpaket Pflege-Erstaufnahme (Feature 200, MVP-1675).
        $this->assertSame(10, $care['version']);
        $visit = EntryType::query()->where('organization_id', $organization->id)->where('slug', 'beratungsbesuch')->firstOrFail();
        $this->assertSame([PeriodKind::HalfYear, PeriodKind::Quarter], $visit->requiredPeriodKinds());
    }

    public function test_period_is_shown_and_filtered_in_the_list_and_head(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->admin->organization_id]);
        $march = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id, 'customer_id' => $customer->id, 'period_key' => '2026-03', 'content' => 'Monat März', 'mode' => 'backlog']);
        DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id, 'period_key' => '2026-04', 'content' => 'Monat April', 'mode' => 'backlog']);

        $this->actingAs($this->admin)->get(route('diary.index', ['period' => '2026-03']))
            ->assertOk()
            ->assertSee('Monat März')
            ->assertDontSee('Monat April');

        $this->actingAs($this->admin)->get(route('diary.show', $march))
            ->assertOk()
            ->assertSee(__('diary.period.badge', ['period' => $march->period_key?->label()]));
    }

    public function test_period_survives_editing_and_api_reads_it(): void {
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->admin->organization_id, 'period_key' => '2025-Q4']);

        $this->assertSame('2025-Q4', $entry->refresh()->period_key?->key());
        $this->assertSame('2025-Q4', (new \App\Http\Resources\DiaryEntryResource($entry))->toArray(request())['period_key']);
    }

    public function test_entry_type_admin_form_stores_period_kinds_and_review(): void {
        $this->actingAs($this->admin)->post(route('admin.entry-types.store'), [
            'slug' => 'quartal',
            'label' => 'Quartalsauftrag',
            'icon' => 'event',
            'color' => 'primary',
            'is_active' => '1',
            'default_status' => 2,
            'requires_period' => ['quarter', 'month'],
            'requires_review' => '1',
        ])->assertSessionHasNoErrors();

        $type = EntryType::query()->where('slug', 'quartal')->firstOrFail();
        $this->assertSame([PeriodKind::Quarter, PeriodKind::Month], $type->requiredPeriodKinds());
        $this->assertTrue($type->requires_review);
        $this->assertSame('quarter,month', $type->flagsArray()['requires_period']);
    }
}
