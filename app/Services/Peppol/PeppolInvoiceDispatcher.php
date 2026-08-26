<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolInvoiceDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Peppol;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\{DocumentDispatch, Invoice};
use App\Plugins\Contracts\PeppolTransportProvider;
use App\Plugins\PeppolAccessPoint\PeppolAccessPointConfig;
use App\Plugins\PluginManager;
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use CommonToolkit\Helper\Data\CryptoHelper;
use ERechnungToolkit\Contracts\{AccessPointClientInterface, ValidatorInterface};
use ERechnungToolkit\Peppol\{BisValidator, DocumentTypeId, Sbdh, TransportReceipt};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Rechnungsversand über Peppol (Feature 066, MVP-734).
 *
 * Reihenfolge ist Absicht — jeder Schritt kann den Versand verhindern, und
 * zwar bevor irgendetwas das Haus verlässt:
 *
 * 1. **Hoheit und Reife:** externer Fakturierungsweg, Pro-forma oder Entwurf ⇒
 *    kein Peppol-Versand (dieselbe Grenze wie beim XRechnungs-Download).
 * 2. **Erzeugen:** die bestehende XRechnung (UBL) — kein zweiter Generator.
 * 3. **Prüfen:** {@see BisValidator} VOR dem Versand. Der Validator deckt eine
 *    **Teilmenge** der Peppol-BIS-Regeln ab; das steht so in der Meldung, damit
 *    ein grüner Lauf nicht als Konformitätsnachweis missverstanden wird.
 * 4. **Auflösen:** Empfänger registriert und formatfähig? Ergebnis kommt aus
 *    dem Zwischenspeicher ({@see PeppolParticipantService}).
 * 5. **Umschlagen:** {@see Sbdh::forUbl()} — Absender, Empfänger, Dokumenttyp
 *    und Prozess stammen aus dem Dokument, nicht aus Vermutungen.
 * 6. **Senden und nachweisen:** die {@see TransportReceipt} des Access Points
 *    landet als Zugangsnachweis am {@see DocumentDispatch}. Sie belegt den
 *    Transport, NICHT die fachliche Annahme — der Status bleibt getrennt.
 *
 * Ein abgelehnter Versand schreibt trotzdem einen Dispatch (`failed`): der
 * Zustellversuch hat stattgefunden und gehört ins Protokoll.
 */
class PeppolInvoiceDispatcher {
    public function __construct(
        private readonly PeppolParticipantService $participants,
        private readonly XRechnungGenerator $generator,
        private readonly BillingModeResolver $billingModes,
        // Kontextuell gebunden im PeppolAccessPointServiceProvider; als
        // Interface, damit ein Schematron-/KoSIT-Validator später ohne
        // Codeänderung an dieselbe Stelle treten kann.
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Ist der Peppol-Weg für diese Rechnung überhaupt anbietbar? Steuert die
     * Sichtbarkeit der Aktion — die harte Prüfung macht {@see send()}.
     */
    public function isOfferable(Invoice $invoice): bool {
        $invoice->loadMissing('customer');

        $organizationId = (int) $invoice->organization_id;

        return $this->plugin()?->peppolAccessPoint($organizationId) !== null
            && PeppolParticipantService::forCustomer($invoice->customer) !== null
            && in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID], true)
            && ! $invoice->isProforma()
            && ! $this->billingModes->effectiveFor($invoice->customer)->isExternal();
    }

    /**
     * @throws RuntimeException mit einer für die Oberfläche gedachten Meldung
     */
    public function send(Invoice $invoice): DocumentDispatch {
        $invoice->loadMissing(['customer', 'items']);
        $organizationId = (int) $invoice->organization_id;

        $customer = $invoice->customer;
        if ($this->billingModes->effectiveFor($customer)->isExternal()) {
            throw new RuntimeException((string) __('peppol.error.external_billing'));
        }
        if ($invoice->isProforma()) {
            throw new RuntimeException((string) __('peppol.error.proforma'));
        }
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID], true)) {
            throw new RuntimeException((string) __('peppol.error.not_issued'));
        }

        $plugin = $this->plugin();
        $client = $plugin?->peppolAccessPoint($organizationId);
        if ($plugin === null || ! $client instanceof AccessPointClientInterface) {
            throw new RuntimeException((string) __('peppol.error.not_configured'));
        }

        $sender = PeppolParticipantService::parse($plugin->peppolSenderId($organizationId));
        if ($sender === null) {
            throw new RuntimeException((string) __('peppol.error.sender_invalid'));
        }

        $receiver = PeppolParticipantService::forCustomer($customer);
        if ($receiver === null) {
            $raw = trim((string) $customer->peppol_participant_id);
            throw new RuntimeException((string) ($raw === ''
                ? __('peppol.error.no_participant', ['customer' => (string) $customer->name])
                : __('peppol.error.invalid_participant', ['customer' => (string) $customer->name, 'value' => $raw])));
        }

        try {
            $ubl = $this->generator->generate($invoice);
        } catch (ValidationException $e) {
            // Preflight des Generators: fachliche Pflichtfelder fehlen. Nach
            // außen bleibt es EINE Fehlerart — der Aufrufer soll nicht zwei
            // Ausnahmetypen unterscheiden müssen.
            throw new RuntimeException(
                (string) __('invoicing.einvoice.error_intro') . ' ' . implode(' ', $e->validator->errors()->all()),
                0,
                $e,
            );
        }

        $validation = $this->validator->validate($ubl);
        if ($validation->hasErrors()) {
            throw new RuntimeException(
                (string) __('peppol.error.validation', ['messages' => implode(' ', array_map('strval', $validation->getErrors()))])
                . ' ' . (string) __('peppol.validator.scope', ['scenario' => $validation->getScenarioName() ?? BisValidator::SCENARIO]),
            );
        }

        $documentType = DocumentTypeId::fromUbl($ubl);

        try {
            $lookup = $this->participants->lookup($organizationId, $receiver);
        } catch (RuntimeException $e) {
            throw new RuntimeException((string) __('peppol.error.lookup_failed', ['message' => $e->getMessage()]), 0, $e);
        }

        if (! $lookup->registered) {
            throw new RuntimeException((string) __('peppol.error.not_registered', ['participant' => $receiver->canonical()]));
        }
        if (! $this->participants->accepts($lookup, $documentType)) {
            throw new RuntimeException((string) __('peppol.error.unsupported_document', [
                'participant' => $receiver->canonical(),
                'document' => $documentType->getCustomizationId(),
            ]));
        }

        $config = PeppolAccessPointConfig::resolve($organizationId);
        $sbdh = Sbdh::forUbl($ubl, $sender, $receiver, $config['sender_country']);
        $envelope = $sbdh->envelope($ubl);

        try {
            $receipt = $client->send($envelope);
        } catch (RuntimeException $e) {
            $this->record($invoice, $receiver->canonical(), $ubl, null, [
                'instance_identifier' => $sbdh->getInstanceIdentifier(),
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ], 'failed');

            throw new RuntimeException((string) __('peppol.error.transport', ['message' => $e->getMessage()]), 0, $e);
        }

        $dispatch = $this->record($invoice, $receiver->canonical(), $ubl, $receipt, [
            'instance_identifier' => $sbdh->getInstanceIdentifier(),
            'document_type' => $documentType->canonical(),
            'validator_scenario' => $validation->getScenarioName() ?? BisValidator::SCENARIO,
        ], $receipt->isSuccess() ? 'sent' : ($receipt->getStatus()->isFinal() ? 'failed' : 'queued'));

        $invoice->audit('invoice.peppolSent', [
            'participant' => $receiver->canonical(),
            'message_id' => $receipt->getMessageId(),
            'transport_status' => $receipt->getStatus()->value,
            'dispatch_id' => $dispatch->id,
        ]);

        return $dispatch;
    }

    /** Das aktivierte Provider-Plugin (null = keins aktiv). */
    private function plugin(): ?PeppolTransportProvider {
        $plugin = app(PluginManager::class)->implementing(PeppolTransportProvider::class)->first();

        return $plugin instanceof PeppolTransportProvider ? $plugin : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(Invoice $invoice, string $recipient, string $ubl, ?TransportReceipt $receipt, array $meta, string $status): DocumentDispatch {
        if ($receipt !== null) {
            $meta += [
                'message_id' => $receipt->getMessageId(),
                'transport_status' => $receipt->getStatus()->value,
                'receipt_at' => $receipt->getTimestamp()->format(DATE_ATOM),
                'receiver_access_point' => $receipt->getReceiverAccessPoint(),
                'error_code' => $receipt->getErrorCode(),
                'error_message' => $receipt->getErrorMessage(),
            ];
        }

        return DocumentDispatch::query()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'document_kind' => RenderDocumentKind::Invoice->value,
            'document_id' => $invoice->id,
            'channel' => DocumentDispatch::CHANNEL_PEPPOL,
            'format' => 'xrechnung_ubl',
            'status' => $status,
            'recipient' => $recipient,
            'sha256' => CryptoHelper::hash($ubl),
            'meta' => array_filter($meta, static fn ($value): bool => $value !== null && $value !== ''),
            'created_by' => Auth::id(),
        ]);
    }
}
