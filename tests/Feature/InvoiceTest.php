<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Mail\InvoiceMail;
use App\Models\{Customer, Invoice, InvoiceMailTemplate, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class InvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_index_requires_billing_role(): void {
        $regular = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($regular)->get(route('invoices.index'))->assertForbidden();
        $this->getAsAdmin('invoices.index')->assertOk();
    }

    public function test_create_invoice_from_time_entries(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('180.00', $invoice->subtotal);
        $this->assertSame('34.20', $invoice->tax_amount);
        $this->assertSame('214.20', $invoice->total);
    }

    public function test_issue_and_pay_workflow(): void {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0001',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $this->postAsAdmin('invoices.issue', [], $invoice)->assertRedirect();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()?->status);

        $this->postAsAdmin('invoices.pay', [], $invoice)->assertRedirect();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()?->status);
    }

    public function test_pdf_export(): void {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0002',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'h',
            'unit_price' => '90.00',
            'position' => 1,
        ]);

        $response = $this->getAsAdmin('invoices.pdf', $invoice);
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    private function getAsAdmin(string $routeName, mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->get(route($routeName, $parameters));
    }

    private function postAsAdmin(string $routeName, array $payload = [], mixed $parameters = []): TestResponse {
        return $this->actingAs($this->admin)->post(route($routeName, $parameters), $payload);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Storno-Workflow
    // ────────────────────────────────────────────────────────────────────────

    public function test_cancel_draft_invoice(): void {
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $this->postAsAdmin('invoices.cancel', ['reason' => 'Kunde widerruft'], $invoice)->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame($this->admin->id, $invoice->cancelled_by);
        $this->assertSame('Kunde widerruft', $invoice->cancel_reason);
    }

    public function test_cancel_issued_invoice(): void {
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED, [
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
        ]);

        $this->postAsAdmin('invoices.cancel', [], $invoice)->assertRedirect();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->fresh()?->status);
    }

    public function test_cancel_paid_invoice_is_forbidden(): void {
        $invoice = $this->makeInvoice(Invoice::STATUS_PAID, [
            'issued_on' => now()->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);

        $this->postAsAdmin('invoices.cancel', [], $invoice)->assertForbidden();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()?->status);
    }

    public function test_credit_note_only_for_paid(): void {
        $draft = $this->makeInvoice(Invoice::STATUS_DRAFT);
        $this->postAsAdmin('invoices.credit-note', [], $draft)->assertForbidden();
    }

    public function test_credit_note_creates_negative_invoice(): void {
        $original = $this->makeInvoice(Invoice::STATUS_PAID, [
            'issued_on' => now()->subDays(5)->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);
        $original->items()->create([
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'h',
            'unit_price' => '90.00',
            'position' => 1,
        ]);
        $original->refresh();
        $original->recalculate();
        $original->save();

        $this->postAsAdmin('invoices.credit-note', [], $original)->assertRedirect();

        $credit = Invoice::query()->where('parent_invoice_id', $original->id)->firstOrFail();
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $credit->type);
        $this->assertStringStartsWith('G', $credit->number);
        $this->assertSame(Invoice::STATUS_DRAFT, $credit->status);
        $this->assertSame(1, $credit->items()->count());
        /** @var \App\Models\InvoiceItem $item */
        $item = $credit->items()->first();
        $this->assertSame('-2.00', $item->quantity);
        $this->assertSame('-180.00', $credit->subtotal);
        $this->assertSame('-214.20', $credit->total);
    }

    public function test_credit_note_cannot_be_created_twice(): void {
        $original = $this->makeInvoice(Invoice::STATUS_PAID, [
            'issued_on' => now()->subDays(5)->toDateString(),
            'paid_on' => now()->toDateString(),
        ]);
        $this->postAsAdmin('invoices.credit-note', [], $original)->assertRedirect();
        $this->postAsAdmin('invoices.credit-note', [], $original)->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Mailversand
    // ────────────────────────────────────────────────────────────────────────

    public function test_send_invoice_queues_mail_and_updates_status(): void {
        Mail::fake();
        $template = $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $this->postAsAdmin('invoices.send', [
            'template_id' => $template->id,
            'to' => ['kunde@example.test'],
            'cc' => ['cc@example.test'],
            'custom_text' => 'Vielen Dank.',
            'bcc_sender' => '1',
        ], $invoice)->assertRedirect();

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $m) use ($invoice): bool {
            return $m->invoice->is($invoice)
                && $m->hasTo('kunde@example.test')
                && $m->hasCc('cc@example.test')
                && str_contains($m->renderedSubject, $invoice->number);
        });

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(1, $invoice->sent_count);
        $this->assertNotNull($invoice->issued_on);
        $this->assertNotNull($invoice->due_on);
    }

    public function test_send_invoice_validates_emails(): void {
        $template = $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(Invoice::STATUS_ISSUED);

        $this->postAsAdmin('invoices.send', [
            'template_id' => $template->id,
            'to' => ['nicht-eine-email'],
        ], $invoice)->assertSessionHasErrors(['to.0']);
    }

    public function test_send_form_renders(): void {
        $this->makeDefaultTemplate();
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);
        $this->getAsAdmin('invoices.send.form', $invoice)->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function makeInvoice(string $status, array $overrides = []): Invoice {
        return Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function makeDefaultTemplate(): InvoiceMailTemplate {
        return InvoiceMailTemplate::create([
            'organization_id' => null,
            'name' => 'Test',
            'is_default' => true,
            'subject' => '{{document_label}} {{invoice_number}}',
            'body_html' => '<p>Hallo {{customer_name}}, Betrag: {{total}} {{currency}}. {{custom_text}}</p>',
            'body_text' => 'Hallo {{customer_name}}, Betrag: {{total}} {{currency}}. {{custom_text}}',
        ]);
    }
}
