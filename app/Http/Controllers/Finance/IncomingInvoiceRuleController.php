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

use App\Enums\Billing\DocumentDirection;
use App\Http\Controllers\Concerns\AuthorizesOrganizationManagement;
use App\Http\Controllers\Controller;
use App\Models\Automation\AutomationRule;
use App\Models\Invoicing\InvoiceSenderRule;
use App\Services\Invoicing\Automation\IncomingInvoiceReceivedTrigger;
use App\Services\Invoicing\EInvoice\{IncomingInvoiceParties, IncomingInvoiceRules};
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Regeln des Rechnungseingangs (Feature 163, MVP-1208/1211): Absenderregeln
 * (Abgleich) und Posteingangsregeln (Automationsregeln des Auslösers
 * „Rechnungseingang eingegangen“) einsehen, schalten und löschen. Angelegt
 * werden sie im Zuordnen-Dialog oder auf der Automationsseite.
 */
class IncomingInvoiceRuleController extends Controller {
    use AuthorizesOrganizationManagement;

    public function __construct(private readonly IncomingInvoiceRules $rules, private readonly IncomingInvoiceParties $parties) {}

    public function index(): View {
        $user = $this->authorizeBilling();
        $rules = $this->rules->rulesFor((int) $user->organization_id);

        return view('finance.incoming-invoices.rules', [
            'senderRules' => InvoiceSenderRule::query()->with(['supplier:id,name', 'customer:id,name'])->orderBy('email')->get(),
            'rules' => $rules,
            'hits' => $this->rules->hits($rules),
            'describe' => $this->rules,
            'canEditAutomations' => $user->isAdmin(),
            // Am Sammelkontakt geparkte Belege (MVP-1225).
            'parked' => $this->parties->parkedAtCollective((int) $user->organization_id),
        ]);
    }

    /** Vom Sammelkontakt auf eine echte Partei umhängen (MVP-1225). */
    public function promoteCollective(Request $request): RedirectResponse {
        $user = $this->authorizeBilling();
        $data = $request->validate([
            'direction' => ['required', Rule::enum(DocumentDirection::class)->only([DocumentDirection::Incoming, DocumentDirection::Outgoing])],
            'key' => ['required', 'string', 'max:200'],
        ]);
        try {
            $result = $this->parties->promote((int) $user->organization_id, DocumentDirection::from((string) $data['direction']), (string) $data['key'], $user);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }
        $message = (string) __('„:party“: :moved Eingänge umgehängt.', ['party' => (string) $result['party']->name, 'moved' => $result['moved']]);
        if ($result['left'] > 0) {
            $message .= ' ' . trans_choice(':count bereits übergebener Eingang bleibt am Sammelkontakt.|:count bereits übergebene Eingänge bleiben am Sammelkontakt.', $result['left'], ['count' => $result['left']]);
        }

        return redirect()->route('finance.incoming-invoices.rules')->with('success', $message);
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
}
