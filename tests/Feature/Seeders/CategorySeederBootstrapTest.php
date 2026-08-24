<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CategorySeederBootstrapTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Seeders;

use App\Models\{ActivityCategory, ExpenseCategory, Organization};
use Database\Seeders\{ActivityCategorySeeder, ExpenseCategorySeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, J3: deploy.sh seedet bei jedem Deploy — die Kategorie-
 * Seeder setzten per updateOrCreate UI-Änderungen zurück (Label, Farbe,
 * billable_default, deaktivierte Kategorien). Jetzt bootstrap-only wie der
 * EntryTypeSeeder; neue Orgs bekommen ihre Erstausstattung über den Observer.
 */
class CategorySeederBootstrapTest extends TestCase {
    use RefreshDatabase;

    public function test_new_organization_gets_both_catalogs_once(): void {
        $organization = Organization::factory()->create();

        $activities = ActivityCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count();
        $expenses = ExpenseCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count();

        $this->assertGreaterThan(0, $activities);
        $this->assertGreaterThan(0, $expenses);

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame($activities, ActivityCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
        $this->assertSame($expenses, ExpenseCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count());
    }

    public function test_redeploy_seed_keeps_organization_edits(): void {
        $organization = Organization::factory()->create();

        $activity = ActivityCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->firstOrFail();
        $activity->forceFill(['label' => 'Büro (angepasst)', 'active' => false])->save();

        $expense = ExpenseCategory::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->firstOrFail();
        $expense->forceFill(['label' => 'Spesen (angepasst)', 'is_active' => false])->save();

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame('Büro (angepasst)', $activity->fresh()?->label);
        $this->assertFalse((bool) $activity->fresh()?->active);
        $this->assertSame('Spesen (angepasst)', $expense->fresh()?->label);
        $this->assertFalse((bool) $expense->fresh()?->is_active);
    }
}
