<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankPaymentPolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Policies\Finance\{BankStatementPolicy, BankTransactionPolicy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Bankauszüge + Bankumsätze (Feature 045): Import nur mit
 * finance.payment.import, Zuordnung nur mit finance.payment.reconcile,
 * Lesen zusätzlich mit finance.viewAny. Bankumsätze sind NIE editierbar
 * (bewusst keine update/delete-Methoden). Mandantengrenze trägt der
 * OrganizationScope (BelongsToOrganization), nicht die Policy.
 */
final class BankPaymentPolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    public function test_statement_import_requires_import_permission(): void {
        $policy = new BankStatementPolicy;
        $importer = $this->actorIn($this->organization, [P::FinancePaymentImport]);
        $viewer = $this->actorIn($this->organization, [P::FinanceViewAny]);

        $this->assertTrue($policy->create($importer));
        $this->assertTrue($policy->viewAny($importer));
        $this->assertTrue($policy->view($importer, new BankStatement));

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->download($viewer, new BankStatement));
        $this->assertFalse($policy->create($viewer), 'finance.viewAny darf nicht importieren.');
    }

    public function test_transaction_reconcile_requires_reconcile_permission(): void {
        $policy = new BankTransactionPolicy;
        $reconciler = $this->actorIn($this->organization, [P::FinancePaymentReconcile]);
        $viewer = $this->actorIn($this->organization, [P::FinanceViewAny]);
        $transaction = new BankTransaction;

        $this->assertTrue($policy->viewAny($reconciler));
        $this->assertTrue($policy->view($reconciler, $transaction));
        $this->assertTrue($policy->reconcile($reconciler, $transaction));

        $this->assertTrue($policy->view($viewer, $transaction));
        $this->assertFalse($policy->reconcile($viewer, $transaction), 'Lesen erlaubt kein Zuordnen.');
    }

    public function test_without_finance_permissions_denied(): void {
        $nobody = $this->actorIn($this->organization);
        $orgless = $this->orglessActor();

        $this->assertFalse((new BankStatementPolicy)->viewAny($nobody));
        $this->assertFalse((new BankTransactionPolicy)->viewAny($nobody));
        $this->assertFalse((new BankStatementPolicy)->viewAny($orgless));
        $this->assertFalse((new BankTransactionPolicy)->reconcile($orgless, new BankTransaction));
    }
}
