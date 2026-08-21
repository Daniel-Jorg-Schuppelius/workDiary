<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceRetentionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Invoicing\{RetentionBase, RetentionKind};
use App\Models\{Invoice, User};
use App\Models\Invoicing\InvoiceRetention;
use App\Services\Invoicing\RetentionService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Sicherheitseinbehalte am Beleg (Feature 113, MVP-602).
 *
 * Anlegen nur am Entwurf (`update`-Policy erzwingt das ohnehin), Freigeben
 * dagegen am ausgestellten Beleg — die Freigabe ist Lifecycle, kein
 * Beleginhalt.
 */
class InvoiceRetentionController extends Controller {
    public function __construct(private readonly RetentionService $retentions) {}

    public function dialog(Invoice $invoice): View {
        Gate::authorize('update', $invoice);

        return view('invoices._retention_dialog', ['invoice' => $invoice]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'kind' => ['required', Rule::enum(RetentionKind::class)],
            'basis' => ['required', 'in:percent,amount'],
            'base_kind' => ['nullable', Rule::enum(RetentionBase::class)],
            'percent' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:basis,percent'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:basis,amount'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->retentions->add(
                $invoice,
                RetentionKind::from((string) $data['kind']),
                $data['basis'] === 'percent' ? (float) $data['percent'] : null,
                $data['basis'] === 'amount' ? (float) $data['amount'] : null,
                $data['due_on'] ?? null,
                $user,
                $data['note'] ?? null,
                RetentionBase::tryFrom((string) ($data['base_kind'] ?? '')) ?? RetentionBase::Net,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', __('invoicing.retention.added'));
    }

    public function release(Request $request, Invoice $invoice, InvoiceRetention $retention): RedirectResponse {
        // Freigeben ist Lifecycle: Die update-Policy (nur Entwurf) passt nicht,
        // Maßstab ist das Abrechnungsrecht — wie beim Mahnen.
        abort_unless($request->user()?->canManageBilling() ?? false, 403);
        abort_unless((int) $retention->invoice_id === (int) $invoice->id, 404);

        try {
            $this->retentions->release($retention, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('invoicing.retention.released'));
    }
}
