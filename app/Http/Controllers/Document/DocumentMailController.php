<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMailController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Enums\Billing\DeliveryNoteStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Sales\{OrderConfirmationStatus, QuoteStatus};
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\SplitsRecipientLists;
use App\Http\Controllers\Controller;
use App\Models\Billing\DeliveryNote;
use App\Models\Construction\ConstructionNotice;
use App\Models\Invoicing\InvoiceMailTemplate;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Project\Project;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Services\Document\DocumentMailService;
use App\Support\SqidEncoder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Generischer Belegversand (Feature 128, MVP-692): EIN Versanddialog für
 * Angebot, Auftragsbestätigung, Bestellung und Lieferschein — dünne
 * Einstiege je Belegart (Route-Model-Binding + Belegart-Guard), der
 * eigentliche Versand läuft über {@see DocumentMailService}.
 *
 * Beim Angebot bewusst GETRENNT vom Annahme-Token-Flow
 * ({@see QuoteController::send()}): quotes.send markiert den Status und
 * erzeugt das Portal-Token, quotes.mail verschickt das PDF per E-Mail.
 */
class DocumentMailController extends Controller {
    use SplitsRecipientLists;

    public function __construct(private readonly DocumentMailService $mailer) {}

    // ── Angebot ──────────────────────────────────────────────────────────

    public function quoteForm(Quote $quote): View {
        Gate::authorize('view', $quote);
        abort_unless($quote->status !== QuoteStatus::Draft, 422, (string) __('Entwürfe erst freigeben, dann versenden.'));

        return $this->form($quote, RenderDocumentKind::Quote, route('quotes.mail', $quote), __('Angebot :nr per E-Mail senden', ['nr' => $quote->number]));
    }

    public function quoteSend(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('view', $quote);
        abort_unless($quote->status !== QuoteStatus::Draft, 422, (string) __('Entwürfe erst freigeben, dann versenden.'));

        return $this->send($request, $quote, RenderDocumentKind::Quote, route('quotes.show', $quote));
    }

    // ── Auftragsbestätigung (eigener Beleg seit Phase 145, MVP-1162) ─────

    public function orderConfirmationForm(OrderConfirmation $orderConfirmation): View {
        Gate::authorize('view', $orderConfirmation);
        abort_if($orderConfirmation->status === OrderConfirmationStatus::Draft, 422, (string) __('quotes.order_confirmation.error.not_confirmed'));

        return $this->form($orderConfirmation, RenderDocumentKind::OrderConfirmation, route('order-confirmations.mail', $orderConfirmation), __('Auftragsbestätigung :nr per E-Mail senden', ['nr' => $orderConfirmation->number]));
    }

    public function orderConfirmationSend(Request $request, OrderConfirmation $orderConfirmation): RedirectResponse {
        Gate::authorize('view', $orderConfirmation);
        abort_if($orderConfirmation->status === OrderConfirmationStatus::Draft, 422, (string) __('quotes.order_confirmation.error.not_confirmed'));

        return $this->send($request, $orderConfirmation, RenderDocumentKind::OrderConfirmation, route('order-confirmations.show', $orderConfirmation));
    }

    // ── Bestellung ───────────────────────────────────────────────────────

    public function purchaseOrderForm(PurchaseOrder $purchaseOrder): View {
        Gate::authorize(P::InventoryPost->value);

        return $this->form($purchaseOrder, RenderDocumentKind::PurchaseOrder, route('purchase-orders.mail', $purchaseOrder), __('Bestellung :nr per E-Mail senden', ['nr' => $purchaseOrder->number]));
    }

    public function purchaseOrderSend(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);

        return $this->send($request, $purchaseOrder, RenderDocumentKind::PurchaseOrder, route('purchase-orders.show', $purchaseOrder));
    }

    // ── Lieferschein (Phase 145, MVP-1163) ────────────────────────────────

    public function deliveryNoteForm(DeliveryNote $deliveryNote): View {
        Gate::authorize('update', $deliveryNote);
        abort_if($deliveryNote->status !== DeliveryNoteStatus::Delivered, 422, (string) __('billing.delivery_note.error.not_delivered'));

        return $this->form($deliveryNote, RenderDocumentKind::DeliveryNote, route('delivery-notes.mail', $deliveryNote), __('Lieferschein :nr per E-Mail senden', ['nr' => $deliveryNote->number]));
    }

    public function deliveryNoteSend(Request $request, DeliveryNote $deliveryNote): RedirectResponse {
        Gate::authorize('update', $deliveryNote);
        abort_if($deliveryNote->status !== DeliveryNoteStatus::Delivered, 422, (string) __('billing.delivery_note.error.not_delivered'));

        return $this->send($request, $deliveryNote, RenderDocumentKind::DeliveryNote, route('delivery-notes.show', $deliveryNote));
    }

    // ── VOB/B-Schreiben (Feature 062, MVP-728) ───────────────────────────
    // Der Versand IST hier der Zweck: erst er erzeugt den Zugangsnachweis
    // (Dispatch-Log) und schreibt das Schreiben fest.

    public function constructionNoticeForm(ConstructionNotice $notice): View {
        Gate::authorize('viewAny', Project::class);

        return $this->form(
            $notice,
            $notice->kind,
            route('construction-notices.mail', $notice),
            __('construction.mail.title', ['label' => $notice->kind->label(), 'nr' => $notice->displayNo()]),
        );
    }

    public function constructionNoticeSend(Request $request, ConstructionNotice $notice): RedirectResponse {
        Gate::authorize('create', Project::class);

        // Festschreiben übernimmt der Belegversand-Anbieter des Baumoduls (afterSent).
        return $this->send($request, $notice, $notice->kind, route('construction-notices.show', $notice));
    }

    // ── Gemeinsame Mechanik ──────────────────────────────────────────────

    private function form(Model $document, RenderDocumentKind $kind, string $action, string $title): View {
        $templates = InvoiceMailTemplate::query()
            ->forKind($kind)
            ->where(function ($q) use ($document): void {
                $q->where('organization_id', $document->getAttribute('organization_id'))->orWhereNull('organization_id');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('documents._send_dialog', [
            'title' => $title,
            'eyebrow' => $kind->label(),
            'action' => $action,
            'templates' => $templates,
            'defaultTemplateId' => $templates->firstWhere('is_default', true)?->id,
            'defaultTo' => $this->mailer->defaultRecipient($document, $kind),
            'variables' => InvoiceMailTemplate::availableVariables($kind),
        ]);
    }

    private function send(Request $request, Model $document, RenderDocumentKind $kind, string $redirectTo): RedirectResponse {
        $this->splitRecipientLists($request);

        $data = $request->validate([
            'template_id' => ['nullable', 'string', 'max:64'],
            'to' => ['required', 'array', 'min:1', 'max:20'],
            'to.*' => ['required', 'email:rfc'],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['email:rfc'],
            'bcc' => ['nullable', 'array', 'max:20'],
            'bcc.*' => ['email:rfc'],
            'custom_text' => ['nullable', 'string', 'max:5000'],
            'bcc_sender' => ['nullable', 'boolean'],
        ]);

        $template = $this->resolveTemplate($data['template_id'] ?? null, $document, $kind);

        $this->mailer->send(
            $document,
            $kind,
            [
                'to' => array_values($data['to']),
                'cc' => array_values($data['cc'] ?? []),
                'bcc' => array_values($data['bcc'] ?? []),
            ],
            $template,
            isset($data['custom_text']) ? (string) $data['custom_text'] : null,
            ! empty($data['bcc_sender']),
        );

        return redirect($redirectTo)->with('status', __(':label an :count Empfänger versendet.', [
            'label' => $kind->label(),
            'count' => count($data['to']),
        ]));
    }

    /**
     * Vorlage aus dem Sqid auflösen: muss zur Belegart passen und global
     * oder org-eigen sein — sonst 422/403. Leer = Default der Belegart.
     */
    private function resolveTemplate(?string $sqid, Model $document, RenderDocumentKind $kind): ?InvoiceMailTemplate {
        if ($sqid === null || $sqid === '') {
            return null;
        }

        $id = app(SqidEncoder::class)->decode(InvoiceMailTemplate::class, $sqid);
        $template = $id !== null ? InvoiceMailTemplate::query()->find($id) : null;
        abort_unless($template !== null && $template->document_kind === $kind->value, 422, (string) __('Vorlage passt nicht zur Belegart.'));
        if ($template->organization_id !== null && $template->organization_id !== (int) $document->getAttribute('organization_id')) {
            abort(403);
        }

        return $template;
    }

}
