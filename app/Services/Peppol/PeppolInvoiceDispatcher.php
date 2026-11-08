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

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Invoicing\Invoice;
use App\Plugins\Contracts\PeppolTransportProvider;
use App\Plugins\PluginManager;
use App\Services\Billing\BillingModeResolver;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use Closure;
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
    /** Empfehlung des Bundes für Peppol-Nachrichten an die OZG-RE. */
    public const MAX_PAYLOAD_BYTES = 100 * 1024 * 1024;

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
            && in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true)
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
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true)) {
            throw new RuntimeException((string) __('peppol.error.not_issued'));
        }

        // Erst nach den Transportprüfungen erzeugen: ohne Access Point bleibt die Meldung „nicht eingerichtet“.
        $dispatch = $this->sendUbl($organizationId, $customer, function () use ($invoice): string {
            try {
                // Peppol BIS Billing 3.0 ist UBL — unabhängig vom Zustellformat (MVP-805).
                return $this->generator->generate($invoice, \App\Enums\Invoicing\XRechnungSyntax::Ubl);
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
        }, [
            'invoice_id' => $invoice->id,
            'document_kind' => RenderDocumentKind::Invoice->value,
            'document_id' => $invoice->id,
            'format' => 'xrechnung_ubl',
            'limit' => app(\App\Services\Invoicing\EInvoice\RecipientProfiles::class)->limitBytes($invoice->customer, $invoice->organization),
        ]);
        $receipt = $dispatch->meta ?? [];

        $invoice->audit('invoice.peppolSent', [
            'participant' => $dispatch->recipient,
            'message_id' => $receipt['message_id'] ?? null,
            'transport_status' => $receipt['transport_status'] ?? null,
            'dispatch_id' => $dispatch->id,
        ]);

        return $dispatch;
    }

    /**
     * Fertige, im Haus erzeugte oder ergänzte UBL über Peppol senden (Feature 196:
     * auch extern ausgestellte Rechnungen). Prüfung, Auflösung, Umschlag und
     * Zustellnachweis wie beim Rechnungsversand.
     *
     * @param  array{invoice_id?: int|null, document_kind: string, document_id: int, format: string, limit: int}  $target
     *
     * @throws RuntimeException mit einer für die Oberfläche gedachten Meldung
     */
    public function sendUbl(int $organizationId, Customer $customer, Closure|string $ubl, array $target): DocumentDispatch {
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
        $ubl = $ubl instanceof Closure ? $ubl() : $ubl;

        // Peppol kennt keine Obergrenze; der Bund empfiehlt höchstens 100 MB, der Empfänger setzt die eigene (Feature 196).
        $limit = min(self::MAX_PAYLOAD_BYTES, $target['limit']);
        if (strlen($ubl) > $limit) {
            throw new RuntimeException((string) __('peppol.error.too_large', [
                'size' => \CommonToolkit\Helper\Data\NumberHelper::formatBytes(strlen($ubl), 1),
                'limit' => \CommonToolkit\Helper\Data\NumberHelper::formatBytes($limit, 0),
            ]));
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

        $sbdh = Sbdh::forUbl($ubl, $sender, $receiver, $plugin->peppolSenderCountry($organizationId));
        $envelope = $sbdh->envelope($ubl);

        try {
            $receipt = $client->send($envelope);
        } catch (RuntimeException $e) {
            $this->record($organizationId, $target, $receiver->canonical(), $ubl, null, [
                'instance_identifier' => $sbdh->getInstanceIdentifier(),
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ], DocumentDispatchStatus::Failed);

            throw new RuntimeException((string) __('peppol.error.transport', ['message' => $e->getMessage()]), 0, $e);
        }

        $dispatch = $this->record($organizationId, $target, $receiver->canonical(), $ubl, $receipt, [
            'instance_identifier' => $sbdh->getInstanceIdentifier(),
            'document_type' => $documentType->canonical(),
            'validator_scenario' => $validation->getScenarioName() ?? BisValidator::SCENARIO,
        ], $receipt->isSuccess() ? DocumentDispatchStatus::Sent : ($receipt->getStatus()->isFinal() ? DocumentDispatchStatus::Failed : DocumentDispatchStatus::Queued));

        // Endgültig abgelehnt: der Versuch steht im Protokoll, der Aufrufer meldet den Fehler statt eines Versands.
        if (! $receipt->isSuccess() && $receipt->isFinal()) {
            throw new RuntimeException((string) __('peppol.error.transport', [
                'message' => $receipt->getErrorMessage() ?? $receipt->getStatus()->value,
            ]));
        }

        return $dispatch;
    }

    /** Das aktivierte Provider-Plugin (null = keins aktiv). */
    private function plugin(): ?PeppolTransportProvider {
        $plugin = app(PluginManager::class)->implementing(PeppolTransportProvider::class)->first();

        return $plugin instanceof PeppolTransportProvider ? $plugin : null;
    }

    /**
     * @param  array{invoice_id?: int|null, document_kind: string, document_id: int, format: string, limit: int}  $target
     * @param  array<string, mixed>  $meta
     */
    private function record(int $organizationId, array $target, string $recipient, string $ubl, ?TransportReceipt $receipt, array $meta, DocumentDispatchStatus $status): DocumentDispatch {
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
            'organization_id' => $organizationId,
            'invoice_id' => $target['invoice_id'] ?? null,
            'document_kind' => $target['document_kind'],
            'document_id' => $target['document_id'],
            'channel' => DocumentDispatch::CHANNEL_PEPPOL,
            'format' => $target['format'],
            'status' => $status,
            'recipient' => $recipient,
            'sha256' => CryptoHelper::hash($ubl),
            'meta' => array_filter($meta, static fn ($value): bool => $value !== null && $value !== ''),
            'created_by' => Auth::id(),
        ]);
    }
}
