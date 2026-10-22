<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyCouplingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Guarantee;

use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeKind};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Warranty\{WarrantyBasis, WarrantySide};
use App\Models\Customer\Customer;
use App\Models\Guarantee\Guarantee;
use App\Models\Invoicing\{Invoice, InvoiceRetention};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\Platform\{Organization, User};
use App\Models\Warranty\WarrantyPeriod;
use App\Services\Warranty\WarrantyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Gewährleistung gibt Einbehalt und Bürgschaft frei (MVP-1282). */
final class WarrantyCouplingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function warranty(string $endsOn): WarrantyPeriod {
        return app(WarrantyService::class)->create(WarrantySide::Owed, WarrantyBasis::Custom, '2022-01-10', $endsOn, 'Vertraglich vereinbart', $this->admin, ['customer_id' => $this->customer->id]);
    }

    private function invoice(): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'number' => 'R-' . uniqid(),
            'status' => InvoiceStatus::Draft, 'currency' => 'EUR', 'tax_rate' => '0.00', 'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Rohbau', 'quantity' => '1.000', 'unit_price' => '10000.0000', 'tax_rate' => '0.00', 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->refresh();
    }

    public function test_retention_takes_the_warranty_end_and_follows_a_moved_end(): void {
        $warranty = $this->warranty('2027-01-10');
        $invoice = $this->invoice();

        $this->actingAs($this->admin)->get(route('invoices.retentions.dialog', $invoice))->assertOk()->assertSee('value="' . $warranty->sqid . '"', false);
        $this->actingAs($this->admin)->post(route('invoices.retentions.store', $invoice), [
            'kind' => 'warranty', 'basis' => 'percent', 'percent' => '5', 'warranty_period_id' => $warranty->sqid,
        ])->assertSessionHas('status');

        $retention = InvoiceRetention::query()->where('invoice_id', $invoice->id)->sole();
        $this->assertSame((int) $warranty->id, (int) $retention->warranty_period_id);
        $this->assertSame('2027-01-10', $retention->due_on?->toDateString());

        $warranty->update(['ends_on' => '2027-03-31']);
        $this->assertSame('2027-03-31', $retention->fresh()->due_on?->toDateString());
    }

    public function test_guarantee_is_due_for_return_once_its_warranty_has_ended(): void {
        \Illuminate\Support\Facades\Notification::fake();
        NotificationRule::factory()->forEvent(NotificationEvent::GuaranteeReturnDue)->create([
            'organization_id' => $this->org->id, 'channels' => [NotificationChannel::InApp->value], 'notify_affected' => true, 'recipient_user_ids' => [$this->admin->id],
        ]);
        $running = $this->warranty(now()->addYear()->toDateString());
        $ended = $this->warranty(now()->subDay()->toDateString());
        $base = [
            'organization_id' => $this->org->id, 'direction' => GuaranteeDirection::Issued->value, 'kind' => GuaranteeKind::Warranty->value,
            'amount' => '500.00', 'currency' => 'EUR', 'issuer_name' => 'Musterbank AG', 'customer_id' => $this->customer->id, 'responsible_user_id' => $this->admin->id,
        ];
        Guarantee::query()->create([...$base, 'reference' => 'BG-LAUFEND', 'warranty_period_id' => $running->id]);
        $due = Guarantee::query()->create([...$base, 'reference' => 'BG-ENDE', 'warranty_period_id' => $ended->id]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $logs = NotificationDispatchLog::query()->where('event', NotificationEvent::GuaranteeReturnDue->value)->get();
        $this->assertCount(1, $logs);
        $this->assertSame((int) $due->id, (int) $logs->first()->subject_id);
    }
}
