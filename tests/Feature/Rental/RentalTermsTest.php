<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalTermsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Contract\{ContractKind, ContractStatus};
use App\Models\Asset\Asset;
use App\Models\Contract\{Contract, ContractSigningRevision};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalProfile};
use App\Services\Rental\RentalCaseService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-895: Mietbedingungen mit Fassung und Unterschrift am Verleihvorgang. */
final class RentalTermsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rüttelplatte']);
        RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $this->asset->id, 'is_rentable' => true, 'group_code' => 'verdichter']);
        $this->actingAs($this->admin);
    }

    private function openCase(): RentalCase {
        return app(RentalCaseService::class)->open($this->organization, $this->admin, [
            'customer_id' => $this->customer->id,
            'starts_at' => now()->addDay()->setTime(8, 0),
            'ends_at' => now()->addDays(2)->setTime(17, 0),
        ], [$this->asset->id]);
    }

    private function signedTerms(): ContractSigningRevision {
        $contract = Contract::factory()->create([
            'organization_id' => $this->organization->id,
            'kind' => ContractKind::RentalTerms->value,
            'status' => ContractStatus::Active->value,
            'customer_id' => $this->customer->id,
        ]);

        return ContractSigningRevision::query()->create([
            'organization_id' => $this->organization->id,
            'contract_id' => $contract->id,
            'revision_no' => 2,
            'status' => 'signed',
            'declaration_text' => 'Mietbedingungen 2026',
            'manifest_hash' => str_repeat('a', 64),
            'completed_at' => now()->subDay(),
        ]);
    }

    public function test_reservation_freezes_the_signed_rental_terms(): void {
        $this->assertTrue(ContractKind::RentalTerms->requiresSigning());
        $revision = $this->signedTerms();
        $case = $this->openCase();

        app(RentalCaseService::class)->reserve($case, $this->admin);

        $case->refresh();
        $this->assertSame($revision->id, (int) $case->contract_signing_revision_id);
        $this->assertSame(2, data_get($case->terms_snapshot, 'rental_terms.revision_no'));
        $this->assertSame(str_repeat('a', 64), data_get($case->terms_snapshot, 'rental_terms.manifest_hash'));

        $this->get(route('rental.show', $case))->assertOk()->assertSee(__('rental.terms.title'));
    }

    public function test_handover_requires_signed_terms_only_when_the_organisation_says_so(): void {
        $service = app(RentalCaseService::class);
        $case = $this->openCase();
        $service->reserve($case, $this->admin);
        $service->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);
        $this->assertNull($case->fresh()->contract_signing_revision_id);

        Setting::set('rental.require_signed_terms', true, SettingScope::Organization, $this->organization);
        app()->instance('currentOrganization', $this->organization->fresh());
        $second = $this->openCase();
        $second->forceFill(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(6)])->save();
        $service->reserve($second, $this->admin);

        $this->expectExceptionMessage((string) __('rental.terms.required'));
        $service->handover($second->fresh(), $this->asset, $this->admin, ['condition' => 'good']);
    }
}
