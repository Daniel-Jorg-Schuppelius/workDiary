<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Finance\Accounting\Vouchers\{LineAwareVoucherPuller, MirroredVoucher, MirroredVoucherLine, VoucherMirror};
use Illuminate\Support\Carbon;

/**
 * Beleg-Rückabruf aus sevDesk (Feature 122, MVP-611; auf den gemeinsamen
 * {@see VoucherPuller}-Vertrag gehoben mit MVP-731).
 *
 * Belege, die direkt in der Buchhaltung entstehen — Kassenbon, per Mail an
 * den Steuerberater gegangene Lieferantenrechnung — tauchen in workDiary
 * sonst nie auf, und der Belegfluss hat ein Loch, das niemand sieht.
 *
 * Gespiegelt wird, nicht übernommen: Der Beleg gehört sevDesk. workDiary
 * schreibt nichts zurück und löscht nichts.
 *
 * - **Endpunkt** `GET /Voucher` (jüngste zuerst), Paginierung `offset`/`limit`.
 * - **Richtung** aus `creditDebit`: C = Einnahme (ausgehend), D = Ausgabe
 *   (eingehend). Eine Belegart-Taxonomie hat sevDesk nicht — die Art bleibt
 *   bewusst „sonstiges".
 * - **Status** (`status`): 50 Entwurf, 100/750 offen, 1000 bezahlt.
 * - **Storno**: sevDesk führt am Beleg kein Stornokennzeichen; ein
 *   Stornovorgang ist dort eine Buchung, kein Beleg-Attribut. Deshalb wird
 *   hier nichts als Storno markiert — geraten wird nicht.
 */
class SevDeskVoucherPullService implements LineAwareVoucherPuller {
    /** Seitengröße des Abrufs; sevDesk liefert jüngste zuerst. */
    private const PAGE_SIZE = 50;

    /** sevDesk-Statuskatalog → normalisierter Belegzustand. */
    private const STATES = [
        '50' => AccountingVoucherState::Draft,
        '100' => AccountingVoucherState::Open,
        '750' => AccountingVoucherState::Open,
        '1000' => AccountingVoucherState::Paid,
    ];

    /**
     * Rechnungsstatus (`GET /Invoice`, Doku „Types and status of invoices“):
     * 50 deaktivierte Serienrechnung, 100 Entwurf, 200 offen/fällig,
     * 750 teilweise bezahlt, 1000 bezahlt.
     */
    private const INVOICE_STATES = [
        '50' => AccountingVoucherState::Draft,
        '100' => AccountingVoucherState::Draft,
        '200' => AccountingVoucherState::Open,
        '750' => AccountingVoucherState::Open,
        '1000' => AccountingVoucherState::Paid,
    ];

    /** Rechnungs-IDs mit Präfix, damit sie nicht mit Beleg-IDs (`/Voucher`) kollidieren. */
    public const INVOICE_PREFIX = 'invoice:';

    public function __construct(
        private readonly SevDeskClientFactory $clients,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return ! empty(SevDeskConfig::resolve($organizationId)['api_key']);
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        if (! $this->isConfigured($organizationId)) {
            return $counters;
        }

        $client = $this->clients->for($organizationId);

        for ($page = 0; $page < max(1, $pages); $page++) {
            $rows = $client->vouchers($page * self::PAGE_SIZE, self::PAGE_SIZE);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $counters['read']++;
                $this->mirror->store($organizationId, $this->pluginId(), $this->map($row), $counters);
            }

            if (count($rows) < self::PAGE_SIZE) {
                break;
            }
        }

        // Ausgangsrechnungen (`GET /Invoice`, Feature 185, MVP-1425): Positionen kommen je Rechnung im Einzelabruf nach.
        for ($page = 0; $page < max(1, $pages); $page++) {
            $rows = $client->invoices($page * self::PAGE_SIZE, self::PAGE_SIZE);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $counters['read']++;
                $this->mirror->store($organizationId, $this->pluginId(), $this->mapInvoice($row), $counters);
            }
            if (count($rows) < self::PAGE_SIZE) {
                break;
            }
        }

        return $counters;
    }

    public function pullLines(int $organizationId, AccountingVoucher $voucher): array {
        if (! str_starts_with((string) $voucher->external_id, self::INVOICE_PREFIX)) {
            return []; // Belege (`/Voucher`) tragen keine Rechnungspositionen
        }
        $client = $this->clients->for($organizationId);
        $lines = [];
        $index = 0;
        foreach ($client->invoicePositions(substr((string) $voucher->external_id, strlen(self::INVOICE_PREFIX))) as $position) {
            $index++;
            $number = is_numeric($position['positionNumber'] ?? null) ? (int) $position['positionNumber'] + 1 : $index;
            $lines[] = new MirroredVoucherLine(
                position: $number > 0 ? $number : $index,
                name: mb_substr(trim((string) ($position['name'] ?? '')) ?: ('#' . $index), 0, 255),
                quantity: is_numeric($position['quantity'] ?? null) ? (float) $position['quantity'] : 1.0,
                unitNet: VoucherMirror::decimal($position['priceNet'] ?? null, 4) ?? '0.0000',
                totalNet: VoucherMirror::decimal($position['sumNetAccounting'] ?? null) ?? '0.00',
                currency: (string) ($voucher->currency ?: 'EUR'),
                externalArticleId: trim((string) data_get($position, 'part.id', '')) ?: null,
                description: trim((string) ($position['text'] ?? '')) ?: null,
                unitName: trim((string) data_get($position, 'unity.name', '')) ?: null,
                taxRate: is_numeric($position['taxRate'] ?? null) ? (string) $position['taxRate'] : null,
            );
        }

        return $lines;
    }

    /**
     * Rechnung laut `Model_Invoice`: `invoiceType` RE/MA/TR/AR/ER = Rechnung,
     * SR = Storno, WKR = Serienrechnung (Plan mit `accountIntervall` als
     * ISO-8601-Dauer und `accountNextInvoice` als Zeitstempel).
     *
     * @param  array<string, mixed>  $row
     */
    private function mapInvoice(array $row): MirroredVoucher {
        $type = strtoupper(trim((string) ($row['invoiceType'] ?? 'RE')));
        $status = trim((string) ($row['status'] ?? ''));
        $isRecurring = $type === 'WKR';
        $contactId = trim((string) data_get($row, 'contact.id', ''));

        return new MirroredVoucher(
            externalId: self::INVOICE_PREFIX . trim((string) ($row['id'] ?? '')),
            direction: $isRecurring ? DocumentDirection::Neutral : DocumentDirection::Outgoing,
            kind: match (true) {
                $isRecurring => DocumentKind::RecurringTemplate,
                $type === 'SR' => DocumentKind::Cancellation,
                default => DocumentKind::Invoice,
            },
            rawType: $type,
            rawStatus: $status !== '' ? $status : null,
            state: $isRecurring ? AccountingVoucherState::Draft : (self::INVOICE_STATES[$status] ?? AccountingVoucherState::Open),
            number: trim((string) ($row['invoiceNumber'] ?? '')) ?: null,
            date: VoucherMirror::date($row['invoiceDate'] ?? null),
            paidDate: VoucherMirror::date($row['payDate'] ?? null),
            totalAmount: $isRecurring ? null : VoucherMirror::decimal($row['sumGross'] ?? null),
            netAmount: $isRecurring ? null : VoucherMirror::decimal($row['sumNet'] ?? null),
            openAmount: null,
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            isCancellation: $type === 'SR',
            contactExternalId: $contactId !== '' ? $contactId : null,
            sourceChangedAt: VoucherMirror::timestamp($row['update'] ?? ($row['create'] ?? null)),
            payload: $row + ($isRecurring ? ['plan' => [
                'status' => match ($status) {
                    '50' => 'ENDED',
                    '200', '750', '1000' => 'ACTIVE',
                    default => 'PAUSED',
                },
                'interval' => self::intervalOf((string) ($row['accountIntervall'] ?? '')),
                'starts_on' => VoucherMirror::date($row['invoiceDate'] ?? null),
                'ends_on' => null,
                'next_execution_on' => is_numeric($row['accountNextInvoice'] ?? null) && (int) $row['accountNextInvoice'] > 0 ? Carbon::createFromTimestamp((int) $row['accountNextInvoice'])->toDateString() : null,
                'last_execution_on' => null,
                'finalize' => false,
                'last_failed' => false,
                'last_error' => null,
                'permalink' => null,
            ]] : []),
        );
    }

    /** ISO-8601-Dauer (`P1M`, `P3M`, `P1Y`, `P1W`, `P2W`) → Rhythmus des Plans. */
    private static function intervalOf(string $duration): string {
        return match (strtoupper($duration)) {
            'P1W', 'P7D' => 'WEEKLY',
            'P2W', 'P14D' => 'BIWEEKLY',
            'P1M' => 'MONTHLY',
            'P3M' => 'QUARTERLY',
            'P6M' => 'BIANNUALLY',
            'P1Y', 'P12M' => 'ANNUALLY',
            default => '',
        };
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): MirroredVoucher {
        $creditDebit = strtoupper(trim((string) ($row['creditDebit'] ?? '')));
        $status = trim((string) ($row['status'] ?? ''));

        return new MirroredVoucher(
            externalId: trim((string) ($row['id'] ?? '')),
            direction: match ($creditDebit) {
                'C' => DocumentDirection::Outgoing,
                'D' => DocumentDirection::Incoming,
                default => DocumentDirection::Neutral,
            },
            // sevDesk kennt keine Belegart-Taxonomie (MVP-611).
            kind: DocumentKind::Other,
            rawType: $creditDebit !== '' ? $creditDebit : null,
            rawStatus: $status !== '' ? $status : null,
            state: self::STATES[$status] ?? AccountingVoucherState::Open,
            number: trim((string) ($row['voucherNumber'] ?? ($row['description'] ?? ''))) ?: null,
            date: VoucherMirror::date($row['voucherDate'] ?? null),
            dueDate: VoucherMirror::date($row['dueDate'] ?? null),
            paidDate: VoucherMirror::date($row['payDate'] ?? null),
            totalAmount: VoucherMirror::decimal($row['sumGross'] ?? null),
            netAmount: VoucherMirror::decimal($row['sumNet'] ?? null),
            // sevDesk führt keinen Offenposten am Beleg; er ergibt sich aus
            // dem Zahldatum. Eine erfundene Zahl wäre schlechter als keine.
            openAmount: null,
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            archived: false,
            contactExternalId: trim((string) ($row['supplier']['id'] ?? '')) ?: null,
            supplierName: trim((string) ($row['supplierName'] ?? ($row['supplier']['name'] ?? ''))) ?: null,
            sourceChangedAt: VoucherMirror::timestamp($row['update'] ?? ($row['create'] ?? null)),
            payload: $row,
        );
    }
}
