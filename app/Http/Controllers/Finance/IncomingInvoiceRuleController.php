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
use App\Models\Automation\AutomationRule;
use App\Models\Invoicing\{IncomingInvoiceTemplate, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Services\Invoicing\Automation\IncomingInvoiceReceivedTrigger;
use App\Services\Invoicing\EInvoice\IncomingInvoiceRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Regeln des Rechnungseingangs (Feature 163, MVP-1208/1211): Absenderregeln
 * (Abgleich) und Posteingangsregeln (Automationsregeln des Auslösers
 * „Rechnungseingang eingegangen“) einsehen, schalten und löschen. Angelegt
 * werden sie im Zuordnen-Dialog oder auf der Automationsseite.
 */
class IncomingInvoiceRuleController extends Controller {
    public function __construct(private readonly IncomingInvoiceRules $rules) {}

    public function index(): View {
        $user = $this->authorizeBilling();
        $rules = $this->rules->rulesFor((int) $user->organization_id);

        return view('finance.incoming-invoices.rules', [
            'senderRules' => InvoiceSenderRule::query()->with(['supplier:id,name', 'customer:id,name'])->orderBy('email')->get(),
            'rules' => $rules,
            'hits' => $this->rules->hits($rules),
            'describe' => $this->rules,
            'canEditAutomations' => $user->isAdmin(),
            'templates' => IncomingInvoiceTemplate::query()->with('supplier:id,name')->orderBy('name')->get(),
        ]);
    }

    public function destroyTemplate(IncomingInvoiceTemplate $template): RedirectResponse {
        $this->authorizeBilling();
        $template->audit('incoming_einvoice.template_deleted', ['name' => $template->name]);
        $template->delete();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', __('Vorlage gelöscht.'));
    }

    public function toggleRule(AutomationRule $rule): RedirectResponse {
        $this->authorizeBilling();
        abort_unless($rule->trigger_event === IncomingInvoiceReceivedTrigger::KEY, 404);
        $rule->forceFill(['is_active' => ! $rule->is_active])->save();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', $rule->is_active ? __('Regel aktiviert.') : __('Regel deaktiviert.'));
    }

    public function destroyRule(AutomationRule $rule): RedirectResponse {
        $this->authorizeBilling();
        abort_unless($rule->trigger_event === IncomingInvoiceReceivedTrigger::KEY, 404);
        $rule->delete();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', __('Regel gelöscht.'));
    }

    public function destroySenderRule(InvoiceSenderRule $rule): RedirectResponse {
        $this->authorizeBilling();
        $rule->audit('incoming_einvoice.sender_rule_deleted', ['email' => $rule->email]);
        $rule->delete();

        return redirect()->toList('finance.incoming-invoices.rules')->with('success', __('Regel gelöscht.'));
    }

    private function authorizeBilling(): User {
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
