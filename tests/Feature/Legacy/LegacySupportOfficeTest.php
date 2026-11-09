<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportOfficeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Models\{LegacySupportCustomer, LegacySupportOrder};
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/** Bürosicht des Support-Portals im Legacy-Bereich (Altportal: Bürokonten). */
class LegacySupportOfficeTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    private User $office;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
        Config::set('app.display_timezone', 'Europe/Berlin');
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'Europe/Berlin'));

        $legacy = DB::connection('legacy');
        $legacy->table('user')->insert([
            ['id' => 2, 'uname' => 'buero', 'userpw' => 'x', 'email' => null],
            ['id' => 5, 'uname' => 'Monteur Eins', 'userpw' => 'x', 'email' => null],
        ]);
        $legacy->table('kp_user')->insert([
            ['id' => 2, 'kunde' => 'self', 'usern' => 'buero', 'passw' => 'geheim'],
            ['id' => 10, 'kunde' => 'Beispiel Haustechnik', 'usern' => 'beispiel', 'passw' => 'kalt1'],
            ['id' => 11, 'kunde' => 'Testkunde Nord', 'usern' => 'nord', 'passw' => 'nord1'],
        ]);

        $this->office = User::factory()->create(['legacy_user_id' => 2, 'email' => 'buero@example.test']);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function order(array $overrides = []): int {
        return (int) DB::connection('legacy')->table('kp_auftrag')->insertGetId([
            'kunde' => 10,
            'datum' => '2026-11-02 08:15:00',
            'kname' => 'Frau Beispiel',
            'telefon' => '0123 4567',
            'objekt' => 'Kühlraum Halle 2',
            'auftrag' => 'Verdampfer vereist',
            'termin' => LegacySupportOrder::NO_APPOINTMENT,
            'wer' => '',
            'antwort' => '',
            'intern' => '',
            'dauer' => '',
            'status' => LegacySupportOrder::STATUS_NEW,
            ...$overrides,
        ]);
    }

    public function test_index_lists_latest_year_with_status_tiles_and_overdue_filter(): void {
        $this->order(['objekt' => 'Aktueller Auftrag']);
        $this->order(['objekt' => 'Terminauftrag', 'status' => LegacySupportOrder::STATUS_SCHEDULED, 'termin' => '2026-11-08 09:00:00']);
        $this->order(['objekt' => 'Vorjahresauftrag', 'datum' => '2025-05-01 10:00:00', 'status' => LegacySupportOrder::STATUS_DONE]);

        $this->actingAs($this->office)->get(route('legacy.support.orders.index'))
            ->assertOk()
            ->assertSee('Aktueller Auftrag')
            ->assertSee('Terminauftrag')
            ->assertDontSee('Vorjahresauftrag')
            ->assertSee('Beispiel Haustechnik');

        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee('Terminauftrag')
            ->assertDontSee('Aktueller Auftrag');

        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['year' => 2025]))
            ->assertOk()
            ->assertSee('Vorjahresauftrag')
            ->assertDontSee('Aktueller Auftrag');
    }

    /** Der Termin ist Ortszeit: 09:30 ist um 10:00 Berliner Zeit verstrichen, obwohl es erst 09:00 UTC ist. */
    public function test_overdue_compares_appointments_in_local_time(): void {
        $passed = $this->order(['status' => LegacySupportOrder::STATUS_SCHEDULED, 'termin' => '2026-11-09 09:30:00']);
        $ahead = $this->order(['status' => LegacySupportOrder::STATUS_SCHEDULED, 'termin' => '2026-11-09 10:30:00']);
        $this->order(['status' => LegacySupportOrder::STATUS_SEEN, 'termin' => '0000-00-00 00:00:00']);

        $this->assertTrue(LegacySupportOrder::query()->findOrFail($passed)->isOverdue());
        $this->assertFalse(LegacySupportOrder::query()->findOrFail($ahead)->isOverdue());
        $this->assertSame([$passed], LegacySupportOrder::query()->overdue()->pluck('id')->map(fn($id) => (int) $id)->all());
    }

    public function test_customer_sort_follows_the_company_name_and_search_narrows_the_list(): void {
        $this->order(['kunde' => 10, 'objekt' => 'Erster Kunde']);
        $this->order(['kunde' => 11, 'objekt' => 'Zweiter Kunde']);

        // Kundennummer 11 („Testkunde Nord“) steht absteigend nach Namen vor 10 („Beispiel Haustechnik“).
        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['sort' => 'kunde', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zweiter Kunde', 'Erster Kunde']);
        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['sort' => 'kunde', 'dir' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Erster Kunde', 'Zweiter Kunde']);

        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['q' => 'Zweiter']))
            ->assertOk()
            ->assertSee('Zweiter Kunde')
            ->assertDontSee('Erster Kunde');
    }

    public function test_mechanic_without_office_account_is_refused(): void {
        $mechanic = User::factory()->create(['legacy_user_id' => 5]);

        $this->actingAs($mechanic)->get(route('legacy.support.orders.index'))->assertForbidden();
        $this->actingAs($mechanic)->get(route('legacy.support.customers.index'))->assertForbidden();
    }

    public function test_office_creates_order_with_office_marker(): void {
        $this->actingAs($this->office)
            ->post(route('legacy.support.orders.store'), [
                'kunde' => Sqid::encode(LegacySupportCustomer::class, 11),
                'telefon' => '0987',
                'objekt' => 'Klimagerät Büro',
                'auftrag' => 'Wartung',
            ])
            ->assertRedirect(route('legacy.support.orders.index'));

        $row = DB::connection('legacy')->table('kp_auftrag')->sole();
        $this->assertSame(11, (int) $row->kunde);
        $this->assertSame('bueradmin', $row->kname);
        $this->assertSame(LegacySupportOrder::STATUS_NEW, (int) $row->status);
        $this->assertSame(LegacySupportOrder::NO_APPOINTMENT, $row->termin);
        $this->assertSame('2026-11-09 10:00:00', $row->datum);
    }

    public function test_office_order_rejects_office_account_as_customer(): void {
        $this->actingAs($this->office)
            ->from(route('legacy.support.orders.index'))
            ->post(route('legacy.support.orders.store'), ['kunde' => 2, 'auftrag' => 'x'])
            ->assertSessionHasErrors('kunde');
    }

    public function test_new_appointment_sets_scheduled_and_keeps_office_orderer(): void {
        $id = $this->order(['kname' => 'chefadmin']);

        $this->actingAs($this->office)
            ->put(route('legacy.support.orders.update', $id), [
                'kunde' => Sqid::encode(LegacySupportCustomer::class, 11),
                'kname' => 'überschrieben',
                'telefon' => '0123 4567',
                'objekt' => 'Kühlraum Halle 2',
                'auftrag' => 'Verdampfer vereist',
                'termin' => '2026-11-12T08:00',
                'wer' => 'Monteur Eins',
                'antwort' => 'Wir kommen am Donnerstag.',
                'intern' => 'Ersatzteil mitnehmen',
                'dauer' => '2 h',
                'status' => LegacySupportOrder::STATUS_SEEN,
            ])
            ->assertRedirect(route('legacy.support.orders.index'));

        $row = DB::connection('legacy')->table('kp_auftrag')->where('id', $id)->sole();
        $this->assertSame(LegacySupportOrder::STATUS_SCHEDULED, (int) $row->status);
        $this->assertSame('2026-11-12 08:00:00', $row->termin);
        $this->assertSame('chefadmin', $row->kname);
        $this->assertSame(11, (int) $row->kunde);
        $this->assertSame('Monteur Eins', $row->wer);
        $this->assertSame('Ersatzteil mitnehmen', $row->intern);
    }

    public function test_done_status_wins_over_new_appointment_and_cleared_appointment_writes_placeholder(): void {
        $id = $this->order(['status' => LegacySupportOrder::STATUS_SCHEDULED, 'termin' => '2026-11-12 08:00:00']);
        $payload = [
            'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
            'kname' => 'Frau Beispiel',
            'auftrag' => 'Verdampfer vereist',
            'status' => LegacySupportOrder::STATUS_DONE,
        ];

        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [...$payload, 'termin' => '2026-11-13T09:00']);
        $this->assertSame(LegacySupportOrder::STATUS_DONE, (int) DB::connection('legacy')->table('kp_auftrag')->where('id', $id)->value('status'));

        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [...$payload, 'termin' => null]);
        $this->assertSame(LegacySupportOrder::NO_APPOINTMENT, DB::connection('legacy')->table('kp_auftrag')->where('id', $id)->value('termin'));
    }

    public function test_unknown_mechanic_is_rejected(): void {
        $id = $this->order();

        $this->actingAs($this->office)
            ->from(route('legacy.support.orders.index'))
            ->put(route('legacy.support.orders.update', $id), [
                'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
                'auftrag' => 'x',
                'wer' => 'Fremder',
                'status' => LegacySupportOrder::STATUS_SEEN,
            ])
            ->assertSessionHasErrors('wer');
    }

    public function test_office_deletes_order(): void {
        $id = $this->order();

        $this->actingAs($this->office)->delete(route('legacy.support.orders.destroy', $id))
            ->assertRedirect(route('legacy.support.orders.index'));

        $this->assertSame(0, DB::connection('legacy')->table('kp_auftrag')->count());
    }

    public function test_writes_are_blocked_while_legacy_is_read_only(): void {
        Config::set('app.legacy_write_enabled', false);

        $this->actingAs($this->office)
            ->from(route('legacy.support.orders.index'))
            ->post(route('legacy.support.orders.store'), ['kunde' => 10, 'auftrag' => 'x'])
            ->assertRedirect(route('legacy.support.orders.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, DB::connection('legacy')->table('kp_auftrag')->count());
    }

    public function test_customer_accounts_are_managed_without_office_accounts(): void {
        $this->actingAs($this->office)->get(route('legacy.support.customers.index'))
            ->assertOk()
            ->assertSee('Beispiel Haustechnik')
            ->assertDontSee('geheim');

        $this->actingAs($this->office)
            ->post(route('legacy.support.customers.store'), [
                'kunde' => 'Neukunde Süd',
                'usern' => 'sued',
                'passw' => 'sued1',
                'passw_confirmation' => 'sued1',
            ])
            ->assertRedirect(route('legacy.support.customers.index'));
        $this->assertSame('sued1', DB::connection('legacy')->table('kp_user')->where('usern', 'sued')->value('passw'));

        $this->actingAs($this->office)
            ->from(route('legacy.support.customers.index'))
            ->post(route('legacy.support.customers.store'), ['kunde' => 'self', 'usern' => 'nord', 'passw' => 'ab', 'passw_confirmation' => 'xy'])
            ->assertSessionHasErrors(['kunde', 'usern', 'passw']);

        $this->actingAs($this->office)->get(route('legacy.support.customers.edit', 2))->assertNotFound();
    }

    public function test_customer_update_keeps_password_when_left_empty(): void {
        $this->actingAs($this->office)
            ->put(route('legacy.support.customers.update', 11), ['kunde' => 'Testkunde Nord GmbH', 'usern' => 'nord', 'passw' => null])
            ->assertRedirect(route('legacy.support.customers.index'));

        $row = DB::connection('legacy')->table('kp_user')->where('id', 11)->sole();
        $this->assertSame('Testkunde Nord GmbH', $row->kunde);
        $this->assertSame('nord1', $row->passw);
    }

    public function test_customer_with_open_orders_cannot_be_deleted(): void {
        $this->order(['kunde' => 10]);

        $this->actingAs($this->office)
            ->from(route('legacy.support.customers.index'))
            ->delete(route('legacy.support.customers.destroy', 10))
            ->assertSessionHas('error');
        $this->assertTrue(DB::connection('legacy')->table('kp_user')->where('id', 10)->exists());

        $this->actingAs($this->office)->delete(route('legacy.support.customers.destroy', 11))
            ->assertRedirect(route('legacy.support.customers.index'));
        $this->assertFalse(DB::connection('legacy')->table('kp_user')->where('id', 11)->exists());
    }

    public function test_archiving_moves_archived_orders_before_cutoff_and_keeps_number(): void {
        $old = $this->order(['status' => LegacySupportOrder::STATUS_ARCHIVED, 'datum' => '2026-10-20 10:00:00', 'objekt' => 'Altauftrag']);
        $recent = $this->order(['status' => LegacySupportOrder::STATUS_ARCHIVED, 'datum' => '2026-11-03 10:00:00']);
        $open = $this->order(['status' => LegacySupportOrder::STATUS_DONE, 'datum' => '2026-10-01 10:00:00']);

        $this->actingAs($this->office)->post(route('legacy.support.archive.run'), ['before' => '2026-11-01'])
            ->assertRedirect(route('legacy.support.archive.index'));

        $legacy = DB::connection('legacy');
        $this->assertSame([$recent, $open], $legacy->table('kp_auftrag')->orderBy('id')->pluck('id')->map(fn($id) => (int) $id)->all());
        $this->assertSame([$old], $legacy->table('kp_archiv')->pluck('id')->map(fn($id) => (int) $id)->all());

        $this->actingAs($this->office)->get(route('legacy.support.archive.index', ['year' => 'all']))
            ->assertOk()
            ->assertSee('Altauftrag');
        $this->actingAs($this->office)->get(route('legacy.support.archive.show', $old))
            ->assertOk()
            ->assertSee('Altauftrag');
    }

    public function test_mail_switch_keeps_the_legacy_values(): void {
        $this->actingAs($this->office)
            ->put(route('legacy.support.mail.update'), ['enabled' => '1', 'email' => 'buero@example.test'])
            ->assertRedirect(route('legacy.support.orders.index'));
        $this->assertSame(1, (int) DB::connection('legacy')->table('email')->where('id', 1)->value('schalt'));

        $this->actingAs($this->office)
            ->put(route('legacy.support.mail.update'), ['enabled' => '0', 'email' => 'buero@example.test']);
        $this->assertSame(3, (int) DB::connection('legacy')->table('email')->where('id', 1)->value('schalt'));
        $this->assertSame(1, DB::connection('legacy')->table('email')->count());
    }

    public function test_dialogs_render(): void {
        $id = $this->order(['intern' => 'Nur fürs Büro']);

        $this->actingAs($this->office)->get(route('legacy.support.orders.show', $id))->assertOk()->assertSee('Nur fürs Büro');
        $this->actingAs($this->office)->get(route('legacy.support.orders.edit', $id))->assertOk()->assertSee('Monteur Eins');
        $this->actingAs($this->office)->get(route('legacy.support.orders.create'))->assertOk()->assertSee('bueradmin');
        $this->actingAs($this->office)->get(route('legacy.support.customers.create'))->assertOk();
        $this->actingAs($this->office)->get(route('legacy.support.customers.edit', 10))->assertOk()->assertSee('Beispiel Haustechnik');
        $this->actingAs($this->office)->get(route('legacy.support.mail.edit'))->assertOk();
    }
}
