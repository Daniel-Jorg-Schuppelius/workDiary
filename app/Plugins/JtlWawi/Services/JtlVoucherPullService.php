<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Models\JtlConnection;
use App\Plugins\JtlWawi\Api\{JtlApiException, JtlGatewayFactory};
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror, VoucherPuller};

/**
 * Beleg-Rückabruf aus JTL-Wawi (Feature 122/078, MVP-731 — Vollscan G18).
 *
 * JTL-Wawi ist in workDiary die **Warenwirtschaft**, nicht das Fakturasystem
 * (Feature 078: „Aufträge/Faktura/Kunden — keines über dieses Plugin"). Der
 * Beleg-Pull ändert daran nichts: er spiegelt nur, was JTL bereits fakturiert
 * hat, damit der Belegfluss keine Lücke hat. Geschrieben wird nach JTL
 * weiterhin ausschließlich Bestand.
 *
 * - **Endpunkt** `GET /v2/salesinvoices` mit dem JTL-Standardumschlag
 *   (`items[]`, `hasNextPage`) und `pageNumber`/`pageSize`.
 * - **Inkrement** über `createdSince` (die v2-Ressourcen der Faktura-Domäne
 *   kennen kein `changedSince` — Feature 078, Abweichungsregister).
 * - **Feature-Detection statt Raten**: Die Faktura-Domäne der v2.0-OpenAPI
 *   ist kommandolastig; ob eine Instanz die Rechnungsliste anbietet, hängt an
 *   ihrem API-Stand. Antwortet JTL mit 404/405, endet der Lauf mit
 *   `unsupported` — es wird KEINE andere Ressource ersatzweise als Rechnung
 *   ausgegeben (dieselbe Linie wie bei der Artikel-Liste v2.0/v2.1).
 * - **Storno-Semantik**: JTL storniert eine Rechnung durch eine
 *   **Gutschrift/Storno-Rechnung** mit negativem Betrag und Verweis auf die
 *   Ursprungsrechnung. Erkannt wird das an einem gesetzten Storno-Kennzeichen
 *   ODER an einem negativen Bruttobetrag — beides führt zu
 *   `is_cancellation` + `document_kind = cancellation`.
 *
 * **Pilot-Vorbehalt:** Die genaue Feldschreibweise der Rechnungsressource ist
 * ohne echte Mandanteninstanz nicht abnehmbar. Der Mapper liest deshalb
 * mehrere dokumentierte Schreibweisen tolerant und legt die Rohantwort als
 * Nachweis in `payload` ab; die Abnahme steht in Feature 122 als Pilotpunkt.
 */
class JtlVoucherPullService implements VoucherPuller {
    /** Feldkandidaten je Zielwert (erster gefundener gewinnt). */
    private const FIELDS = [
        'id' => ['id', 'salesInvoiceId', 'invoiceId'],
        'number' => ['invoiceNumber', 'number', 'salesInvoiceNumber'],
        'date' => ['creationDate', 'invoiceDate', 'createdDate'],
        'due' => ['dueDate', 'paymentDueDate'],
        'paid' => ['paymentDate', 'paidDate'],
        'gross' => ['grossAmount', 'totalGross', 'amountGross'],
        'net' => ['netAmount', 'totalNet', 'amountNet'],
        'currency' => ['currencyIsoCode', 'currency'],
        'customer' => ['customerId', 'debtorId'],
        'changed' => ['modifiedDate', 'lastModified', 'creationDate'],
        'cancelled' => ['isCanceled', 'isCancelled', 'cancelled'],
        'cancels' => ['canceledInvoiceId', 'cancelledInvoiceId', 'referenceInvoiceId'],
    ];

    public function __construct(
        private readonly JtlGatewayFactory $gateways,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return JtlWawiPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return $this->connection($organizationId) instanceof JtlConnection;
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        $connection = $this->connection($organizationId);
        if (! $connection instanceof JtlConnection) {
            return $counters;
        }

        $gateway = $this->gateways->for($connection);
        $since = $this->mirror->lastSourceChange($organizationId, $this->pluginId());
        $pageSize = max(1, min(250, (int) config('plugins.jtl_wawi.page_size', 100)));

        for ($page = 1; $page <= max(1, $pages); $page++) {
            try {
                $envelope = $gateway->salesInvoices($since, $page, $pageSize);
            } catch (JtlApiException $e) {
                if ($e->isMissingEndpoint()) {
                    // Diese Instanz kennt die Rechnungsliste nicht — sichtbar
                    // nichts tun ist besser als eine erfundene Quelle.
                    return $counters;
                }

                throw $e;
            }

            $rows = (array) ($envelope['items'] ?? []);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $counters['read']++;
                $this->mirror->store($organizationId, $this->pluginId(), $this->map($row), $counters);
            }

            if (! (bool) ($envelope['hasNextPage'] ?? false)) {
                break;
            }
        }

        return $counters;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): MirroredVoucher {
        $gross = $this->first($row, 'gross');
        $cancelled = $this->first($row, 'cancelled');
        $isCancellation = $cancelled === true
            || (is_numeric($gross) && (float) $gross < 0.0);
        $paidDate = VoucherMirror::date($this->first($row, 'paid'));

        return new MirroredVoucher(
            externalId: trim((string) ($this->first($row, 'id') ?? '')),
            direction: DocumentDirection::Outgoing,
            kind: $isCancellation ? DocumentKind::Cancellation : DocumentKind::Invoice,
            rawType: 'salesinvoice',
            rawStatus: $isCancellation ? 'cancelled' : null,
            state: $isCancellation ? 'cancelled' : ($paidDate !== null ? 'paid' : 'open'),
            number: trim((string) ($this->first($row, 'number') ?? '')) ?: null,
            date: VoucherMirror::date($this->first($row, 'date')),
            dueDate: VoucherMirror::date($this->first($row, 'due')),
            paidDate: $paidDate,
            totalAmount: VoucherMirror::decimal($gross),
            netAmount: VoucherMirror::decimal($this->first($row, 'net')),
            openAmount: $paidDate === null ? VoucherMirror::decimal($gross) : null,
            currency: trim((string) ($this->first($row, 'currency') ?? 'EUR')) ?: 'EUR',
            isCancellation: $isCancellation,
            cancelsExternalId: trim((string) ($this->first($row, 'cancels') ?? '')) ?: null,
            contactExternalId: trim((string) ($this->first($row, 'customer') ?? '')) ?: null,
            sourceChangedAt: VoucherMirror::timestamp($this->first($row, 'changed')),
            payload: $row,
        );
    }

    /** @param array<string, mixed> $row */
    private function first(array $row, string $key): mixed {
        foreach (self::FIELDS[$key] as $candidate) {
            if (array_key_exists($candidate, $row) && $row[$candidate] !== null && $row[$candidate] !== '') {
                return $row[$candidate];
            }
        }

        return null;
    }

    private function connection(int $organizationId): ?JtlConnection {
        return JtlConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', JtlConnection::STATUS_ACTIVE)
            ->first();
    }
}
