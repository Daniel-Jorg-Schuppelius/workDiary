<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqBillingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\Customer\Customer;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Gaeb\{BoqBillingService, BoqProgressService, GaebImportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-932: Abschlag aus dem Leistungsstand, Rechnungen und Zahlungen je LV. */
final class BoqBillingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private BillOfQuantity $boq;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x86.xml', $this->organization->id);
        $this->boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
        $this->boq->forceFill(['project_id' => $project->id])->save();
    }

    private function progress(string $reference, string $quantity): void {
        app(BoqProgressService::class)->record($this->boq->items()->where('reference_no', $reference)->firstOrFail(), $quantity);
    }

    public function test_down_payments_follow_cumulative_progress(): void {
        $this->progress('01.0010', '40'); // 40 × 12,50 = 500
        $billing = app(BoqBillingService::class);
        $this->assertSame(500.0, $billing->proposal($this->boq->fresh())['amount']);

        $this->actingAs($this->admin)->post(route('bill-of-quantities.billing.store', $this->boq), ['amount' => '600'])->assertSessionHasErrors('amount');
        $this->actingAs($this->admin)->post(route('bill-of-quantities.billing.store', $this->boq), ['amount' => '500'])->assertRedirect();
        $first = Invoice::query()->sole();
        $this->assertSame(Invoice::TYPE_DOWN_PAYMENT, $first->type);
        $this->assertSame($this->boq->id, $first->bill_of_quantity_id);
        $this->assertSame('500.00', $first->subtotal?->getAmount());

        // Kumuliert 100 × 12,50 + 10 × 89,90 = 2.149, abzüglich 500.
        $this->progress('01.0010', '60');
        $this->progress('02.0010', '10');
        $this->assertSame(1649.0, $billing->proposal($this->boq->fresh())['amount']);

        // Ein stornierter Abschlag zählt nicht mehr.
        $first->forceFill(['status' => Invoice::STATUS_CANCELLED])->saveQuietly();
        $this->assertSame(2149.0, $billing->proposal($this->boq->fresh())['amount']);
    }

    public function test_overview_lists_invoices_and_needs_rights(): void {
        $this->progress('01.0010', '10');
        $this->actingAs($this->admin)->post(route('bill-of-quantities.billing.store', $this->boq), ['amount' => '125'])->assertRedirect();
        $number = Invoice::query()->sole()->number;

        $this->actingAs($this->admin)->get(route('bill-of-quantities.billing', $this->boq))->assertOk()
            ->assertSee($number)->assertSee('Abschlagsrechnung')->assertSee('125,00');

        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('bill-of-quantities.billing', $this->boq))->assertForbidden();
    }

    public function test_boq_without_customer_cannot_be_billed(): void {
        $this->boq->forceFill(['project_id' => null])->save();
        $this->progress('01.0010', '10');
        $this->actingAs($this->admin)->post(route('bill-of-quantities.billing.store', $this->boq), ['amount' => '125'])->assertSessionHasErrors('amount');
    }
}
