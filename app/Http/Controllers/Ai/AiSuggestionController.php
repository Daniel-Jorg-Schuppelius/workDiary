<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiSuggestionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Enums\Ai\AiMemoryEntryType;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiTextSuggestion;
use App\Models\{Customer, Invoice, InvoiceItem, Quote, QuoteItem};
use App\Models\Finance\BillingTransferPosition;
use App\Services\Ai\AiMemoryService;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\ItemTextSuggestionService;
use App\Support\Locales;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * KI-Leistungstexte an Belegen (Feature 084, MVP-402–405): Vorschläge
 * anfordern (einzeln, gesammelt, Übersetzung), übernehmen/verwerfen und
 * bestätigtes Lernen („Merken?"-Dialog → Beispielpaar/Glossar im
 * KI-Gedächtnis). Rechte: Beleg-Update-Policy + `ai.use`; Fehlerpfade
 * enden IMMER als Flash — der Beleg-Workflow hängt nie an der KI.
 */
class AiSuggestionController extends Controller {
    public function __construct(private readonly ItemTextSuggestionService $suggestions) {}

    public function invoiceItem(Invoice $invoice, InvoiceItem $item): RedirectResponse {
        $this->authorizeInvoice($invoice, $item);

        return $this->guarded(function () use ($invoice, $item): string {
            $this->suggestions->suggestForInvoiceItem($invoice, $item, Auth::user());

            return __('ai.flash.suggestion_created');
        });
    }

    public function invoiceAll(Invoice $invoice): RedirectResponse {
        $this->authorizeInvoice($invoice);

        return $this->guarded(function () use ($invoice): string {
            $count = $this->suggestions->queueAllForInvoice($invoice, Auth::user());

            return __('ai.flash.suggestions_queued', ['count' => $count]);
        });
    }

    /** Dialog: Zielsprache wählen (modal-first). */
    public function invoiceItemTranslateForm(Invoice $invoice, InvoiceItem $item): View {
        $this->authorizeInvoice($invoice, $item);

        return view('ai._translate_dialog', [
            'action' => route('ai.suggestions.invoice-item-translate', [$invoice, $item]),
        ]);
    }

    public function invoiceItemTranslate(Request $request, Invoice $invoice, InvoiceItem $item): RedirectResponse {
        $this->authorizeInvoice($invoice, $item);

        $data = $request->validate([
            'target_language' => ['required', 'string', Rule::in(Locales::enabledCodes())],
        ]);

        return $this->guarded(function () use ($invoice, $item, $data): string {
            $this->suggestions->translateInvoiceItem($invoice, $item, $data['target_language'], Auth::user());

            return __('ai.flash.suggestion_created');
        });
    }

    public function quoteItem(Quote $quote, QuoteItem $item): RedirectResponse {
        Gate::authorize('update', $quote);
        abort_unless(Gate::allows(Permission::AiUse->value), 403);
        abort_unless((int) $item->quote_id === (int) $quote->id, 404);

        return $this->guarded(function () use ($quote, $item): string {
            $this->suggestions->suggestForQuoteItem($quote, $item, Auth::user());

            return __('ai.flash.suggestion_created');
        });
    }

    public function accept(Request $request, AiTextSuggestion $suggestion): RedirectResponse {
        $this->authorizeSuggestion($suggestion);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        return $this->guarded(function () use ($suggestion, $data): string {
            $edited = $this->suggestions->accept($suggestion, Auth::user(), $data['text']);

            if ($edited) {
                // MVP-404: „Merken?"-Dialog — nie stilles Lernen.
                session()->flash('ai_learn', [
                    'source_text' => (string) $suggestion->original,
                    'content' => trim($data['text']),
                    'customer_id' => $this->customerIdFor($suggestion),
                    'capability' => $suggestion->capability,
                ]);
            }

            return __('ai.flash.suggestion_accepted');
        });
    }

    public function reject(AiTextSuggestion $suggestion): RedirectResponse {
        $this->authorizeSuggestion($suggestion);

        $this->suggestions->reject($suggestion, Auth::user());

        return back()->with('success', __('ai.flash.suggestion_rejected'));
    }

    /** Bestätigter Lernvorschlag aus dem „Merken?"-Dialog (MVP-404). */
    public function learn(Request $request, AiMemoryService $memory): RedirectResponse {
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        $data = $request->validate([
            'entry_type' => ['required', 'string', 'in:glossary,example'],
            'term' => ['nullable', 'string', 'max:120', 'required_if:entry_type,glossary'],
            'source_text' => ['nullable', 'string', 'max:2000', 'required_if:entry_type,example'],
            'content' => ['required', 'string', 'max:2000'],
            'customer_id' => ['nullable', 'integer'],
            'capability' => ['nullable', 'string', 'max:80'],
        ]);

        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        if ($customerId !== null && ! Customer::query()->whereKey($customerId)->exists()) {
            $customerId = null; // Mandantengrenze: fremde Kunden nie verknüpfen.
        }

        $memory->rememberLearned(app('currentOrganization'), Auth::user(), [
            'customer_id' => $customerId,
            'entry_type' => AiMemoryEntryType::from($data['entry_type']),
            'term' => $data['term'] ?? null,
            'content' => $data['content'],
            'source_text' => $data['source_text'] ?? null,
        ]);

        return back()->with('success', __('ai.flash.learned'));
    }

    private function guarded(callable $action): RedirectResponse {
        try {
            $message = $action();
        } catch (AiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function authorizeInvoice(Invoice $invoice, ?InvoiceItem $item = null): void {
        Gate::authorize('update', $invoice);
        abort_unless(Gate::allows(Permission::AiUse->value), 403);
        if ($item !== null) {
            abort_unless((int) $item->invoice_id === (int) $invoice->id, 404);
        }
    }

    private function authorizeSuggestion(AiTextSuggestion $suggestion): void {
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        $subject = $suggestion->subject;
        if ($subject instanceof InvoiceItem) {
            Gate::authorize('update', $subject->invoice);
        } elseif ($subject instanceof QuoteItem) {
            Gate::authorize('update', $subject->quote);
        } elseif ($subject instanceof BillingTransferPosition) {
            // Übergabe-Positionen (MVP-488): wer bestätigen darf, darf auch den
            // Text entscheiden.
            Gate::authorize('confirm', $subject->transfer);
        } else {
            abort(404);
        }
    }

    private function customerIdFor(AiTextSuggestion $suggestion): ?int {
        $subject = $suggestion->subject;

        $customerId = match (true) {
            $subject instanceof InvoiceItem => $subject->invoice?->customer_id,
            $subject instanceof QuoteItem => $subject->quote?->customer_id,
            $subject instanceof BillingTransferPosition => $subject->transfer?->customer_id,
            default => null,
        };

        return $customerId !== null ? (int) $customerId : null;
    }
}
