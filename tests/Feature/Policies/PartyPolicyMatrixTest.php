<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Customer, ForeignCustomer, Organization, Supplier, User};
use App\Policies\{CustomerPolicy, ForeignCustomerPolicy, SupplierPolicy};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Matrix-Test über die drei baugleichen Stammdaten-Policies Kunde,
 * Fremd-/Endkunde und Lieferant: view strikt organisationsgebunden
 * (Defense-in-Depth zum OrganizationScope), Anlegen für Abrechnung ODER
 * User-Rolle, Ändern/Archivieren für Abrechnung ODER den Ersteller
 * (created_by), Löschen nur Abrechnung und nur ohne Referenzen.
 */
final class PartyPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    /** @return array<string, array{class-string, class-string<Model>}> */
    public static function partyPolicies(): array {
        return [
            'customer' => [CustomerPolicy::class, Customer::class],
            'foreign-customer' => [ForeignCustomerPolicy::class, ForeignCustomer::class],
            'supplier' => [SupplierPolicy::class, Supplier::class],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function party(string $modelClass, ?int $orgId = null, ?User $creator = null): Model {
        /** @var Model&object{organization_id: int|null, created_by: int|null} $party */
        $party = new $modelClass;
        $party->organization_id = $orgId ?? $this->organization->id;
        $party->created_by = $creator?->id;

        return $party;
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('partyPolicies')]
    public function test_view_is_org_bound_for_everyone(string $policyClass, string $modelClass): void {
        $policy = new $policyClass;
        $user = $this->actorIn($this->organization);
        $own = $this->party($modelClass);
        $foreign = $this->party($modelClass, Organization::factory()->create()->id);

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $own));
        $this->assertFalse($policy->view($user, $foreign), 'Fremd-Org-Stammdaten sind unsichtbar (Defense-in-Depth).');
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('partyPolicies')]
    public function test_creator_and_billing_may_edit_others_may_not(string $policyClass, string $modelClass): void {
        $policy = new $policyClass;
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $otherUser = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $nobody = $this->actorIn($this->organization); // ohne Rolle
        $this->actAsTeam($this->organization);
        $party = $this->party($modelClass, null, $creator);

        $this->assertTrue($policy->create($creator), 'User-Rolle darf anlegen.');
        $this->assertFalse($policy->create($nobody), 'Ohne Rolle kein Anlegen.');

        $this->assertTrue($policy->update($creator, $party), 'Ersteller pflegt seinen Datensatz.');
        $this->assertTrue($policy->update($accountant, $party));
        $this->assertFalse($policy->update($otherUser, $party), 'Fremder Datensatz ohne Abrechnungsrecht ist tabu.');

        $this->assertTrue($policy->archive($creator, $party));
        $this->assertTrue($policy->restore($accountant, $party));
        $this->assertFalse($policy->archive($otherUser, $party));
    }

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('partyPolicies')]
    public function test_delete_is_billing_only(string $policyClass, string $modelClass): void {
        $policy = new $policyClass;
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $party = $this->party($modelClass, null, $creator);

        $this->assertFalse($policy->delete($creator, $party), 'Selbst der Ersteller löscht nicht ohne Abrechnungsrecht.');
        $this->assertTrue($policy->delete($accountant, $party), 'Unreferenzierte Stammdaten sind für die Abrechnung löschbar.');
    }

    public function test_push_and_promote_are_billing_only(): void {
        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        /** @var Customer $customer */
        $customer = $this->party(Customer::class, null, $creator);
        $this->assertFalse((new CustomerPolicy)->pushToLexoffice($creator, $customer));
        $this->assertTrue((new CustomerPolicy)->pushToLexoffice($accountant, $customer));

        /** @var ForeignCustomer $foreignCustomer */
        $foreignCustomer = $this->party(ForeignCustomer::class, null, $creator);
        $this->assertFalse((new ForeignCustomerPolicy)->promote($creator, $foreignCustomer));
        $this->assertTrue((new ForeignCustomerPolicy)->promote($accountant, $foreignCustomer));

        /** @var Supplier $supplier */
        $supplier = $this->party(Supplier::class, null, $creator);
        $this->assertFalse((new SupplierPolicy)->pushToLexoffice($creator, $supplier));
        $this->assertTrue((new SupplierPolicy)->pushToLexoffice($accountant, $supplier));
    }
}
