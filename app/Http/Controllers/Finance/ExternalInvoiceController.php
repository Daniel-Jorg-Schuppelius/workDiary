<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ExternalPackageKind;
use App\Http\Controllers\Concerns\SplitsRecipientLists;
use App\Http\Controllers\Controller;
use App\Models\Document\DocumentDispatch;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition, ExternalInvoice, ExternalInvoicePackage, ExternalInvoicePosition};
use App\Models\Invoicing\InvoiceMailTemplate;
use App\Services\Finance\ExternalInvoices\{ExternalInvoiceDispatchService, ExternalInvoicePackager, ExternalInvoiceProviders, ExternalInvoiceService};
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Extern ausgestellte Rechnungen (Feature 196, Nachtrag 07.11.2026): Stand und
 * Original abrufen, Übergabepositionen zuordnen, Abweichungen bestätigen,
 * mit Nachweisen zustellen. Keine lokale Rechnung, keine Nummernvergabe.
 */
class ExternalInvoiceController extends Controller {
    use SplitsRecipientLists;

    public function __construct(
        private readonly ExternalInvoiceService $invoices,
        private readonly ExternalInvoiceDispatchService $dispatches,
    ) {}

    public function show(ExternalInvoice $externalInvoice): View {
        Gate::authorize('view', $externalInvoice);
        $externalInvoice->load(['customer', 'organization', 'reconciler', 'positionLinks.transfer', 'positionLinks.position']);
        $packages = $externalInvoice->packages()->with(['dispatches', 'creator'])->get();

        return view('finance.external-invoices.show', [
            'invoice' => $externalInvoice,
            'provider' => app(ExternalInvoiceProviders::class)->for($externalInvoice->plugin_id),
            'reconciliation' => $this->invoices->reconciliation($externalInvoice),
            'route' => $this->dispatches->route($externalInvoice),
            'packages' => $packages,
            'dispatches' => $this->dispatches,
            'canUpdate' => Gate::allows('update', $externalInvoice),
            'canSend' => Gate::allows('send', $externalInvoice),
            'preflight' => $this->dispatches->preflight($externalInvoice, DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial),
        ]);
    }

    /** Rechnung zum Übergabenachweis abrufen; beim ersten Mal gehören alle Positionen dazu. */
    public function storeForTransfer(BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('markTransferred', $transfer);

        $invoice = $this->invoices->syncForTransfer($transfer, $this->authUser());

        return redirect()->route('finance.external-invoices.show', $invoice)->with('status', __('finance.external_invoice.status.synced'));
    }

    public function linkForm(BillingTransfer $transfer): View {
        Gate::authorize('markTransferred', $transfer);
        $provider = app(ExternalInvoiceProviders::class)->forTransfer($transfer);
        $free = $this->invoices->freePositionIds($transfer);

        return view('finance.external-invoices._link_dialog', [
            'transfer' => $transfer,
            'provider' => $provider,
            'candidates' => $provider?->candidates((int) $transfer->organization_id, (int) $transfer->customer_id) ?? [],
            'positions' => $transfer->positions()->whereIn('id', $free)->get(),
        ]);
    }

    /** Geteilte, gebündelte oder direkt extern angelegte Rechnung zuordnen. */
    public function link(Request $request, BillingTransfer $transfer): RedirectResponse {
        Gate::authorize('markTransferred', $transfer);
        $data = $request->validate([
            'external_id' => ['nullable', 'string', 'max:128'],
            'external_candidate' => ['nullable', 'string', 'max:128'],
            'positions' => ['required', 'array', 'min:1', 'max:500'],
            'positions.*' => ['required', 'string', 'max:64'],
        ]);
        $externalId = trim((string) ($data['external_id'] ?? '')) !== '' ? (string) $data['external_id'] : (string) ($data['external_candidate'] ?? '');
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(BillingTransferPosition::class, $sqid), $data['positions'])));

        $invoice = $this->invoices->link($transfer, $externalId, $ids, $this->authUser());

        return redirect()->route('finance.external-invoices.show', $invoice)->with('status', __('finance.external_invoice.status.linked'));
    }

    public function refresh(ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);

        $this->invoices->refresh($externalInvoice, $this->authUser());

        return $this->back($externalInvoice, 'finance.external_invoice.status.synced');
    }

    public function refetchOriginal(ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);

        $this->invoices->refetchOriginal($externalInvoice, $this->authUser());

        return $this->back($externalInvoice, 'finance.external_invoice.status.original');
    }

    public function unassign(ExternalInvoice $externalInvoice, BillingTransferPosition $position): RedirectResponse {
        Gate::authorize('update', $externalInvoice);
        $link = ExternalInvoicePosition::query()->where('external_invoice_id', $externalInvoice->id)->where('billing_transfer_position_id', $position->id)->firstOrFail();

        $this->invoices->unassign($externalInvoice, $link);

        return $this->back($externalInvoice, 'finance.external_invoice.status.unassigned');
    }

    public function reconcile(Request $request, ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $this->invoices->confirmReconciliation($externalInvoice, $this->authUser(), $data['note']);

        return $this->back($externalInvoice, 'finance.external_invoice.status.reconciled');
    }

    public function sendForm(Request $request, ExternalInvoice $externalInvoice): View {
        Gate::authorize('send', $externalInvoice);
        $externalInvoice->load(['customer', 'organization']);
        $kind = ExternalPackageKind::tryFrom((string) $request->query('kind')) ?? ExternalPackageKind::Initial;

        return view('finance.external-invoices._send_dialog', [
            'invoice' => $externalInvoice,
            'kind' => $kind,
            'templates' => InvoiceMailTemplate::query()
                ->forKind(\App\Enums\DocumentDesign\RenderDocumentKind::Invoice)
                ->where(fn ($q) => $q->where('organization_id', $externalInvoice->organization_id)->orWhereNull('organization_id'))
                ->orderByDesc('is_default')->orderBy('name')->get(),
            'defaultTemplateId' => InvoiceMailTemplate::defaultFor((int) $externalInvoice->organization_id)->id,
            'defaultTo' => $externalInvoice->customer === null ? '' : ($externalInvoice->customer->primaryContact()['email'] ?? $externalInvoice->customer->email ?? ''),
            'checks' => [
                DocumentDispatch::CHANNEL_EMAIL => $this->dispatches->preflight($externalInvoice, DocumentDispatch::CHANNEL_EMAIL, $kind, ['provider_not_sent' => true, 'redelivery' => true]),
                DocumentDispatch::CHANNEL_PEPPOL => $this->dispatches->preflight($externalInvoice, DocumentDispatch::CHANNEL_PEPPOL, $kind, ['provider_not_sent' => true, 'redelivery' => true]),
            ],
            'providerSendDisabled' => (bool) app(\App\Services\Invoicing\EInvoice\RecipientProfiles::class)->setting('einvoice.provider_send_disabled', $externalInvoice->organization),
        ]);
    }

    public function send(Request $request, ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('send', $externalInvoice);
        $rawTemplate = $request->input('template_id');
        if (is_string($rawTemplate) && $rawTemplate !== '' && ! ctype_digit($rawTemplate)) {
            $request->merge(['template_id' => app(\App\Support\SqidEncoder::class)->decode(InvoiceMailTemplate::class, $rawTemplate)]);
        }
        $this->splitRecipientLists($request);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(ExternalPackageKind::class)],
            'dispatch_channel' => ['required', Rule::in([DocumentDispatch::CHANNEL_EMAIL, DocumentDispatch::CHANNEL_PEPPOL])],
            'template_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('invoice_mail_templates')],
            'to' => ['required_if:dispatch_channel,' . DocumentDispatch::CHANNEL_EMAIL, 'array', 'max:20'],
            'to.*' => ['required', 'email:rfc'],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['email:rfc'],
            'bcc' => ['nullable', 'array', 'max:20'],
            'bcc.*' => ['email:rfc'],
            'custom_text' => ['nullable', 'string', 'max:5000'],
            'confirm_provider_not_sent' => ['nullable', 'boolean'],
            'confirm_redelivery' => ['nullable', 'boolean'],
        ]);
        if (isset($data['template_id'])) {
            $template = InvoiceMailTemplate::query()->whereKey($data['template_id'])->firstOrFail();
            abort_unless($template->organization_id === null || $template->organization_id === $externalInvoice->organization_id, 403);
            abort_unless($template->document_kind === \App\Enums\DocumentDesign\RenderDocumentKind::Invoice->value, 422);
        }

        $this->dispatches->dispatch(
            $externalInvoice,
            $data['dispatch_channel'],
            ExternalPackageKind::from($data['kind']),
            [
                'to' => array_values($data['to'] ?? []),
                'cc' => array_values($data['cc'] ?? []),
                'bcc' => array_values($data['bcc'] ?? []),
                'template_id' => isset($data['template_id']) ? (int) $data['template_id'] : null,
                'custom_text' => $data['custom_text'] ?? null,
            ],
            $this->authUser(),
            [
                'provider_not_sent' => (bool) ($data['confirm_provider_not_sent'] ?? false),
                'redelivery' => (bool) ($data['confirm_redelivery'] ?? false),
            ],
        );

        return $this->back($externalInvoice, 'finance.external_invoice.status.sent');
    }

    public function retry(ExternalInvoicePackage $package): RedirectResponse {
        $invoice = $package->externalInvoice;
        Gate::authorize('send', $invoice);

        $this->dispatches->retry($package, $this->authUser());

        return $this->back($invoice, 'finance.external_invoice.status.sent');
    }

    public function redeliver(Request $request, ExternalInvoicePackage $package): RedirectResponse {
        $invoice = $package->externalInvoice;
        Gate::authorize('send', $invoice);
        $request->validate(['confirm' => ['accepted']]);

        $this->dispatches->redeliver($package, $this->authUser());

        return $this->back($invoice, 'finance.external_invoice.status.sent');
    }

    public function resolve(Request $request, ExternalInvoicePackage $package): RedirectResponse {
        $invoice = $package->externalInvoice;
        Gate::authorize('send', $invoice);
        $data = $request->validate(['delivered' => ['required', 'boolean']]);

        $this->dispatches->resolve($package, (bool) $data['delivered'], $this->authUser());

        return $this->back($invoice, 'finance.external_invoice.status.resolved');
    }

    public function original(ExternalInvoice $externalInvoice): Response {
        Gate::authorize('view', $externalInvoice);
        $content = $this->invoices->originalContent($externalInvoice);
        abort_if($content === null || $externalInvoice->original_format === null, 404);
        $name = sprintf('original-%s.%s', \Illuminate\Support\Str::slug($externalInvoice->supportingReferenceNumber()), $externalInvoice->original_format->extension());

        return response($content, 200, [
            'Content-Type' => $externalInvoice->original_format->mime(),
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function packageFile(ExternalInvoicePackage $package, int $index): Response {
        Gate::authorize('view', $package->externalInvoice);
        $files = app(ExternalInvoicePackager::class)->files($package);
        abort_unless(isset($files[$index]), 404);
        $file = $files[$index];

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $file['name']) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function back(ExternalInvoice $invoice, string $message): RedirectResponse {
        return redirect()->route('finance.external-invoices.show', $invoice)->with('status', __($message));
    }
}
