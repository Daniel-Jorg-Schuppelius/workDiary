<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diary;

use App\Enums\User\{Permission, UserRole};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Aufträge prüfen ihre Rechte (lk-06): für andere anlegen mit
 * diary.create-for-others, fremde exportieren mit diary.export, eigene und
 * zugewiesene ansehen mit diary.viewOwn.
 */
class DiaryPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_THIS_YEAR);
    }

    public function test_creating_orders_for_others_follows_the_right(): void {
        foreach ([UserRole::Callcenter, UserRole::Teamleitung] as $role) {
            $this->actingAs($this->userWithRole($role->value))
                ->get(route('diary.create'))
                ->assertOk()
                ->assertViewHas('canCreateForOthers', true);
        }
        $this->actingAs($this->userWithRole(UserRole::Buchhaltung->value))
            ->get(route('diary.create'))
            ->assertViewHas('canCreateForOthers', false);
        $this->actingAs($this->orgAdmin())
            ->get(route('diary.create'))
            ->assertViewHas('canCreateForOthers', true);
    }

    public function test_export_of_foreign_orders_needs_diary_export(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $this->entry($worker, 'Auftrag des Mitarbeiters');
        $callcenter = $this->userWithRole(UserRole::Callcenter->value);
        $this->entry($callcenter, 'Auftrag der Zentrale');

        $leadCsv = $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))->get(route('diary.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Auftrag des Mitarbeiters', $leadCsv);

        $callcenterCsv = $this->actingAs($callcenter)->get(route('diary.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Auftrag der Zentrale', $callcenterCsv);
        $this->assertStringNotContainsString('Auftrag des Mitarbeiters', $callcenterCsv);

        $adminCsv = $this->actingAs($this->orgAdmin())->get(route('diary.export.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Auftrag des Mitarbeiters', $adminCsv);
    }

    public function test_viewing_own_orders_needs_diary_view_own(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $bare = $this->actorIn($this->organization, [Permission::DiaryCreate]);

        $this->assertTrue(Gate::forUser($worker)->allows('view', $this->entry($worker, 'Eigener Auftrag')));
        $this->assertFalse(Gate::forUser($bare)->allows('view', $this->entry($bare, 'Ohne Leserecht')));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('view', $this->entry($worker, 'Admin liest')));
    }

    private function entry(User $owner, string $content): DiaryEntry {
        return DiaryEntry::factory()->for($owner)->create([
            'organization_id' => $this->organization->id,
            'content' => $content,
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);
    }
}
