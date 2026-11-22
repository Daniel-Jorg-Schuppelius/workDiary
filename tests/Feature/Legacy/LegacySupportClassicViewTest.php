<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportClassicViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Http\Middleware\EnsureLegacySupportCustomer;
use App\Legacy\Models\{LegacySupportCustomer, LegacySupportOrder};
use App\Legacy\Support\LegacyView;
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/** Klassische Darstellung des Support-Portals im Aussehen des Altportals: gleiche Routen und Prüfungen. */
class LegacySupportClassicViewTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    private User $office;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
        Config::set('app.legacy_view', LegacyView::CLASSIC);
        Config::set('app.display_timezone', 'Europe/Berlin');
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'Europe/Berlin'));

        $legacy = DB::connection('legacy');
        $legacy->table('user')->insert([
            ['id' => 2, 'uname' => 'buero', 'userpw' => 'x', 'email' => null],
            ['id' => 5, 'uname' => 'Monteur Eins', 'userpw' => 'x', 'email' => null],
        ]);
        $legacy->table('kp_user')->insert([
            ['id' => 10, 'kunde' => 'Beispiel Haustechnik', 'usern' => 'beispiel', 'passw' => 'Kalt1'],
            ['id' => 11, 'kunde' => 'Testkunde Nord', 'usern' => 'nord', 'passw' => 'nord1'],
        ]);

        $this->office = User::factory()->create(['legacy_user_id' => 2]);
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
            'wer' => 'Monteur Eins',
            'antwort' => 'Wir kommen.',
            'intern' => 'Leiter mitnehmen',
            'dauer' => '1 h',
            'status' => LegacySupportOrder::STATUS_SEEN,
            ...$overrides,
        ]);
    }

    private function row(int $id): object {
        return DB::connection('legacy')->table('kp_auftrag')->where('id', $id)->sole();
    }

    public function test_configuration_and_cookie_choose_the_view(): void {
        $this->order();

        $this->actingAs($this->office)->get(route('legacy.support.orders.index'))
            ->assertOk()
            ->assertViewIs('legacy.classic.support.orders.index')
            ->assertSee(__('legacy_support.classic.legend.overdue'))
            ->assertSee(__('legacy_support.classic.choose_year'))
            ->assertSee('Kühlraum Halle 2');

        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.support.orders.index'))
            ->assertOk()
            ->assertViewIs('legacy.support.orders.index');

        Config::set('app.legacy_view', LegacyView::MODERN);
        $this->actingAs($this->office)->get(route('legacy.support.orders.index'))->assertViewIs('legacy.support.orders.index');
    }

    public function test_switch_remembers_the_choice_and_only_returns_into_the_support_portal(): void {
        $this->get(route('legacy.view', ['mode' => 'modern', 'back' => '/legacy/support/orders?year=2025&sort=nr']))
            ->assertRedirect(url('/legacy/support/orders?year=2025&sort=nr'))
            ->assertCookie(LegacyView::COOKIE, LegacyView::MODERN);

        $this->get(route('legacy.view', ['mode' => 'classic', 'back' => 'https://example.org/legacy/support/orders']))
            ->assertRedirect(route('legacy.portal.login'));
        $this->get(route('legacy.view', ['mode' => 'classic', 'back' => '//example.org/legacy/support']))
            ->assertRedirect(route('legacy.portal.login'));

        $this->get('/legacy/view/retro')->assertNotFound();
    }

    public function test_entry_form_takes_the_appointment_in_four_fields(): void {
        $id = $this->order();

        $this->actingAs($this->office)->get(route('legacy.support.orders.edit', $id))
            ->assertOk()
            ->assertViewIs('legacy.classic.support.orders.form')
            ->assertSee('name="tj"', false)
            ->assertSee(__('legacy_support.classic.status_option.read'));

        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [
            'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
            'kname' => 'Frau Beispiel',
            'auftrag' => 'Verdampfer vereist',
            'tj' => '2026', 'tm' => '11', 'tt' => '12', 'th' => '8',
            'wer' => 'Monteur Eins',
            'status' => LegacySupportOrder::STATUS_SEEN,
        ])->assertRedirect();

        $row = $this->row($id);
        $this->assertSame('2026-11-12 08:00:00', $row->termin);
        $this->assertSame(LegacySupportOrder::STATUS_SCHEDULED, (int) $row->status);

        $this->actingAs($this->office)
            ->from(route('legacy.support.orders.edit', $id))
            ->put(route('legacy.support.orders.update', $id), [
                'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
                'auftrag' => 'x',
                'tj' => '2026', 'tm' => '', 'tt' => '12', 'th' => '8',
                'status' => LegacySupportOrder::STATUS_SEEN,
            ])
            ->assertSessionHasErrors('termin');
    }

    public function test_appointment_and_customer_masks_keep_the_other_fields(): void {
        $id = $this->order(['termin' => '2026-11-10 09:00:00', 'status' => LegacySupportOrder::STATUS_SCHEDULED]);

        $page = $this->actingAs($this->office)->get(route('legacy.support.orders.edit', [$id, 'form' => 'appointment']))
            ->assertOk()
            ->assertSee(__('legacy_support.classic.delete_entry'));
        $page->assertSee('value="Leiter mitnehmen"', false);

        // Teilmaske „Termin": nur der Termin ändert sich.
        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [
            'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
            'kname' => 'Frau Beispiel', 'telefon' => '0123 4567', 'objekt' => 'Kühlraum Halle 2',
            'auftrag' => 'Verdampfer vereist', 'wer' => 'Monteur Eins', 'antwort' => 'Wir kommen.',
            'intern' => 'Leiter mitnehmen', 'dauer' => '1 h', 'status' => (string) LegacySupportOrder::STATUS_SCHEDULED,
            'tj' => '2026', 'tm' => '11', 'tt' => '13', 'th' => '14',
        ])->assertRedirect();
        $row = $this->row($id);
        $this->assertSame('2026-11-13 14:00:00', $row->termin);
        $this->assertSame('Leiter mitnehmen', $row->intern);

        // Teilmaske „Kundenauswahl": der Termin kommt versteckt mit und bleibt.
        $this->actingAs($this->office)->get(route('legacy.support.orders.edit', [$id, 'form' => 'customer']))
            ->assertOk()
            ->assertSee('name="tj" value="2026"', false);
        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [
            'kunde' => Sqid::encode(LegacySupportCustomer::class, 11),
            'kname' => 'Frau Beispiel', 'telefon' => '0123 4567', 'objekt' => 'Kühlraum Halle 2',
            'auftrag' => 'Verdampfer vereist', 'wer' => 'Monteur Eins', 'antwort' => 'Wir kommen.',
            'intern' => 'Leiter mitnehmen', 'dauer' => '1 h', 'status' => (string) LegacySupportOrder::STATUS_SCHEDULED,
            'tj' => '2026', 'tm' => '11', 'tt' => '13', 'th' => '14',
        ])->assertRedirect();
        $row = $this->row($id);
        $this->assertSame(11, (int) $row->kunde);
        $this->assertSame('2026-11-13 14:00:00', $row->termin);
    }

    public function test_saving_returns_to_the_list_with_its_year(): void {
        $id = $this->order(['datum' => '2025-05-02 08:00:00']);
        $this->order();

        $this->actingAs($this->office)->get(route('legacy.support.orders.index', ['year' => 2025]))
            ->assertOk()
            ->assertSee('value="2025" selected', false);

        $this->actingAs($this->office)->put(route('legacy.support.orders.update', $id), [
            'kunde' => Sqid::encode(LegacySupportCustomer::class, 10),
            'auftrag' => 'Verdampfer vereist',
            'tj' => '', 'tm' => '', 'tt' => '', 'th' => '',
            'status' => LegacySupportOrder::STATUS_DONE,
        ])->assertRedirect(route('legacy.support.orders.index', ['year' => 2025]));
    }

    public function test_office_pages_of_the_old_menu_render(): void {
        $id = $this->order(['intern' => 'Nur fürs Büro']);
        DB::connection('legacy')->table('kp_archiv')->insert([
            'id' => 900, 'kunde' => 10, 'datum' => '2015-03-02 10:00:00', 'kname' => 'Alt', 'telefon' => '', 'objekt' => 'Altauftrag',
            'auftrag' => 'x', 'termin' => LegacySupportOrder::NO_APPOINTMENT, 'wer' => '', 'antwort' => '', 'intern' => '', 'dauer' => '',
            'status' => LegacySupportOrder::STATUS_ARCHIVED,
        ]);

        $this->actingAs($this->office)->get(route('legacy.support.orders.show', $id))->assertOk()->assertViewIs('legacy.classic.support.orders.show')->assertSee('Nur fürs Büro');
        $this->actingAs($this->office)->get(route('legacy.support.orders.create'))->assertOk()->assertSee(__('legacy_support.classic.new_entry'))->assertSee('bueradmin');
        $this->actingAs($this->office)->get(route('legacy.support.customers.index'))->assertOk()->assertSee(__('legacy_support.classic.customers.new_user'))->assertDontSee('Kalt1');
        $this->actingAs($this->office)->get(route('legacy.support.customers.create'))->assertOk()->assertSee(__('legacy_support.classic.customers.create_question'));
        $this->actingAs($this->office)->get(route('legacy.support.customers.show', [11, 'delete' => 1]))->assertOk()->assertSee(__('legacy_support.classic.customers.delete_question'));
        $this->actingAs($this->office)->get(route('legacy.support.archive.index', ['year' => 2015]))->assertOk()->assertSee('Altauftrag');
        $this->actingAs($this->office)->get(route('legacy.support.archive.show', 900))->assertOk()->assertViewIs('legacy.classic.support.orders.show');
        $this->actingAs($this->office)->get(route('legacy.support.archive.prepare'))->assertOk()->assertSee(__('legacy_support.classic.archiving.run'));
    }

    public function test_classic_only_pages_lead_back_to_the_list_in_the_new_view(): void {
        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.support.customers.show', 11))
            ->assertRedirect(route('legacy.support.customers.index'));
        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.support.archive.prepare'))
            ->assertRedirect(route('legacy.support.archive.index'));
    }

    public function test_mail_dialog_of_the_new_view_leads_back_to_the_list_in_the_classic_view(): void {
        $this->actingAs($this->office)->get(route('legacy.support.mail.edit'))
            ->assertRedirect(route('legacy.support.orders.index'));
        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.support.mail.edit'))
            ->assertOk();
    }

    public function test_mechanic_still_needs_the_office_rule(): void {
        $mechanic = User::factory()->create(['legacy_user_id' => 5]);

        $this->actingAs($mechanic)->get(route('legacy.support.orders.index'))->assertForbidden();
        $this->actingAs($mechanic)->get(route('legacy.support.customers.show', 11))->assertForbidden();
        $this->actingAs($mechanic)->get(route('legacy.support.archive.prepare'))->assertForbidden();
    }

    public function test_customer_pages_look_like_the_old_portal(): void {
        Config::set('app.legacy_support_title', 'Kundenportal der Musterfirma');
        $id = $this->order(['antwort' => '']);

        $this->get(route('legacy.portal.login'))
            ->assertOk()
            ->assertViewIs('legacy.classic.support.portal.login')
            ->assertSee('Kundenportal der Musterfirma')
            ->assertSee(__('legacy_support.classic.label.user'));

        $this->post(route('legacy.portal.login.submit'), ['username' => 'beispiel', 'password' => 'Kalt1'])
            ->assertRedirect(route('legacy.portal.orders.index'));
        $this->assertSame(10, session(EnsureLegacySupportCustomer::SESSION_KEY));

        $this->get(route('legacy.portal.orders.index'))
            ->assertOk()
            ->assertViewIs('legacy.classic.support.portal.index')
            ->assertSee(__('legacy_support.classic.logged_in_as', ['name' => 'Beispiel Haustechnik']))
            ->assertSee(__('legacy_support.classic.menu.new_order'));
        $this->get(route('legacy.portal.orders.show', $id))
            ->assertOk()
            ->assertSee(__('legacy_support.classic.detail_view'))
            ->assertSee(__('legacy_support.classic.none'))
            ->assertDontSee('Leiter mitnehmen');
        $this->get(route('legacy.portal.orders.create'))->assertOk()->assertSee(__('legacy_support.classic.new_entry'));
        $this->get(route('legacy.portal.password.edit'))->assertOk()->assertSee(__('legacy_support.classic.password_page.submit'));
    }
}
