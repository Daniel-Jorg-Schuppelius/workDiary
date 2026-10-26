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
use App\Enums\Finance\AccountingVoucherState;
use App\Plugins\Easybill\Api\{EasybillClient, EasybillClientFactory};
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror, VoucherPuller};
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
        return app(EasybillClientFactory::class)->isConfigured($organizationId);
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
                // Positionen stehen in der Liste (Feature 185, MVP-1425: `Document.items` → `DocumentPosition`).
                $this->mirror->storeLines($organizationId, $this->pluginId(), trim((string) ($row['id'] ?? '')), self::lines($row));
            }

            if (count($rows) < $limit) {
                break;
            }
        }
        $this->pullRecurring($client, $organizationId, $limit, $counters);

        return $counters;
    }

    /**
     * Serienvorlagen (Feature 185, MVP-1426): easybill führt sie als Dokumente
     * vom Typ `RECURRING` mit `recurring_options` (`next_date`, `frequency`,
     * `interval`, `status`, `target_type`, `as_draft`). Gespiegelt als Beleg der
     * Art `recurring_template` ohne Beträge — Plan, kein Umsatz.
     *
     * @param  array{read: int, created: int, updated: int, skipped: int}  $counters
     */
    private function pullRecurring(EasybillClient $client, int $organizationId, int $limit, array &$counters): void {
        for ($page = 1; $page <= 5; $page++) {
            $rows = $client->documents(['type' => 'RECURRING', 'limit' => $limit, 'page' => $page]);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                if (! is_array($row) || strtoupper((string) data_get($row, 'recurring_options.target_type', 'INVOICE')) !== 'INVOICE') {
                    continue;
                }
                $counters['read']++;
                $this->mirror->store($organizationId, $this->pluginId(), $this->mapRecurring($row), $counters);
                $this->mirror->storeLines($organizationId, $this->pluginId(), trim((string) ($row['id'] ?? '')), self::lines($row));
            }
            if (count($rows) < $limit) {
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<MirroredVoucherLine>
     */
    private static function lines(array $row): array {
        $lines = [];
        $index = 0;
        foreach ((array) ($row['items'] ?? []) as $item) {
            if (! is_array($item) || strtoupper((string) ($item['type'] ?? 'POSITION')) === 'TEXT') {
                continue;
            }
            $index++;
            $position = is_numeric($item['position'] ?? null) ? (int) $item['position'] : $index;
            $description = trim((string) ($item['description'] ?? ''));
            $lines[] = new MirroredVoucherLine(
                position: $position > 0 ? $position : $index,
                name: mb_substr(\CommonToolkit\Helper\Data\StringHelper::lines($description, true)[0] ?? ($item['number'] ?? ''), 0, 255),
                quantity: is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0,
                unitNet: VoucherMirror::fromCents($item['single_price_net'] ?? null) ?? '0.00',
                totalNet: VoucherMirror::fromCents($item['total_price_net'] ?? null) ?? '0.00',
                currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
                externalArticleId: trim((string) ($item['number'] ?? '')) ?: null,
                description: $description !== '' ? $description : null,
                unitName: trim((string) ($item['unit'] ?? '')) ?: null,
                taxRate: is_numeric($item['vat_percent'] ?? null) ? (string) $item['vat_percent'] : null,
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapRecurring(array $row): MirroredVoucher {
        $options = is_array($row['recurring_options'] ?? null) ? $row['recurring_options'] : [];
        $frequency = strtoupper((string) ($options['frequency'] ?? ''));
        $every = max(1, (int) ($options['interval'] ?? 1));
        $interval = match (true) {
            $frequency === 'YEARLY' && $every === 1 => 'ANNUALLY',
            $frequency === 'MONTHLY' && $every === 1 => 'MONTHLY',
            $frequency === 'MONTHLY' && $every === 3 => 'QUARTERLY',
            $frequency === 'MONTHLY' && $every === 6 => 'BIANNUALLY',
            $frequency === 'MONTHLY' && $every === 12 => 'ANNUALLY',
            $frequency === 'WEEKLY' && $every === 1 => 'WEEKLY',
            $frequency === 'WEEKLY' && $every === 2 => 'BIWEEKLY',
            default => '',
        };
        $status = match (strtoupper((string) ($options['status'] ?? ''))) {
            'RUNNING' => 'ACTIVE',
            'STOP' => 'ENDED',
            default => 'PAUSED', // PAUSE, WAITING
        };
        $end = trim((string) ($options['end_date_or_count'] ?? ''));

        return new MirroredVoucher(
            externalId: trim((string) ($row['id'] ?? '')),
            direction: DocumentDirection::Neutral,
            kind: DocumentKind::RecurringTemplate,
            rawType: 'RECURRING',
            rawStatus: strtoupper((string) ($options['status'] ?? '')) ?: null,
            state: AccountingVoucherState::Draft,
            number: trim((string) ($row['title'] ?? $row['number'] ?? '')) ?: null,
            date: VoucherMirror::date($row['document_date'] ?? null),
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            archived: (bool) ($row['is_archive'] ?? false),
            contactExternalId: ($row['customer_id'] ?? null) !== null ? (string) $row['customer_id'] : null,
            sourceChangedAt: VoucherMirror::timestamp($row['edited_at'] ?? ($row['created_at'] ?? null)),
            payload: $row + ['plan' => [
                'status' => $status,
                'interval' => $interval,
                'starts_on' => VoucherMirror::date($row['document_date'] ?? null),
                'ends_on' => preg_match('/^\d{4}-\d{2}-\d{2}/', $end) === 1 ? substr($end, 0, 10) : null,
                'next_execution_on' => VoucherMirror::date($options['next_date'] ?? null),
                'last_execution_on' => null,
                'finalize' => ! (bool) ($options['as_draft'] ?? false),
                'last_failed' => false,
                'last_error' => null,
                'permalink' => null,
            ]],
        );
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
    private function state(array $row, string $cancelId): AccountingVoucherState {
        if (($row['is_draft'] ?? false) === true) {
            return AccountingVoucherState::Draft;
        }
        if ($cancelId !== '' && $cancelId !== '0') {
            // Der Beleg wurde durch ein Stornodokument aufgehoben.
            return AccountingVoucherState::Cancelled;
        }

        return VoucherMirror::date($row['paid_at'] ?? null) !== null ? AccountingVoucherState::Paid : AccountingVoucherState::Open;
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
