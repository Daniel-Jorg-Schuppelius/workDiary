<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategoryPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{ExpenseCategory, User};
use App\Policies\ExpenseCategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Spesenkategorien: Lesen für alle, Verwaltung AUSSCHLIESSLICH durch Admin
 * (HasAdminBypass). Fixiert den Sicherheitsbefund aus B7/MVP-348: delete
 * erlaubte zuvor jedem eingeloggten Nutzer das Löschen unbenutzter
 * Kategorien; der Nutzungs-Guard liegt im Controller.
 */
final class ExpenseCategoryPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private ExpenseCategoryPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new ExpenseCategoryPolicy;
    }

    public function test_everyone_may_read_categories(): void {
        $user = $this->actorIn($this->organization);
        $category = new ExpenseCategory;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $category));
    }

    public function test_regular_user_cannot_manage_categories(): void {
        $user = $this->actorIn($this->organization);
        $category = new ExpenseCategory;

        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $category));
        // Sicherheitsbefund B7: auch UNBENUTZTE Kategorien darf kein Nicht-Admin löschen.
        $this->assertFalse($this->policy->delete($user, $category));
    }

    public function test_admin_manages_categories_via_bypass(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $category = new ExpenseCategory;

        $this->assertTrue(Gate::forUser($admin)->allows('create', ExpenseCategory::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $category));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $category));
    }
}
