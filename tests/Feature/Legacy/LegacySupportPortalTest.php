<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportPortalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Http\Middleware\EnsureLegacySupportCustomer;
use App\Legacy\Models\{LegacySupportMailSetting, LegacySupportOrder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/** Kundenseite des Support-Portals: eigene Anmeldung gegen kp_user, nur eigene Aufträge. */
class LegacySupportPortalTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
        Config::set('app.display_timezone', 'Europe/Berlin');
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'Europe/Berlin'));

        DB::connection('legacy')->table('kp_user')->insert([
            ['id' => 1, 'kunde' => 'self', 'usern' => 'chef', 'passw' => 'chef1'],
            ['id' => 10, 'kunde' => 'Beispiel Haustechnik', 'usern' => 'beispiel', 'passw' => 'Kalt1'],
            ['id' => 11, 'kunde' => 'Testkunde Nord', 'usern' => 'nord', 'passw' => 'nord1'],
        ]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function order(int $customer, array $overrides = []): int {
        return (int) DB::connection('legacy')->table('kp_auftrag')->insertGetId([
            'kunde' => $customer,
            'datum' => '2026-11-02 08:15:00',
            'kname' => 'Frau Beispiel',
            'telefon' => '0123',
            'objekt' => 'Objekt ' . $customer,
            'auftrag' => 'Auftragstext ' . $customer,
            'termin' => LegacySupportOrder::NO_APPOINTMENT,
            'wer' => '',
            'antwort' => '',
            'intern' => 'Interne Notiz ' . $customer,
            'dauer' => '',
            'status' => LegacySupportOrder::STATUS_NEW,
            ...$overrides,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage> */
    private function sentMails(): \Illuminate\Support\Collection {
        $transport = app('mailer')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages();
    }

    private function loginAs(string $username, string $password): \Illuminate\Testing\TestResponse {
        return $this->from(route('legacy.portal.login'))->post(route('legacy.portal.login.submit'), [
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function test_customer_sees_only_own_orders(): void {
        $this->order(10);
        $this->order(11);

        $this->loginAs('beispiel', 'Kalt1')->assertRedirect(route('legacy.portal.orders.index'));
        $this->assertSame(10, session(EnsureLegacySupportCustomer::SESSION_KEY));

        $this->get(route('legacy.portal.orders.index'))
            ->assertOk()
            ->assertSee('Beispiel Haustechnik')
            ->assertSee('Objekt 10')
            ->assertDontSee('Objekt 11');
    }

    public function test_foreign_order_number_stays_invisible_and_internal_note_is_hidden(): void {
        $own = $this->order(10, ['antwort' => 'Termin folgt']);
        $foreign = $this->order(11);

        $this->loginAs('beispiel', 'Kalt1');

        $this->get(route('legacy.portal.orders.show', $own))
            ->assertOk()
            ->assertSee('Termin folgt')
            ->assertDontSee('Interne Notiz 10');
        $this->get(route('legacy.portal.orders.show', $foreign))->assertNotFound();
    }

    public function test_office_accounts_and_case_variants_cannot_sign_in(): void {
        $this->loginAs('chef', 'chef1')->assertSessionHasErrors('username');
        $this->loginAs('beispiel', 'kalt1')->assertSessionHasErrors('username');
        $this->assertNull(session(EnsureLegacySupportCustomer::SESSION_KEY));
    }

    public function test_pages_require_a_customer_session(): void {
        $id = $this->order(10);

        $this->get(route('legacy.portal.orders.index'))->assertRedirect(route('legacy.portal.login'));
        $this->get(route('legacy.portal.orders.show', $id))->assertRedirect(route('legacy.portal.login'));
        $this->get(route('legacy.portal.login'))->assertOk()->assertSee(__('legacy_support.portal.title'));
    }

    public function test_login_is_throttled_per_username(): void {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->loginAs('beispiel', 'falsch');
        }

        $this->loginAs('beispiel', 'Kalt1')->assertSessionHasErrors('username');
        $this->assertNull(session(EnsureLegacySupportCustomer::SESSION_KEY));
    }

    public function test_new_order_is_stored_for_the_session_customer_and_notifies_the_office(): void {
        DB::connection('legacy')->table('email')->insert(['id' => 1, 'email' => 'buero@example.test', 'schalt' => LegacySupportMailSetting::SWITCH_ON]);
        $this->loginAs('beispiel', 'Kalt1');

        $this->post(route('legacy.portal.orders.store'), [
            'kunde' => 11,
            'kname' => 'Herr Beispiel',
            'telefon' => '0123 99',
            'objekt' => 'Kühlzelle',
            'auftrag' => "Tür schließt nicht\nBitte zeitnah",
        ])->assertRedirect(route('legacy.portal.orders.index'));

        $row = DB::connection('legacy')->table('kp_auftrag')->sole();
        $this->assertSame(10, (int) $row->kunde);
        $this->assertSame(LegacySupportOrder::STATUS_NEW, (int) $row->status);
        $this->assertSame(LegacySupportOrder::NO_APPOINTMENT, $row->termin);
        $this->assertSame('2026-11-09 10:00:00', $row->datum);

        $messages = $this->sentMails();
        $this->assertCount(1, $messages);
        $text = $messages->first()->toString();
        $this->assertStringContainsString('buero@example.test', $text);
        $this->assertStringContainsString('Herr Beispiel', $text);
    }

    public function test_switched_off_notice_sends_no_mail(): void {
        DB::connection('legacy')->table('email')->insert(['id' => 1, 'email' => 'buero@example.test', 'schalt' => LegacySupportMailSetting::SWITCH_OFF]);
        $this->loginAs('beispiel', 'Kalt1');

        $this->post(route('legacy.portal.orders.store'), ['kname' => 'Herr Beispiel', 'auftrag' => 'Wartung']);

        $this->assertCount(0, $this->sentMails());
        $this->assertSame(1, DB::connection('legacy')->table('kp_auftrag')->count());
    }

    public function test_read_only_legacy_blocks_new_orders(): void {
        Config::set('app.legacy_write_enabled', false);
        $this->loginAs('beispiel', 'Kalt1');

        $this->get(route('legacy.portal.orders.create'))->assertRedirect(route('legacy.portal.orders.index'));
        $this->from(route('legacy.portal.orders.index'))
            ->post(route('legacy.portal.orders.store'), ['kname' => 'Herr Beispiel', 'auftrag' => 'Wartung'])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::connection('legacy')->table('kp_auftrag')->count());
    }

    public function test_password_change_requires_current_password_and_ends_other_sessions(): void {
        $this->loginAs('beispiel', 'Kalt1');

        $this->from(route('legacy.portal.password.edit'))
            ->put(route('legacy.portal.password.update'), ['current_password' => 'falsch', 'password' => 'Neu12', 'password_confirmation' => 'Neu12'])
            ->assertSessionHasErrors('current_password');

        $this->put(route('legacy.portal.password.update'), ['current_password' => 'Kalt1', 'password' => 'Neu12', 'password_confirmation' => 'Neu12'])
            ->assertRedirect(route('legacy.portal.orders.index'));
        $this->assertSame('Neu12', DB::connection('legacy')->table('kp_user')->where('id', 10)->value('passw'));
        $this->get(route('legacy.portal.orders.index'))->assertOk();

        // Eine andere Sitzung mit dem alten Fingerabdruck fliegt raus.
        $this->withSession([
            EnsureLegacySupportCustomer::SESSION_KEY => 10,
            EnsureLegacySupportCustomer::SESSION_FINGERPRINT => 'veraltet',
        ])->get(route('legacy.portal.orders.index'))->assertRedirect(route('legacy.portal.login'));
    }

    public function test_customer_pages_render(): void {
        $this->loginAs('beispiel', 'Kalt1');

        $this->get(route('legacy.portal.orders.create'))->assertOk()->assertSee(__('legacy_support.portal.submit_order'));
        $this->get(route('legacy.portal.password.edit'))->assertOk()->assertSee(__('legacy_support.field.current_password'));
        $this->get(route('legacy.portal.home'))->assertRedirect(route('legacy.portal.orders.index'));
        $this->get(route('legacy.portal.login'))->assertRedirect(route('legacy.portal.orders.index'));
    }

    public function test_logout_ends_the_customer_session(): void {
        $this->loginAs('beispiel', 'Kalt1');

        $this->post(route('legacy.portal.logout'))->assertRedirect(route('legacy.portal.login'));
        $this->get(route('legacy.portal.orders.index'))->assertRedirect(route('legacy.portal.login'));
    }
}
