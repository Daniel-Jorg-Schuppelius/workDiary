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

namespace App\Http\Controllers;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\User\Permission as P;
use App\Models\Construction\ConstructionNotice;
use App\Models\{InvoiceMailTemplate, ManufacturingOrder, Project, PurchaseOrder, Quote, StockDelivery};
use App\Services\Construction\ConstructionNoticeService;
use App\Services\Document\DocumentMailService;
use App\Services\SqidEncoder;
use Illuminate\Contracts\View\View;
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
    public function __construct(private readonly DocumentMailService $mailer) {}

    // ── Angebot ──────────────────────────────────────────────────────────

    public function quoteForm(Quote $quote): View {
        Gate::authorize('view', $quote);
        abort_unless($quote->status !== 'draft', 422, (string) __('Entwürfe erst freigeben, dann versenden.'));

        return $this->form($quote, RenderDocumentKind::Quote, route('quotes.mail', $quote), __('Angebot :nr per E-Mail senden', ['nr' => $quote->number]));
    }

    public function quoteSend(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('view', $quote);
        abort_unless($quote->status !== 'draft', 422, (string) __('Entwürfe erst freigeben, dann versenden.'));

        return $this->send($request, $quote, RenderDocumentKind::Quote, route('quotes.show', $quote));
    }

    // ── Auftragsbestätigung (zum angenommenen Angebot) ───────────────────

    public function orderConfirmationForm(Quote $quote): View {
        Gate::authorize('view', $quote);
        $this->assertAccepted($quote);

        return $this->form($quote, RenderDocumentKind::OrderConfirmation, route('quotes.order-confirmation.mail', $quote), __('Auftragsbestätigung :nr per E-Mail senden', ['nr' => $quote->number]));
    }

    public function orderConfirmationSend(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('view', $quote);
        $this->assertAccepted($quote);

        return $this->send($request, $quote, RenderDocumentKind::OrderConfirmation, route('quotes.show', $quote));
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

    // ── Lieferschein (Auslieferung) ──────────────────────────────────────

    public function deliveryNoteForm(ManufacturingOrder $order, StockDelivery $delivery): View {
        Gate::authorize('view', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        return $this->form($delivery, RenderDocumentKind::DeliveryNote, route('manufacturing-orders.deliveries.mail', [$order, $delivery]), __('Lieferschein :nr per E-Mail senden', ['nr' => app(\App\Services\Manufacturing\DeliveryNotePdfRenderer::class)->number($delivery)]));
    }

    public function deliveryNoteSend(Request $request, ManufacturingOrder $order, StockDelivery $delivery): RedirectResponse {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        return $this->send($request, $delivery, RenderDocumentKind::DeliveryNote, route('manufacturing-orders.show', $order));
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

        $response = $this->send($request, $notice, $notice->kind, route('construction-notices.show', $notice));
        app(ConstructionNoticeService::class)->markSent($notice);

        return $response;
    }

    // ── Gemeinsame Mechanik ──────────────────────────────────────────────

    private function form(Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind, string $action, string $title): View {
        $templates = InvoiceMailTemplate::query()
            ->forKind($kind)
            ->where(function ($q) use ($document): void {
                $q->where('organization_id', $document->organization_id)->orWhereNull('organization_id');
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

    private function send(Request $request, Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind, string $redirectTo): RedirectResponse {
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
    private function resolveTemplate(?string $sqid, Quote|PurchaseOrder|StockDelivery|ConstructionNotice $document, RenderDocumentKind $kind): ?InvoiceMailTemplate {
        if ($sqid === null || $sqid === '') {
            return null;
        }

        $id = app(SqidEncoder::class)->decode(InvoiceMailTemplate::class, $sqid);
        $template = $id !== null ? InvoiceMailTemplate::query()->find($id) : null;
        abort_unless($template !== null && $template->document_kind === $kind->value, 422, (string) __('Vorlage passt nicht zur Belegart.'));
        if ($template->organization_id !== null && $template->organization_id !== $document->organization_id) {
            abort(403);
        }

        return $template;
    }

    private function assertAccepted(Quote $quote): void {
        abort_unless(in_array($quote->status, ['accepted', 'partially_accepted'], true), 422, (string) __('Nur angenommene Angebote können bestätigt werden.'));
    }
}
