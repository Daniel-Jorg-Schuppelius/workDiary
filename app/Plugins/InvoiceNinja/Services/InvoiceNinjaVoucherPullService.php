<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\InvoiceNinja\Api\{InvoiceNinjaClient, InvoiceNinjaClientFactory};
use App\Plugins\InvoiceNinja\{InvoiceNinjaConfig, InvoiceNinjaPlugin};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, MirroredVoucherLine, VoucherMirror, VoucherPuller};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Invoice Ninja in den Belegspiegel (Feature 176, MVP-1331): Rechnungen und
 * Gutschriften als Ausgangsbelege, Ausgaben als Eingangsbelege, nur lesend.
 * Die IDs der drei Listen sind getrennte Räume, deshalb trägt die Fremd-ID die
 * Art (`invoice:<id>`). Der Status kommt aus `status_id`, nie aus der Anzeige;
 * gelöschte Belege gelten als storniert, eine verrechnete Gutschrift (4) als
 * bezahlt. Jede Liste hat ihre eigene Marke: Rechnungen und Gutschriften
 * kommen nach `updated_at` aufsteigend und setzen am Seitenlimit beim
 * nächsten Lauf fort. Ausgaben lassen sich nicht nach Änderung sortieren (die
 * API übergeht den Schlüssel und liefert nach ID absteigend) — der Erstlauf
 * nimmt die jüngsten `$pages` Seiten, ein Folgelauf liest alle Änderungen
 * vollständig, bevor er spiegelt.
 */
class InvoiceNinjaVoucherPullService implements VoucherPuller {
    /** Liste → Art, Richtung und mitzuladender Kontakt. */
    private const ENTITIES = [
        'invoices' => ['type' => 'invoice', 'kind' => DocumentKind::Invoice, 'direction' => DocumentDirection::Outgoing, 'include' => 'client', 'sortable' => true],
        'credits' => ['type' => 'credit', 'kind' => DocumentKind::CreditNote, 'direction' => DocumentDirection::Outgoing, 'include' => 'client', 'sortable' => true],
        'expenses' => ['type' => 'expense', 'kind' => DocumentKind::Expense, 'direction' => DocumentDirection::Incoming, 'include' => 'vendor', 'sortable' => false],
        // Serienrechnungen (Feature 185, MVP-1426): Plan, kein Umsatz — `GET /api/v1/recurring_invoices`.
        'recurring_invoices' => ['type' => 'recurring', 'kind' => DocumentKind::RecurringTemplate, 'direction' => DocumentDirection::Neutral, 'include' => 'client', 'sortable' => true],
    ];

    /** `frequency_id` laut `App\Models\RecurringInvoice` (FREQUENCY_*) → Rhythmus des Plans. */
    private const FREQUENCIES = ['2' => 'WEEKLY', '3' => 'BIWEEKLY', '5' => 'MONTHLY', '7' => 'QUARTERLY', '9' => 'BIANNUALLY', '10' => 'ANNUALLY'];

    /** Seiten, die ein Folgelauf für Ausgaben höchstens liest, bevor er als unvollständig abbricht (je `$pages`). */
    private const UNSORTED_PAGES_FACTOR = 50;

    /** @var array<string, ?string> Währung je Kundengruppe, einmal je Lauf gelesen */
    private array $groupCurrencies = [];

    public function __construct(
        private readonly InvoiceNinjaClientFactory $clients,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return InvoiceNinjaPlugin::ID;
    }

    /** Eingeschaltet, Token hinterlegt und „Belege abrufen“ gesetzt — der allgemeine Abruf prüft den Plugin-Schalter nicht selbst. */
    public function isConfigured(int $organizationId): bool {
        $config = InvoiceNinjaConfig::resolve($organizationId);

        return $config['enabled'] && $config['pull_vouchers'] && $this->clients->isConfigured($organizationId);
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        if (! $this->isConfigured($organizationId)) {
            return $counters;
        }
        $client = $this->clients->for($organizationId);
        $this->groupCurrencies = [];
        $context = ['client' => $client, 'currencies' => $client->currencies(), 'company' => $client->company(), 'organization_id' => $organizationId];

        foreach (self::ENTITIES as $entity => $meta) {
            $since = $this->mirror->lastSourceChange($organizationId, $this->pluginId(), $meta['type']);
            $query = ['include' => $meta['include']] + ($since !== null ? ['updated_at' => $since->getTimestamp()] : []);
            if ($meta['sortable']) {
                // Zweiter Schlüssel `id`: bei gleichem Zeitstempel blieben die Seiten sonst nicht stabil.
                $query['sort'] = ['updated_at|asc', 'id|asc'];
                $maxPages = $since !== null ? max(1, $pages * 5) : max(1, $pages);
            } else {
                $maxPages = $since !== null ? max(1, $pages * self::UNSORTED_PAGES_FACTOR) : max(1, $pages);
            }
            $rows = $this->readEntity($client, $entity, $query, $maxPages, $meta['sortable'] || $since === null);
            foreach ($rows as $row) {
                $counters['read']++;
                $voucher = $this->map($row, $meta, $context);
                $this->mirror->store($organizationId, $this->pluginId(), $voucher, $counters);
                if ($meta['type'] !== 'expense') {
                    // Positionen stehen im Beleg (Feature 185, MVP-1425: `line_items` laut InvoiceTransformer).
                    $this->mirror->storeLines($organizationId, $this->pluginId(), $voucher->externalId, self::lines($row, $voucher->currency));
                }
            }
        }

        return $counters;
    }

    /** Link zum Original in der Oberfläche (Belegfluss, {@see InvoiceNinjaVoucherLinks}). */
    public function voucherUrl(AccountingVoucher $voucher): ?string {
        [$type, $id] = array_pad(explode(':', (string) $voucher->external_id, 2), 2, '');
        $path = ['invoice' => 'invoices', 'credit' => 'credits', 'expense' => 'expenses'][$type] ?? null;
        if ($path === null || preg_match('/^[A-Za-z0-9]+$/', $id) !== 1) {
            return null;
        }
        $baseUrl = $this->clients->baseUrl((int) $voucher->organization_id);
        // Die gehostete Oberfläche liegt unter app.invoicing.co, die API unter invoicing.co.
        $app = $baseUrl === InvoiceNinjaConfig::HOSTED_URL ? 'https://app.invoicing.co' : $baseUrl;
        // Ohne `company` öffnet die Oberfläche bei mehreren Firmen die gerade gewählte.
        $company = data_get($voucher->payload, 'company_id');

        return $app . '/#/' . $path . '/' . $id . '/edit' . (is_string($company) && preg_match('/^[A-Za-z0-9]+$/', $company) === 1 ? '?company=' . $company : '');
    }

    /**
     * Seiten einer Liste. `$partialAllowed`: Endet das Lesen am Seitenlimit,
     * gilt das Gelesene trotzdem (sortierte Liste oder Erstlauf); sonst wirft
     * der Abbruch, bevor etwas gespiegelt ist.
     *
     * @param  array<string, int|string|list<string>>  $query
     * @return list<array<string, mixed>>
     */
    private function readEntity(InvoiceNinjaClient $client, string $entity, array $query, int $maxPages, bool $partialAllowed): array {
        $rows = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $client->page($entity, $query, $page);
            array_push($rows, ...$result['rows']);
            if ($result['rows'] === [] || $page >= $result['total_pages']) {
                return $rows;
            }
        }
        if (! $partialAllowed) {
            throw new IncompleteRemoteReadException('page_limit');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{type: string, kind: DocumentKind, direction: DocumentDirection, include: string, sortable: bool}  $meta
     * @param  array{client: InvoiceNinjaClient, currencies: array<string, string>, company: array{id: ?string, currency_id: ?string}, organization_id: int}  $context
     */
    private function map(array $row, array $meta, array $context): MirroredVoucher {
        $id = StringHelper::trimToNull($row['id'] ?? null);
        $contact = is_array($row[$meta['include']] ?? null) ? $row[$meta['include']] : [];
        $currency = $context['currencies'][$this->currencyId($row, $contact, $context) ?? ''] ?? 'EUR';
        $currencyCode = CurrencyCode::tryFrom($currency) ?? CurrencyCode::Euro;
        $isExpense = $meta['type'] === 'expense';
        $isRecurring = $meta['type'] === 'recurring';
        [$total, $net] = $isExpense ? self::expenseAmounts($row, $currencyCode) : self::documentAmounts($row, $currencyCode);
        if ($isRecurring) {
            [$total, $net] = [null, null]; // Plan, kein Umsatz
        }
        $foreign = $isExpense ? self::expenseForeign($row, $context['currencies']) : [null, null];

        return new MirroredVoucher(
            externalId: $id !== null ? $meta['type'] . ':' . $id : '',
            direction: $meta['direction'],
            kind: $meta['kind'],
            rawType: $meta['type'],
            rawStatus: StringHelper::trimToNull($row['status_id'] ?? null),
            state: $isRecurring ? AccountingVoucherState::Draft : $this->state($meta['type'], $row),
            number: StringHelper::trimToNull($row['number'] ?? null),
            date: VoucherMirror::date($row['date'] ?? null),
            dueDate: $isExpense ? null : VoucherMirror::date($row['due_date'] ?? null),
            paidDate: $isExpense ? VoucherMirror::date($row['payment_date'] ?? null) : null,
            totalAmount: $total,
            netAmount: $net,
            openAmount: $isExpense || $isRecurring ? null : VoucherMirror::decimal($row['balance'] ?? null),
            currency: $currency,
            foreignAmount: $foreign[0],
            foreignCurrency: $foreign[1],
            archived: is_numeric($row['archived_at'] ?? null) && (int) $row['archived_at'] > 0,
            contactExternalId: ($contactId = StringHelper::trimToNull($row[$isExpense ? 'vendor_id' : 'client_id'] ?? null)) !== null ? ($isExpense ? 'vendor:' : 'client:') . $contactId : null,
            supplierName: $isExpense ? StringHelper::trimToNull($contact['name'] ?? null) : null,
            customerNumber: $isExpense ? null : StringHelper::trimToNull($contact['number'] ?? null),
            sourceChangedAt: is_numeric($row['updated_at'] ?? null) && (int) $row['updated_at'] > 0 ? Carbon::createFromTimestamp((int) $row['updated_at'])->toDateTimeString() : null,
            payload: array_diff_key($row, ['client' => true, 'vendor' => true, 'invitations' => true]) + ['company_id' => $context['company']['id']]
                + ($isRecurring ? ['plan' => $this->plan($row, $context)] : []),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<MirroredVoucherLine>
     */
    private static function lines(array $row, string $currency): array {
        $lines = [];
        $index = 0;
        foreach ((array) ($row['line_items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $index++;
            $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0;
            $cost = VoucherMirror::decimal($item['cost'] ?? null, 4) ?? '0.0000';
            $total = VoucherMirror::decimal($item['line_total'] ?? null) ?? NumberHelper::toUSFormat($quantity * (float) $cost, 2);
            $lines[] = new MirroredVoucherLine(
                position: $index,
                name: mb_substr(StringHelper::trimToNull($item['product_key'] ?? null) ?? StringHelper::trimToNull($item['notes'] ?? null) ?? '#' . $index, 0, 255),
                quantity: $quantity,
                unitNet: $cost,
                totalNet: $total,
                currency: $currency,
                externalArticleId: StringHelper::trimToNull($item['product_key'] ?? null),
                description: StringHelper::trimToNull($item['notes'] ?? null),
                unitName: null,
                taxRate: is_numeric($item['tax_rate1'] ?? null) ? (string) $item['tax_rate1'] : null,
            );
        }

        return $lines;
    }

    /**
     * Normalisierter Plan einer Serienrechnung (Feature 185, MVP-1426): Status
     * und Rhythmus laut `App\Models\RecurringInvoice` (STATUS_*, FREQUENCY_*),
     * Link wie {@see voucherUrl()} in die Oberfläche.
     *
     * @param  array<string, mixed>  $row
     * @param  array{client: InvoiceNinjaClient, currencies: array<string, string>, company: array{id: ?string, currency_id: ?string}, organization_id: int}  $context
     * @return array<string, mixed>
     */
    private function plan(array $row, array $context): array {
        $remaining = is_numeric($row['remaining_cycles'] ?? null) ? (int) $row['remaining_cycles'] : -1;
        $status = match ((string) ($row['status_id'] ?? '')) {
            '2' => $remaining === 0 ? 'ENDED' : 'ACTIVE',
            '4' => 'ENDED',
            default => 'PAUSED', // 1 Entwurf, 3 pausiert, -1 wartend
        };
        $id = StringHelper::trimToNull($row['id'] ?? null);
        $company = $context['company']['id'];
        $baseUrl = $this->clients->baseUrl($context['organization_id']);
        $app = $baseUrl === InvoiceNinjaConfig::HOSTED_URL ? 'https://app.invoicing.co' : $baseUrl;

        return [
            'status' => $status,
            'interval' => self::FREQUENCIES[(string) ($row['frequency_id'] ?? '')] ?? '',
            'starts_on' => VoucherMirror::date($row['date'] ?? null),
            'ends_on' => null,
            'next_execution_on' => VoucherMirror::date($row['next_send_date'] ?? null),
            'last_execution_on' => VoucherMirror::date($row['last_sent_date'] ?? null),
            'finalize' => true, // Invoice Ninja erzeugt aus der Serie fertige Rechnungen
            'last_failed' => false,
            'last_error' => null,
            'permalink' => $id !== null && preg_match('/^[A-Za-z0-9]+$/', $id) === 1
                ? $app . '/#/recurring_invoices/' . $id . '/edit' . (is_string($company) && preg_match('/^[A-Za-z0-9]+$/', $company) === 1 ? '?company=' . $company : '')
                : null,
        ];
    }

    /**
     * Währung wie in Invoice Ninja: die des Belegs (nur Ausgaben tragen eine),
     * sonst die des Kunden, seiner Gruppe, zuletzt die der Firma. Leere
     * Einstellungen zählen nicht.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $contact
     * @param  array{client: InvoiceNinjaClient, currencies: array<string, string>, company: array{id: ?string, currency_id: ?string}, organization_id: int}  $context
     */
    private function currencyId(array $row, array $contact, array $context): ?string {
        $own = StringHelper::trimToNull($row['currency_id'] ?? null) ?? StringHelper::trimToNull(data_get($contact, 'settings.currency_id'));
        if ($own !== null) {
            return $own;
        }
        $groupId = StringHelper::trimToNull($contact['group_settings_id'] ?? null);
        if ($groupId !== null) {
            if (! array_key_exists($groupId, $this->groupCurrencies)) {
                $this->groupCurrencies[$groupId] = $context['client']->groupCurrencyId($groupId);
            }
            if ($this->groupCurrencies[$groupId] !== null) {
                return $this->groupCurrencies[$groupId];
            }
        }

        return $context['company']['currency_id'];
    }

    /**
     * Gegenwert einer Ausgabe in Fremdwährung: Invoice Ninja rechnet sie in die
     * Firmenwährung (`invoice_currency_id`) um und führt das Ergebnis als
     * `foreign_amount`.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $currencies
     * @return array{0: ?string, 1: ?string}
     */
    private static function expenseForeign(array $row, array $currencies): array {
        $target = StringHelper::trimToNull($row['invoice_currency_id'] ?? null);
        $amount = VoucherMirror::decimal($row['foreign_amount'] ?? null);
        if ($target === null || $target === StringHelper::trimToNull($row['currency_id'] ?? null) || $amount === null || (float) $amount === 0.0 || ! isset($currencies[$target])) {
            return [null, null];
        }

        return [$amount, $currencies[$target]];
    }

    /**
     * Rechnung und Gutschrift: `amount` ist brutto, netto = brutto − `total_taxes`.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?string, 1: ?string}
     */
    private static function documentAmounts(array $row, CurrencyCode $currency): array {
        $total = VoucherMirror::decimal($row['amount'] ?? null);
        $taxes = VoucherMirror::decimal($row['total_taxes'] ?? null);

        return [$total, $total !== null && $taxes !== null ? Money::of($total, $currency)->minus(Money::of($taxes, $currency))->getAmount() : null];
    }

    /**
     * Ausgabe: `amount` ist nur bei `uses_inclusive_taxes` brutto, sonst netto.
     * Die Steuer kommt als Betrag (`calculate_tax_by_amount`) oder aus bis zu
     * drei Sätzen; eingeschlossene Steuer je Satz aus dem Bruttobetrag, je
     * Satz einmal gerundet.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?string, 1: ?string}
     */
    private static function expenseAmounts(array $row, CurrencyCode $currency): array {
        $amount = VoucherMirror::decimal($row['amount'] ?? null);
        if ($amount === null) {
            return [null, null];
        }
        $money = Money::of($amount, $currency);
        $inclusive = ($row['uses_inclusive_taxes'] ?? false) === true;
        $rates = array_values(array_filter(array_map(static fn (int $n): float => is_numeric($row['tax_rate' . $n] ?? null) ? (float) $row['tax_rate' . $n] : 0.0, [1, 2, 3]), static fn (float $rate): bool => $rate > 0));
        if (($row['calculate_tax_by_amount'] ?? false) === true) {
            $tax = Money::sum(array_map(static fn (int $n): Money => Money::of(VoucherMirror::decimal($row['tax_amount' . $n] ?? null) ?? '0', $currency), [1, 2, 3]), $currency);
        } elseif ($inclusive) {
            $sum = array_sum($rates);
            $tax = Money::sum(array_map(static fn (float $rate): Money => $money->times($rate / (100 + $sum)), $rates), $currency);
        } else {
            $tax = $money->percentage(array_sum($rates));
        }

        return $inclusive
            ? [$amount, $money->minus($tax)->getAmount()]
            : [$money->plus($tax)->getAmount(), $amount];
    }

    /** @param array<string, mixed> $row */
    private function state(string $type, array $row): AccountingVoucherState {
        if (($row['is_deleted'] ?? false) === true) {
            return AccountingVoucherState::Cancelled;
        }
        if ($type === 'expense') {
            return VoucherMirror::date($row['payment_date'] ?? null) !== null ? AccountingVoucherState::Paid : AccountingVoucherState::Open;
        }

        return match ((string) ($row['status_id'] ?? '')) {
            '1' => AccountingVoucherState::Draft,
            '4' => AccountingVoucherState::Paid,
            '5', '6' => AccountingVoucherState::Cancelled,
            default => AccountingVoucherState::Open,
        };
    }
}
