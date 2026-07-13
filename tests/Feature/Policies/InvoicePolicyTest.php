<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{Invoice, User};
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Rechnungen (Feature 066): Abrechnungsrecht (canManageBilling = Admin oder
 * Buchhaltung) plus GoBD-Statusmaschine — ausgestellte Rechnungen sind
 * unveränderlich (update/delete/issue nur auf Drafts), bezahlte dürfen nur
 * per Gutschrift storniert werden (genau einmal). Mandantengrenze trägt der
 * OrganizationScope (BelongsToOrganization), nicht die Policy.
 */
final class InvoicePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private InvoicePolicy $policy;

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new InvoicePolicy;
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
    }

    private function invoice(string $status, string $type = Invoice::TYPE_INVOICE): Invoice {
        $invoice = new Invoice;
        $invoice->status = $status;
        $invoice->type = $type;

        return $invoice;
    }

    public function test_accountant_may_manage_drafts(): void {
        $draft = $this->invoice(Invoice::STATUS_DRAFT);

        $this->assertTrue($this->policy->viewAny($this->accountant));
        $this->assertTrue($this->policy->view($this->accountant, $draft));
        $this->assertTrue($this->policy->create($this->accountant));
        $this->assertTrue($this->policy->update($this->accountant, $draft));
        $this->assertTrue($this->policy->delete($this->accountant, $draft));
        $this->assertTrue($this->policy->issue($this->accountant, $draft));
        $this->assertTrue($this->policy->cancel($this->accountant, $draft));
        $this->assertTrue($this->policy->send($this->accountant, $draft));
    }

    public function test_issued_invoices_are_immutable_but_payable_and_cancellable(): void {
        $issued = $this->invoice(Invoice::STATUS_ISSUED);

        $this->assertFalse($this->policy->update($this->accountant, $issued), 'Ausgestellte Rechnung ist unveränderlich (GoBD).');
        $this->assertFalse($this->policy->delete($this->accountant, $issued));
        $this->assertFalse($this->policy->issue($this->accountant, $issued));
        $this->assertTrue($this->policy->pay($this->accountant, $issued));
        $this->assertTrue($this->policy->cancel($this->accountant, $issued));
        $this->assertFalse($this->policy->createCreditNote($this->accountant, $issued), 'Gutschrift nur für bezahlte Rechnungen.');
    }

    public function test_paid_invoices_require_credit_note_to_cancel(): void {
        $paid = $this->invoice(Invoice::STATUS_PAID);

        $this->assertFalse($this->policy->cancel($this->accountant, $paid), 'Bezahlte Rechnung: kein Direkt-Storno.');
        $this->assertTrue($this->policy->createCreditNote($this->accountant, $paid));
        $this->assertFalse($this->policy->pay($this->accountant, $paid));
    }

    public function test_credit_notes_cannot_be_cancelled_or_credited_again(): void {
        $creditNote = $this->invoice(Invoice::STATUS_PAID, Invoice::TYPE_CREDIT_NOTE);

        $this->assertFalse($this->policy->cancel($this->accountant, $creditNote));
        $this->assertFalse($this->policy->createCreditNote($this->accountant, $creditNote));
    }

    public function test_regular_user_has_no_invoice_access(): void {
        $user = $this->actorIn($this->organization);
        $draft = $this->invoice(Invoice::STATUS_DRAFT);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $draft));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $draft));
        $this->assertFalse($this->policy->send($user, $draft));
    }

    public function test_orgless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
