<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTransmissionAndReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Finance\DatevTransmissionStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\DatevBookingBatch;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\DatevBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Übermittlungsstatus (MVP-1277) und Begleitbericht (MVP-1278) des DATEV-Stapels. */
final class DatevTransmissionAndReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization([
            'settings' => ['datev' => ['advisor_number' => 12345, 'client_number' => 1, 'skr' => 'skr03', 'debtor_base' => 10000]],
        ]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function batch(bool $finalize = true): DatevBookingBatch {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
        Invoice::create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'RE-' . uniqid(),
            'status' => InvoiceStatus::Issued, 'type' => Invoice::TYPE_INVOICE, 'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-15', 'currency' => 'EUR', 'tax_rate' => '19.00', 'subtotal' => '100.00', 'tax_amount' => '19.00', 'total' => '119.00',
        ]);
        $service = app(DatevBookingService::class);
        $config = DatevBookingConfig::forOrganization($this->organization);
        $period = ['from' => '2026-05-01', 'to' => '2026-05-31'];
        $batch = $service->createDraft($this->organization, $period, $service->collectBookingReady($this->organization, $period), $config, $this->admin);

        return $finalize ? $service->finalize($batch, $config, $this->admin) : $batch;
    }

    public function test_transmission_follows_the_journal_and_its_transitions(): void {
        $batch = $this->batch();
        $service = app(DatevBookingService::class);
        $this->assertSame(DatevTransmissionStatus::Open, $service->transmissionStatus($batch));

        $this->actingAs($this->admin)->get(route('finance.datev.transmission.dialog', $batch))->assertOk();
        $this->actingAs($this->admin)->post(route('finance.datev.transmission', $batch), ['status' => 'transmitted'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('finance.datev.transmission', $batch), ['status' => 'rejected', 'note' => 'Konto 8400 fehlt'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('finance.datev.transmission', $batch), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('finance.datev.transmission', $batch), ['status' => 'transmitted'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('finance.datev.transmission', $batch), ['status' => 'confirmed'])->assertSessionHas('success');

        $batch = $batch->fresh();
        $this->assertSame(DatevTransmissionStatus::Confirmed, $service->transmissionStatus($batch));
        $this->assertSame(['transmitted', 'rejected', 'transmitted', 'confirmed'], $batch->journal()->whereIn('event', DatevBookingService::TRANSMISSION_EVENTS)->orderBy('id')->pluck('event')->all());

        $this->actingAs($this->admin)->get(route('finance.datev.index'))->assertOk()->assertSee(DatevTransmissionStatus::Confirmed->label());
        $this->actingAs($this->admin)->get(route('finance.datev.show', $batch))->assertOk()->assertSee('Konto 8400 fehlt');
    }

    public function test_draft_batches_are_not_transmitted(): void {
        $batch = $this->batch(finalize: false);

        $this->expectException(ValidationException::class);
        app(DatevBookingService::class)->recordTransmission($batch, DatevTransmissionStatus::Transmitted, null, $this->admin);
    }

    public function test_report_pdf_for_a_finalized_batch(): void {
        $batch = $this->batch();

        app(DatevBookingService::class)->recordTransmission($batch, DatevTransmissionStatus::Transmitted, 'Kanzlei-Upload', $this->admin);

        $this->mock(DocumentDesignRenderer::class, function ($mock) use ($batch): void {
            $mock->shouldReceive('renderPdf')->once()->withArgs(function (RenderDocumentKind $kind, string $view, array $data) use ($batch): bool {
                $html = view($view, $data)->render();

                return $kind === RenderDocumentKind::Report
                    && str_contains($html, (string) $batch->file_hash)
                    && str_contains($html, (string) $batch->sources->first()?->revenue_account)
                    && str_contains($html, 'Kanzlei-Upload')
                    && str_contains($html, DatevTransmissionStatus::Transmitted->label());
            })->andReturn('%PDF-1.4');
        });
        $this->actingAs($this->admin)->get(route('finance.datev.report', $batch))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
