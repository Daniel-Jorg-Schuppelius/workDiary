<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KpiWidgetTileLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\Diary\Status;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dashboard-Zählkacheln führen zum Auftragsbuch mit dem Filter, den sie zählen
 * (ux-pattern-katalog §4, Vollscan 2026-11-02 gap-06); das Auftragsbuch kennt
 * dafür die Statusgruppen `open` und `active`.
 */
final class KpiWidgetTileLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_personal_widget_links_every_tile(): void {
        $html = view('dashboard.widgets.personal-kpis', ['kpi' => ['open_entries' => 2, 'progress_entries' => 1, 'upcoming_shifts' => 0, 'upcoming_emergencies' => 0]])->render();

        $this->assertStringContainsString(e(route('diary.index', ['mine' => 1, 'status' => 'open'])), $html);
        $this->assertStringContainsString(e(route('diary.index', ['mine' => 1, 'status' => Status::InProgress->value])), $html);
        $this->assertStringContainsString(e(route('shifts.index')), $html);
        $this->assertStringContainsString(e(route('duties.index', ['tab' => 'notdienst'])), $html);
    }

    public function test_team_widget_links_every_tile(): void {
        $html = view('dashboard.widgets.team-kpis', ['kpi' => ['open_entries' => 2, 'progress_entries' => 1, 'archived_today' => 0, 'user_count' => 3]])->render();

        $this->assertStringContainsString(e(route('diary.index', ['status' => 'open'])), $html);
        $this->assertStringContainsString(e(route('diary.index', ['status' => Status::InProgress->value])), $html);
        $this->assertStringContainsString(e(route('diary.index', ['archived' => 1])), $html);
        $this->assertStringContainsString(e(route('org.members.index')), $html);
    }

    public function test_diary_status_groups_filter_the_list_and_mark_the_tile(): void {
        $entry = fn (string $content, Status $status) => DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'content' => $content, 'status' => $status->value,
            'start_at' => now(), 'end_at' => now()->addHour(),
        ]);
        $entry('Offener Auftrag Nordhof', Status::Open);
        $entry('Problemfall Südhof', Status::Problem);
        $entry('Laufender Auftrag Westhof', Status::InProgress);

        $this->actingAs($this->admin)->get(route('diary.index', ['status' => 'open']))->assertOk()
            ->assertSee('Offener Auftrag Nordhof')->assertSee('Problemfall Südhof')->assertDontSee('Laufender Auftrag Westhof');

        $this->actingAs($this->admin)->get(route('diary.index', ['status' => 'active']))->assertOk()
            ->assertSee('Problemfall Südhof')->assertSee('Laufender Auftrag Westhof')->assertDontSee('Offener Auftrag Nordhof')
            ->assertSee('ring-primary/40');
    }
}
