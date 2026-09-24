<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IndexSortingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Listing;

use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MVP-871: Listen lesen `sort`/`dir` über `SortableQuery::resolve()` —
 * gültiger Schlüssel gilt, ungültiger setzt Schlüssel und Richtung auf den
 * Default, `dir` wird ohne Rücksicht auf Groß-/Kleinschreibung gelesen.
 */
final class IndexSortingTest extends TestCase {
    use RefreshDatabase;

    /** @return array<string, array{string, string, string, string}> Route, Sortierschlüssel, Default, Ansichtsschlüssel */
    public static function sortModes(): array {
        return [
            'Wissensartikel' => ['knowledge.index', 'helpful', 'newest', 'filters'],
            'Risiken' => ['isms.risks.index', 'review', 'score', 'filters'],
            'Schwachstellen' => ['isms.vulnerabilities.index', 'cvss', 'severity', 'filters'],
            'Lieferantenbewertungen' => ['isms.suppliers.index', 'risk', 'criticality', 'filters'],
            'Klassifikationsvorgaben' => ['admin.classification-requirements.index', 'severity', 'entry_type_code', 'activeFilters'],
        ];
    }

    #[DataProvider('sortModes')]
    public function test_sort_mode_is_whitelisted(string $route, string $valid, string $default, string $viewKey): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route($route, ['sort' => $valid]))
            ->assertOk()->assertViewHas($viewKey, fn (array $filters): bool => $filters['sort'] === $valid);
        $this->actingAs($admin)->get(route($route, ['sort' => 'id; drop table users']))
            ->assertOk()->assertViewHas($viewKey, fn (array $filters): bool => $filters['sort'] === $default);
    }

    public function test_document_feed_sort_and_direction(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('billing.feed', ['sort' => 'amount', 'dir' => 'ASC']))
            ->assertOk()->assertViewHas('sort', 'amount')->assertViewHas('dir', 'asc');
        $this->actingAs($admin)->get(route('billing.feed', ['sort' => 'bogus', 'dir' => 'asc']))
            ->assertOk()->assertViewHas('sort', 'date')->assertViewHas('dir', 'desc');
    }

    public function test_project_time_entries_sort_and_direction(): void {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)->get(route('projects.show', ['project' => $project, 'tab' => 'time', 'sort' => 'user', 'dir' => 'asc']))
            ->assertOk()->assertViewHas('timeSort', 'user')->assertViewHas('timeDir', 'asc');
        $this->actingAs($admin)->get(route('projects.show', ['project' => $project, 'tab' => 'time', 'sort' => 'bogus', 'dir' => 'asc']))
            ->assertOk()->assertViewHas('timeSort', 'date')->assertViewHas('timeDir', 'desc');
    }
}
