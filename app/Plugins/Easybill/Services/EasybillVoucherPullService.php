<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror, VoucherPuller};
use Illuminate\Support\Carbon;

/**
 * Beleg-Rückabruf aus easybill (Feature 122, MVP-731 — Vollscan G18).
 *
 * Vertragsgrundlage ist die mitgelieferte Swagger-Fixture
 * `tests/Fixtures/Plugins/Easybill/openapi.json` (v1.99), nicht Erinnerung:
 *
 * - **Endpunkt** `GET /documents`, Listenumschlag `{page, pages, limit, total,
 *   items[]}` — Paginierung über `page`/`limit` (max. 1000).
 * - **Belegarten** über `type` (Mehrfachwerte kommasepariert). Gespiegelt
 *   werden die buchhalterisch relevanten: `INVOICE`, `CREDIT`, `STORNO`,
 *   `STORNO_CREDIT`. Angebote, Lieferscheine, Mahnungen sind keine Belege.
 * - **Storno-Semantik**: easybill storniert nicht am Beleg, sondern legt ein
 *   eigenes Dokument an (`STORNO`/`STORNO_CREDIT`). Der stornierte Beleg
 *   trägt `cancel_id` (Doku: „ID from the cancel document. Only for document
 *   type INVOICE") — er gilt hier deshalb als `cancelled`, das Stornodokument
 *   als `is_cancellation` mit `cancels_external_id = root_id/ref_id`.
 * - **Beträge sind CENTS** (`amount`, `amount_net`, `paid_amount`;
 *   150 = 1,50 €) — Umrechnung ohne float über {@see VoucherMirror::fromCents()}.
 * - **Inkrement** über den Filter `edited_at=<von>,<bis>`: `edited_at` ist
 *   auch der Marker, den wir je Beleg mitschreiben. Der erste Lauf hat keinen
 *   Marker und liest `$pages` Seiten.
 *
 * Richtung: easybill führt die **Ausgangs**-Belege; Eingangsrechnungen leben
 * dort nicht unter `/documents`. Alles hier ist deshalb `outgoing` — geraten
 * wird nichts.
 */
class EasybillVoucherPullService implements VoucherPuller {
    /** Buchhalterisch relevante Dokumenttypen (Swagger-Enum `type`). */
    private const TYPES = 'INVOICE,CREDIT,STORNO,STORNO_CREDIT';

    public function __construct(
        private readonly EasybillClientFactory $clients,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        $config = EasybillConfig::resolve($organizationId);

        return ! empty($config['api_key']);
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        if (! $this->isConfigured($organizationId)) {
            return $counters;
        }

        $limit = max(1, min(1000, (int) config('plugins.easybill.page_size', 100)));
        $since = $this->mirror->lastSourceChange($organizationId, $this->pluginId());
        $client = $this->clients->for($organizationId);

        $query = ['type' => self::TYPES, 'limit' => $limit];
        if ($since !== null) {
            // easybill-Filterkonvention: Zeitraum als "von,bis" (Doku `edited_at`).
            $query['edited_at'] = $since->toDateString() . ',' . Carbon::now()->addDay()->toDateString();
        }

        $maxPages = $since !== null ? max(1, $pages * 5) : max(1, $pages);
        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $client->documents($query + ['page' => $page]);
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

            if (count($rows) < $limit) {
                break;
            }
        }

        return $counters;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): MirroredVoucher {
        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        $isCancellation = str_starts_with($type, 'STORNO');
        $cancelId = trim((string) ($row['cancel_id'] ?? ''));

        return new MirroredVoucher(
            externalId: trim((string) ($row['id'] ?? '')),
            direction: DocumentDirection::Outgoing,
            kind: $this->kind($type),
            rawType: $type !== '' ? $type : null,
            rawStatus: ($row['status'] ?? null) !== null ? (string) $row['status'] : null,
            state: $this->state($row, $cancelId),
            number: trim((string) ($row['number'] ?? '')) ?: null,
            date: VoucherMirror::date($row['document_date'] ?? null),
            dueDate: VoucherMirror::date($row['due_date'] ?? null),
            paidDate: VoucherMirror::date($row['paid_at'] ?? null),
            totalAmount: VoucherMirror::fromCents($row['amount'] ?? null),
            netAmount: VoucherMirror::fromCents($row['amount_net'] ?? null),
            openAmount: $this->openAmount($row),
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            archived: (bool) ($row['is_archive'] ?? false),
            isCancellation: $isCancellation,
            // Das Stornodokument nennt den Ursprungsbeleg über root_id/ref_id.
            cancelsExternalId: $isCancellation
                ? (trim((string) ($row['root_id'] ?? $row['ref_id'] ?? '')) ?: null)
                : null,
            contactExternalId: trim((string) ($row['customer_id'] ?? '')) ?: null,
            sourceChangedAt: VoucherMirror::timestamp($row['edited_at'] ?? ($row['created_at'] ?? null)),
            payload: $row,
        );
    }

    private function kind(string $type): DocumentKind {
        return match ($type) {
            'INVOICE' => DocumentKind::Invoice,
            'CREDIT' => DocumentKind::CreditNote,
            'STORNO', 'STORNO_CREDIT' => DocumentKind::Cancellation,
            default => DocumentKind::Other,
        };
    }

    /** @param array<string, mixed> $row */
    private function state(array $row, string $cancelId): string {
        if (($row['is_draft'] ?? false) === true) {
            return 'draft';
        }
        if ($cancelId !== '' && $cancelId !== '0') {
            // Der Beleg wurde durch ein Stornodokument aufgehoben.
            return 'cancelled';
        }

        return VoucherMirror::date($row['paid_at'] ?? null) !== null ? 'paid' : 'open';
    }

    /**
     * Offener Betrag = Gesamt − bezahlt (beides Cents). Ohne Zahlung ist der
     * ganze Beleg offen; bei fehlenden Feldern lieber `null` als eine Zahl,
     * die niemand belegen kann.
     *
     * @param array<string, mixed> $row
     */
    private function openAmount(array $row): ?string {
        if (! is_int($row['amount'] ?? null)) {
            return null;
        }
        $paid = is_int($row['paid_amount'] ?? null) ? (int) $row['paid_amount'] : 0;

        return VoucherMirror::fromCents((int) $row['amount'] - $paid);
    }
}
