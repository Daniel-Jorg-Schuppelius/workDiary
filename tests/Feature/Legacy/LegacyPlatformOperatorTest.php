<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyPlatformOperatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Legacy;

use App\Legacy\Support\LegacyRoleResolver;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Config, DB};
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

/**
 * Der Plattformbetrieb verwaltet das Altsystem auch ohne Legacy-Konto
 * (Befund 2026-11-09: die Wochenansicht zeigte ihm keine Mitarbeiter, Archiv-
 * und Arbeitsliste nur „eigene" Einträge, obwohl er sie öffnen und pflegen durfte).
 */
class LegacyPlatformOperatorTest extends TestCase {
    use RefreshDatabase;
    use UsesLegacySqlite;

    private User $operator;

    protected function setUp(): void {
        parent::setUp();
        $this->useLegacySqlite();
        Config::set('app.legacy_write_enabled', true);

        $legacy = DB::connection('legacy');
        $legacy->table('user')->insert([
            ['id' => 4, 'uname' => 'Monteur Alpha', 'userpw' => 'x', 'email' => null],
            ['id' => 5, 'uname' => 'Monteur Beta', 'userpw' => 'x', 'email' => null],
        ]);
        $legacy->table('a_tagebuch')->insert([
            'id' => 90, 'user' => 4, 'inhalt' => 'Archivierte Wartung', 'antwort' => '',
            'von' => '2026-01-05 08:00:00', 'bis' => '2026-01-05 10:00:00', 'aktuell' => '2026-01-05 10:00:00', 'gelesen' => -1, 'sms' => '',
        ]);

        $this->operator = User::factory()->platformAdmin()->create();
    }

    public function test_operator_manages_but_does_not_count_as_legacy_admin(): void {
        $this->assertTrue(LegacyRoleResolver::canManage($this->operator));
        $this->assertFalse(LegacyRoleResolver::isAdmin($this->operator));
        $this->assertTrue($this->operator->canViewAllLegacyData());
    }

    public function test_week_view_shows_all_employees_and_the_archive_week_link(): void {
        $this->actingAs($this->operator)->get(route('legacy.diary.week'))
            ->assertOk()
            ->assertSee('Monteur Alpha')
            ->assertSee('Monteur Beta')
            ->assertSee(route('legacy.archive.week', [], false), false);
    }

    public function test_archive_lists_entries_of_other_employees(): void {
        $this->actingAs($this->operator)->get(route('legacy.archive.index', ['tab' => 'auftraege']))
            ->assertOk()
            ->assertSee('Archivierte Wartung')
            ->assertSee(__('Archivierung starten'));
    }

    public function test_duty_buttons_follow_the_backend_but_diary_entries_need_a_legacy_account(): void {
        $this->actingAs($this->operator)->get(route('legacy.diary.index', ['tab' => 'bereitschaft']))
            ->assertOk()
            ->assertViewHas('canManageDuties', true)
            ->assertViewHas('isAdmin', false)
            ->assertSee(route('legacy.oncall.create'), false);
    }

    public function test_employee_without_admin_rights_still_sees_only_himself(): void {
        $employee = User::factory()->create(['legacy_user_id' => 4]);

        $this->assertFalse(LegacyRoleResolver::canManage($employee));
        $this->actingAs($employee)->get(route('legacy.diary.week'))
            ->assertOk()
            ->assertSee('Monteur Alpha')
            ->assertDontSee('Monteur Beta');
    }
}
