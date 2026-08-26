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
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror, VoucherPuller};

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
class SevDeskVoucherPullService implements VoucherPuller {
    /** Seitengröße des Abrufs; sevDesk liefert jüngste zuerst. */
    private const PAGE_SIZE = 50;

    /** sevDesk-Statuskatalog → normalisierter Belegzustand. */
    private const STATES = [
        '50' => 'draft',
        '100' => 'open',
        '750' => 'open',
        '1000' => 'paid',
    ];

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

        return $counters;
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
            state: self::STATES[$status] ?? 'open',
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
