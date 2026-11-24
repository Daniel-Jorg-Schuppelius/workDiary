<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDiaryClassicViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Models\LegacyUser;
use App\Legacy\Support\LegacyView;
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/** Klassische Ansicht von Online-Tagebuch und Notdienstplan: Aussehen des Altportals über dieselben Wege. */
class LegacyDiaryClassicViewTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    private User $office;

    private User $mechanic;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);
        Config::set('app.legacy_view', LegacyView::CLASSIC);
        Config::set('app.display_timezone', 'Europe/Berlin');
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Europe/Berlin'));

        DB::connection('legacy')->table('user')->insert([
            ['id' => 2, 'uname' => 'buero', 'userpw' => 'x', 'email' => null],
            ['id' => 5, 'uname' => 'Monteur Eins', 'userpw' => 'x', 'email' => 'eins@example.test'],
            ['id' => 6, 'uname' => 'Monteur Zwei', 'userpw' => 'x', 'email' => null],
        ]);

        $this->office = User::factory()->create(['legacy_user_id' => 2]);
        $this->mechanic = User::factory()->create(['legacy_user_id' => 5]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function entry(array $overrides = []): int {
        return (int) DB::connection('legacy')->table('tagebuch')->insertGetId([
            'user' => 5,
            'inhalt' => 'Kältemaschine prüfen',
            'antwort' => null,
            'von' => '2026-11-17 08:00:00',
            'bis' => '2026-11-17 10:00:00',
            'aktuell' => '2026-11-16 09:00:00',
            'gelesen' => 2,
            'sms' => '',
            ...$overrides,
        ]);
    }

    private function userKey(int $id): string {
        return (string) Sqid::encode(LegacyUser::class, $id);
    }

    /** @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage> */
    private function sentMails(): \Illuminate\Support\Collection {
        $transport = app('mailer')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages();
    }

    public function test_office_sees_the_week_by_day_and_employee(): void {
        $this->entry();

        $this->actingAs($this->office)->get(route('legacy.diary.week'))
            ->assertOk()
            ->assertViewIs('legacy.classic.diary.week')
            ->assertSee('kp-classic', false)
            ->assertSee('Monteur Eins')
            ->assertSee('Monteur Zwei')
            ->assertSee('Kältemasch')
            ->assertSee(route('legacy.diary.create', ['user' => $this->userKey(5), 'date' => '2026-11-17']))
            ->assertSee(__('legacy_classic.menu.users'));
    }

    public function test_employee_sees_only_the_own_week(): void {
        $this->entry();
        $this->entry(['user' => 6, 'inhalt' => 'Fremder Auftrag']);

        $this->actingAs($this->mechanic)->get(route('legacy.diary.week'))
            ->assertOk()
            ->assertSee(__('legacy_classic.work_orders'))
            ->assertSee('Kältemasch')
            ->assertDontSee('Fremder Auftrag')
            ->assertDontSee('href="' . route('legacy.users.index') . '"', false);
    }

    public function test_office_enters_with_date_and_time_fields_and_the_employee_is_notified(): void {
        $this->actingAs($this->office)->post(route('legacy.diary.store'), [
            'user' => $this->userKey(5),
            'von_date' => '2026-11-17',
            'von_time' => '08:00',
            'bis_date' => '2026-11-17',
            'bis_time' => '10:30',
            'inhalt' => 'Wartung Kühlraum',
            'sms' => 'j',
            'gelesen' => 2,
        ])->assertRedirect();

        $row = DB::connection('legacy')->table('tagebuch')->sole();
        $this->assertSame(5, (int) $row->user);
        $this->assertSame('2026-11-17 08:00:00', $row->von);
        $this->assertSame('2026-11-17 10:30:00', $row->bis);
        $this->assertSame(2, (int) $row->gelesen);
        $this->assertCount(1, $this->sentMails());
    }

    public function test_invalid_classic_dates_are_reported(): void {
        $this->actingAs($this->office)->from(route('legacy.diary.create'))->post(route('legacy.diary.store'), [
            'user' => $this->userKey(5),
            'von_date' => '2026-02-31',
            'von_time' => '08:00',
            'bis_date' => '2026-11-17',
            'bis_time' => '25:00',
            'inhalt' => 'Wartung',
            'gelesen' => 2,
        ])->assertSessionHasErrors(['von', 'bis']);

        $this->assertSame(0, DB::connection('legacy')->table('tagebuch')->count());
    }

    public function test_employee_answer_reports_a_problem_without_notifying_himself(): void {
        $id = $this->entry(['sms' => 'j']);

        $this->actingAs($this->mechanic)->get(route('legacy.diary.edit', $id))
            ->assertOk()
            ->assertSee(__('legacy_classic.label.answer'))
            ->assertDontSee('name="von_date"', false);

        $this->actingAs($this->mechanic)->put(route('legacy.diary.update', $id), [
            'inhalt' => 'Kältemaschine prüfen',
            'von' => '2026-11-17 08:00:00',
            'bis' => '2026-11-17 10:00:00',
            'gelesen' => 3,
            'sms' => 'j',
            'antwort' => 'Ersatzteil fehlt',
        ])->assertRedirect(route('legacy.diary.show', $id));

        $row = DB::connection('legacy')->table('tagebuch')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->gelesen);
        $this->assertSame('Ersatzteil fehlt', $row->antwort);
        $this->assertSame('j', $row->sms);
        $this->assertCount(0, $this->sentMails());
    }

    public function test_employee_marks_read_and_returns_to_the_diary(): void {
        $id = $this->entry();

        $this->actingAs($this->mechanic)->get(route('legacy.diary.show', $id))
            ->assertOk()
            ->assertSee(__('legacy_classic.order_diary'))
            ->assertSee(__('legacy_classic.action.read'));

        $this->actingAs($this->mechanic)->get(route('legacy.diary.week', ['week' => 1]));
        $this->actingAs($this->mechanic)->post(route('legacy.diary.bulk'), ['action' => 'status_progress', 'ids' => [$id]])
            ->assertRedirect(route('legacy.diary.week', ['week' => 1]));

        $this->assertSame(1, (int) DB::connection('legacy')->table('tagebuch')->where('id', $id)->value('gelesen'));
    }

    public function test_overview_filters_like_the_old_portal(): void {
        $this->entry(['inhalt' => 'Ab heute offen']);
        $this->entry(['inhalt' => 'Gestern offen', 'von' => '2026-11-15 08:00:00', 'bis' => '2026-11-15 10:00:00']);
        $this->entry(['inhalt' => 'Ab heute erledigt', 'gelesen' => -1]);

        $this->actingAs($this->office)->get(route('legacy.diary.index'))
            ->assertOk()
            ->assertViewIs('legacy.classic.diary.index')
            ->assertSee('name="zeitpunkt"', false);

        $this->actingAs($this->office)->get(route('legacy.diary.index', ['anzeigen' => 1, 'zeitpunkt' => 1, 'status' => 2]))
            ->assertOk()
            ->assertSee('Ab heute offen')
            ->assertDontSee('Gestern offen')
            ->assertDontSee('Ab heute erledigt');

        $this->actingAs($this->office)->get(route('legacy.diary.index', ['anzeigen' => 1, 'zeitpunkt' => 2, 'status' => 'all']))
            ->assertOk()
            ->assertSee('Gestern offen')
            ->assertDontSee('Ab heute offen');
    }

    public function test_vacation_tab_stays_in_the_new_view(): void {
        $this->actingAs($this->office)->get(route('legacy.diary.index', ['tab' => 'urlaub']))
            ->assertOk()
            ->assertViewIs('legacy.diary.index');
    }

    public function test_duties_are_managed_with_date_pickers(): void {
        DB::connection('legacy')->table('bereit')->insert(['id' => 7, 'user' => 6, 'von' => '2026-11-20', 'bis' => '2026-11-22']);

        $this->actingAs($this->office)->get(route('legacy.diary.index', ['tab' => 'bereitschaft']))
            ->assertOk()
            ->assertViewIs('legacy.classic.diary.duties')
            ->assertSee(__('legacy_classic.duty.oncall.title'))
            ->assertSee('Monteur Zwei');

        $this->actingAs($this->office)->post(route('legacy.oncall.store'), [
            'user' => $this->userKey(5),
            'von' => '2026-12-01',
            'bis' => '2026-12-03',
        ])->assertRedirect(route('legacy.diary.index', ['tab' => 'bereitschaft']));

        $this->assertDatabaseHas('bereit', ['user' => 5, 'von' => '2026-12-01 00:00:00', 'bis' => '2026-12-03 00:00:00'], 'legacy');

        $this->actingAs($this->office)->get(route('legacy.notdienst.create'))
            ->assertOk()
            ->assertSee('type="date" name="von"', false);
        $this->actingAs($this->office)->get(route('legacy.oncall.edit', [7, 'delete' => 1]))
            ->assertOk()
            ->assertSee('Monteur Zwei')
            ->assertSee(__('legacy_classic.yes'));
    }

    public function test_users_are_managed_like_the_old_portal(): void {
        $this->entry(['user' => 5]);

        $this->actingAs($this->office)->get(route('legacy.users.index'))
            ->assertOk()
            ->assertViewIs('legacy.classic.users.index')
            ->assertSeeInOrder(['Monteur Eins', 'Monteur Zwei']);

        $this->actingAs($this->office)->from(route('legacy.users.create'))->post(route('legacy.users.store'), [
            'uname' => 'Monteur Drei',
            'userpw' => 'geheim1',
            'userpw_confirmation' => 'anders',
        ])->assertSessionHasErrors('userpw');
        $this->actingAs($this->office)->post(route('legacy.users.store'), [
            'uname' => 'Monteur Drei',
            'userpw' => 'geheim1',
            'userpw_confirmation' => 'geheim1',
        ])->assertRedirect(route('legacy.users.index'));
        $this->assertDatabaseHas('user', ['uname' => 'Monteur Drei'], 'legacy');

        $this->actingAs($this->office)->get(route('legacy.users.edit', [5, 'delete' => 1]))
            ->assertOk()
            ->assertSee(__('legacy_classic.users.blocked.orders', ['name' => 'Monteur Eins']));
        $this->actingAs($this->office)->get(route('legacy.users.edit', [6, 'delete' => 1]))
            ->assertOk()
            ->assertSee(__('legacy_classic.users.confirm_delete', ['name' => 'Monteur Zwei']));
    }

    public function test_archiving_moves_everything_of_one_employee(): void {
        $this->entry(['user' => 5]);
        $this->entry(['user' => 5, 'von' => '2027-03-01 08:00:00', 'bis' => '2027-03-01 09:00:00']);
        $this->entry(['user' => 6]);

        $this->actingAs($this->office)->get(route('legacy.archive.prepare'))
            ->assertOk()
            ->assertSee(__('legacy_classic.archiving.all_of_employee'));

        $this->actingAs($this->office)->from(route('legacy.archive.prepare'))
            ->post(route('legacy.archive.run'), ['months' => 0])
            ->assertSessionHasErrors('user');

        $this->actingAs($this->office)->post(route('legacy.archive.run'), ['months' => 0, 'user' => $this->userKey(5)])
            ->assertRedirect(route('legacy.archive.prepare'));

        $legacy = DB::connection('legacy');
        $this->assertSame(0, $legacy->table('tagebuch')->where('user', 5)->count());
        $this->assertSame(2, $legacy->table('a_tagebuch')->where('user', 5)->count());
        $this->assertSame(1, $legacy->table('tagebuch')->where('user', 6)->count());
    }

    public function test_archive_week_and_entry_render(): void {
        $id = (int) DB::connection('legacy')->table('a_tagebuch')->insertGetId([
            'user' => 5, 'inhalt' => 'Alter Auftrag', 'von' => '2026-11-17 08:00:00', 'bis' => '2026-11-17 09:00:00', 'aktuell' => '2026-11-16 09:00:00', 'gelesen' => -1, 'sms' => '',
        ]);

        $this->actingAs($this->office)->get(route('legacy.archive.week'))
            ->assertOk()
            ->assertSee(__('legacy_classic.archive.heading'))
            ->assertSee('Alter Auft');
        $this->actingAs($this->office)->get(route('legacy.archive.show', $id))
            ->assertOk()
            ->assertSee('Alter Auftrag');
    }

    public function test_password_page_and_new_view_only_pages(): void {
        $this->actingAs($this->mechanic)->get(route('legacy.account.password.edit'))
            ->assertOk()
            ->assertSee(__('legacy_classic.password.current'));

        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.archive.prepare'))
            ->assertRedirect(route('legacy.archive.index'));
        $this->actingAs($this->mechanic)->get(route('legacy.archive.prepare'))->assertForbidden();
    }

    public function test_callcenter_shows_the_emergency_plan_like_the_old_portal(): void {
        Config::set('app.legacy_callcenter_subtitle', 'der Musterfirma');
        DB::connection('legacy')->table('calluser')->insert(['uname' => 'zentrale', 'userpw' => 'ruf1']);
        DB::connection('legacy')->table('notdnst')->insert(['user' => 6, 'von' => '2026-11-16', 'bis' => '2026-11-16']);

        $this->get(route('legacy.callcenter.login'))
            ->assertOk()
            ->assertViewIs('legacy.classic.callcenter.login')
            ->assertSee('der Musterfirma');

        $this->post(route('legacy.callcenter.login.submit'), ['username' => 'zentrale', 'password' => 'ruf1'])
            ->assertRedirect(route('legacy.callcenter.notdienst'));
        $this->get(route('legacy.callcenter.notdienst'))
            ->assertOk()
            ->assertSee('tb-today', false)
            ->assertSee('Monteur Zwei')
            ->assertSee(__('legacy_classic.menu.logout'));
    }

    public function test_classic_pages_carry_their_menu_and_the_view_switch_in_the_app_header(): void {
        $html = (string) $this->actingAs($this->office)->get(route('legacy.diary.week'))->assertOk()->getContent();
        // Volle Höhe: die Fläche füllt den Bildschirm, das Raster scrollt in seinem Kasten.
        $this->assertStringContainsString('kp-fill', $html);
        $this->assertStringContainsString('class="eintrag tb-grid"', $html);
        $header = Str::between($html, 'id="app-header"', '</header>');
        $this->assertStringContainsString(route('legacy.view', ['mode' => LegacyView::MODERN, 'back' => '/legacy/diary/week']), $header);
        $this->assertStringContainsString('aria-label="' . __('legacy_classic.diary_title') . '"', $header);
        $this->assertStringContainsString('href="' . route('legacy.archive.week') . '"', $header);
        // Passwort und Abmelden stehen im Benutzermenü, nicht noch einmal im Tagebuchmenü.
        $diaryMenu = Str::between($header, 'aria-label="' . __('legacy_classic.diary_title') . '"', '</nav>');
        $this->assertStringNotContainsString(route('legacy.account.password.edit'), $diaryMenu);
        $this->assertStringNotContainsString(route('logout'), $diaryMenu);
        // Die Navigation der neuen Legacy-Ansicht entfällt.
        $this->assertStringNotContainsString('aria-label="' . __('Hauptnavigation') . '"', $header);

        // Urlaub gibt es nur in der neuen Ansicht: dort kein Schalter.
        $this->actingAs($this->office)->get(route('legacy.diary.index', ['tab' => 'urlaub']))
            ->assertOk()
            ->assertDontSee('/legacy/view/classic', false);

        // Ohne App-Konto (Callcenter) sitzt der Schalter in der eigenen Kopfzeile.
        $this->get(route('legacy.callcenter.login'))
            ->assertOk()
            ->assertSee('kp-switch', false)
            ->assertSee('/legacy/view/modern', false);
    }

    public function test_one_switch_for_the_whole_legacy_area(): void {
        $this->actingAs($this->office)->withCookie(LegacyView::COOKIE, LegacyView::MODERN)
            ->get(route('legacy.diary.week'))
            ->assertOk()
            ->assertViewIs('legacy.diary.week')
            ->assertSee('aria-label="' . __('Hauptnavigation') . '"', false)
            ->assertSee(route('legacy.view', ['mode' => LegacyView::CLASSIC, 'back' => '/legacy/diary/week']), false);

        $this->actingAs($this->office)->get(route('legacy.view', ['mode' => LegacyView::MODERN, 'back' => '/legacy/diary/week?week=1']))
            ->assertRedirect(url('/legacy/diary/week?week=1'))
            ->assertCookie(LegacyView::COOKIE, LegacyView::MODERN);
        $this->actingAs($this->office)->get(route('legacy.view', ['mode' => LegacyView::CLASSIC, 'back' => 'https://example.org/legacy/diary']))
            ->assertRedirect(route('legacy.diary.week'));
    }
}
