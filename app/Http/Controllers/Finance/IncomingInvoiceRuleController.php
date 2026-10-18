<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\{IncomingInvoiceExclusion, InvoiceSenderRule};
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Regeln des Rechnungseingangs (Feature 163, MVP-1208): Absenderregeln und
 * Ausschlüsse einsehen und löschen. Angelegt werden beide nur im Zuordnen-Dialog.
 */
class IncomingInvoiceRuleController extends Controller {
    public function index(): View {
        $this->authorizeBilling();

        return view('finance.incoming-invoices.rules', [
            'senderRules' => InvoiceSenderRule::query()->with(['supplier:id,name', 'customer:id,name'])->orderBy('email')->get(),
            'exclusions' => IncomingInvoiceExclusion::query()->orderBy('party_name')->get(),
        ]);
    }

    public function destroySenderRule(InvoiceSenderRule $rule): RedirectResponse {
        $this->authorizeBilling();
        $rule->audit('incoming_einvoice.sender_rule_deleted', ['email' => $rule->email]);
        $rule->delete();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', __('Regel gelöscht.'));
    }

    public function destroyExclusion(IncomingInvoiceExclusion $exclusion): RedirectResponse {
        $this->authorizeBilling();
        $exclusion->audit('incoming_einvoice.exclusion_deleted', ['party' => $exclusion->party_name]);
        $exclusion->delete();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', __('Regel gelöscht.'));
    }

    private function authorizeBilling(): void {
        abort_unless($this->authUser()->canManageBilling(), 403);
    }
}
