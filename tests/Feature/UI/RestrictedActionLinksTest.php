<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestrictedActionLinksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Enums\Project\ProjectStatus;
use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Befunde des UI-Vollcrawls 2026-10-03 als einfacher Benutzer: Listen, die
 * jeder lesen darf, zeigten Anlegen-/Bearbeiten-Knöpfe und Reiter, deren Ziel
 * ein eigenes Recht verlangt — der Klick endete im 403.
 */
class RestrictedActionLinksTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $plain;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->plain = $this->orgUser();
        $this->admin = $this->orgAdmin();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function readOnlyLists(): array {
        return [
            'Klassifikationen' => ['admin.classifications.index', 'admin.classifications.create'],
            'Pflichtangaben' => ['admin.classification-requirements.index', 'admin.classification-requirements.create'],
            'Spesenkategorien' => ['admin.expense-categories.index', 'admin.expense-categories.create'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readOnlyLists')]
    public function test_lists_offer_create_only_with_the_right(string $index, string $create): void {
        $this->actingAs($this->plain)->get(route($index))
            ->assertOk()
            ->assertDontSee(route($create), false);

        $this->actingAs($this->admin)->get(route($index))
            ->assertOk()
            ->assertSee(route($create), false);
    }

    public function test_agile_report_tabs_need_the_report_right(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Agil-Reiter',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
        $tabs = fn(User $viewer): string => tap($this->actingAs($viewer), fn() => null)
            ? view('agile._tabs', ['project' => $project, 'board' => true])->render()
            : '';

        $this->assertStringNotContainsString(route('agile.reports.sprint', $project), $tabs($this->plain));
        $this->assertStringNotContainsString(route('agile.reports.flow', $project), $tabs($this->plain));
        $this->assertStringContainsString(route('agile.board', $project), $tabs($this->plain));

        $this->assertStringContainsString(route('agile.reports.sprint', $project), $tabs($this->admin));
        $this->assertStringContainsString(route('agile.reports.flow', $project), $tabs($this->admin));
    }
}
