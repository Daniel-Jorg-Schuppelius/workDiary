<?php
/*
 * Created on   : Thu Oct 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingMirrorSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainAccountingEntry, DomainResellerAccount};
use App\Models\Platform\Organization;
use App\Models\Reselling\ResalePriceEntry;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Buchungsjournal des Domainanbieters als Belegquelle (Feature 152, Befund
 * 2026-10-29): Das Portal stellt Subresellern Monatsrechnungen
 * „<Kundennummer>-MM-JJJJ“ und zieht das Geld ein; der Inhaber importiert sie
 * als Umsatzbelege ohne Positionen in die Buchhaltung. Die Positionen stehen
 * im Journal: je Buchung mit Domain (Verlängerung, Registrierung, Transfer)
 * eine Zeile mit Datum, Laufzeit und Betrag. Die Quelle fasst die Buchungen
 * eines Subusers je Monat zu einem Beleg mit derselben Nummer zusammen;
 * Empfänger ist der Kunde des Subusers. Lizenzpositionen gibt es hier keine —
 * Domain-Abos treffen die Zeilen über den Domainnamen. Beträge (Entscheidung
 * 2026-10-30): das Journal nennt nur den Einkauf des Inhabers, der Subuser zahlt
 * den Preis seiner Benutzerklasse — die Position trägt den Klassenpreis aus dem
 * Preiskatalog (`domain:sync-prices`), sonst den Journalbetrag; was die gebuchte
 * Monatsrechnung darüber hinaus enthält (DNS-Zonen, Gebühren), steht in der
 * Belegansicht als Restposition „Weitere Posten des Portals“.
 */
final class DomainAccountingMirrorSource implements InvoiceMirrorSource {
    public const KEY = 'domain-accounting';

    public function key(): string {
        return self::KEY;
    }

    public function morphClass(): string {
        return (new DomainAccountingEntry)->getTable();
    }

    public function linesFor(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, bool $licenceOnly = true): Collection {
        if ($licenceOnly) {
            return collect();
        }
        // Kein Von-Filter: eine Mehrjahresbuchung vor dem Fenster deckt Perioden darin; das Journal ist klein.
        return $this->map($organization, $this->entryQuery($organization, $recipientCustomerIds, null, $to)->get());
    }

    public function voidedLines(Organization $organization, ?array $recipientCustomerIds): Collection {
        return collect();
    }

    public function creditNoteLines(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): Collection {
        return collect();
    }

    public function vouchersFor(Organization $organization, array $recipientCustomerIds, CarbonImmutable $from, int $limit = 150): Collection {
        if ($recipientCustomerIds === []) {
            return collect();
        }
        $lines = $this->withRemainders($organization, $this->map($organization, $this->entryQuery($organization, $recipientCustomerIds, $from, null)->get()));

        return $this->vouchers($lines)
            ->sortByDesc(static fn(MirrorVoucher $v): string => ($v->voucherDate?->format('Y-m-d') ?? '') . ' ' . $v->voucherKey)
            ->take($limit)
            ->values();
    }

    public function voucherByKey(Organization $organization, string $voucherKey): ?MirrorVoucher {
        // „<Subuser>:<JJJJ-MM>“ — der Monatsbeleg des Portals an den Subuser.
        if (preg_match('/^(\d+):(\d{4})-(\d{2})$/', $voucherKey, $m) !== 1) {
            return null;
        }
        $month = CarbonImmutable::parse($m[2] . '-' . $m[3] . '-01', 'UTC')->startOfDay();
        $entries = $this->entryQuery($organization, null, $month, $month->endOfMonth())->where('reseller_account_id', (int) $m[1])->get();

        return $this->vouchers($this->withRemainders($organization, $this->map($organization, $entries)))->first(static fn(MirrorVoucher $v): bool => $v->voucherKey === $voucherKey);
    }

    public function pendingCount(Organization $organization, array $recipientCustomerIds, ?CarbonImmutable $from = null): int {
        return 0;
    }

    public function unlinkedLines(Organization $organization, int $limit): array {
        return ['total' => 0, 'lines' => collect()];
    }

    public function linesByIds(Organization $organization, array $ids): Collection {
        if ($ids === []) {
            return collect();
        }

        return $this->map($organization, $this->baseQuery($organization)->whereIn('domain_accounting_entries.id', $ids)->get())
            ->keyBy(static fn(MirrorLine $line): int => $line->morphId);
    }

    public function lineByKey(Organization $organization, string $key): ?MirrorLine {
        $id = Sqid::decode(DomainAccountingEntry::class, $key);

        return $id === null ? null : $this->linesByIds($organization, [$id])->get($id);
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return DomainResellerAccount::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $recipient->id)
            ->exists();
    }

    public function draftBecameInvoice(Organization $organization, string $reference): bool {
        return false;
    }

    public function constrainProposalLinks(Organization $organization, Builder $links): void {
        // Journalzeilen sind nie Entwürfe des Registers.
    }

    /** @return Builder<DomainAccountingEntry> */
    private function baseQuery(Organization $organization): Builder {
        return DomainAccountingEntry::query()->withoutGlobalScopes()
            ->where('domain_accounting_entries.organization_id', $organization->id)
            ->whereNotNull('domain_projection_id')
            ->whereNotNull('reseller_account_id')
            ->whereNotNull('entry_date')
            ->whereNotNull('net_amount')
            ->with(['resellerAccount', 'domainProjection:id,external_domain']);
    }

    /**
     * Buchungen mit Domain für Subuser mit zugeordnetem Kunden (bzw. den Empfängern).
     *
     * @param  list<int>|null  $recipientCustomerIds
     * @return Builder<DomainAccountingEntry>
     */
    private function entryQuery(Organization $organization, ?array $recipientCustomerIds, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder {
        $resellers = DomainResellerAccount::query()->withoutGlobalScopes()->where('organization_id', $organization->id);
        if ($recipientCustomerIds !== null) {
            $resellers->whereIn('customer_id', $recipientCustomerIds);
        } else {
            $resellers->whereNotNull('customer_id');
        }
        $query = $this->baseQuery($organization)->whereIn('reseller_account_id', $resellers->select('id'));
        if ($from !== null) {
            $query->where('entry_date', '>=', DateRange::day($from));
        }
        if ($to !== null) {
            $query->where('entry_date', '<', DateRange::dayAfter($to));
        }

        return $query->orderBy('entry_date')->orderBy('domain_accounting_entries.id');
    }

    /**
     * @param  Collection<int, DomainAccountingEntry>  $entries
     * @return Collection<int, MirrorLine>
     */
    private function map(Organization $organization, Collection $entries): Collection {
        $customerIds = [];
        foreach ($entries as $entry) {
            if ($entry->resellerAccount?->customer_id !== null) {
                $customerIds[(int) $entry->resellerAccount->customer_id] = true;
            }
        }
        /** @var array<int, string> $names */
        $names = $customerIds === [] ? [] : Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereIn('id', array_keys($customerIds))->pluck('name', 'id')->all();
        $prices = $this->classPrices($organization, $entries);
        $positions = [];
        $lines = [];
        foreach ($entries as $entry) {
            $reseller = $entry->resellerAccount;
            if ($reseller === null || $reseller->customer_id === null || $entry->entry_date === null) {
                continue;
            }
            $start = CarbonImmutable::instance($entry->entry_date)->startOfDay();
            // Immer der Monatsbeleg: „accounting invoice id“ ist die Registrar-Rechnung an den Inhaber
            // (AR<Datum>…, Prod 2026-10-29), nicht die Rechnung des Portals an den Subuser.
            $voucherKey = $reseller->id . ':' . $start->format('Y-m');
            $positions[$voucherKey] = ($positions[$voucherKey] ?? 0) + 1;
            $lines[] = $this->toLine($entry, $reseller, $start, $voucherKey, $positions[$voucherKey], $names[(int) $reseller->customer_id] ?? null, self::classUnit($prices, $entry, $reseller, $start));
        }

        return collect($lines);
    }

    private function toLine(DomainAccountingEntry $entry, DomainResellerAccount $reseller, CarbonImmutable $start, string $voucherKey, int $position, ?string $recipientName, ?Money $classUnit): MirrorLine {
        $currency = $entry->currency instanceof CurrencyCode ? $entry->currency : CurrencyCode::Euro;
        $years = self::yearsOf($entry);
        // Belastung steht negativ im Journal; ein positiver Betrag mit Domain ist eine Rückbuchung.
        $net = Money::of((string) $entry->net_amount, $currency, 4);
        $isCreditNote = ! $net->isNegative();
        $total = $net->isNegative() ? $net->negated() : $net;
        if ($classUnit !== null) {
            $total = $classUnit->withScale(4)->times($years); // der Subuser zahlt den Preis seiner Klasse, nicht den Einkauf
        }
        // Nummer der Monatsrechnung des Portals an den Subuser: Kundennummer plus Monat.
        $number = ($reseller->provider_customer_number ?? $reseller->external_user) . '-' . $start->format('m-Y');
        $registrarInvoice = trim((string) $entry->invoice_number);
        $type = (string) $entry->type;
        $typeLabel = $type !== '' && Lang::has('domain.accounting.types.' . $type) ? (string) __('domain.accounting.types.' . $type) : $type;
        $description = trim($typeLabel . ' ' . (string) $entry->invoice_period . ($registrarInvoice !== '' ? ' · ' . $registrarInvoice : '')
            . ($classUnit !== null ? ' · ' . __('resale.mirror.domain_price_class', ['class' => (string) $reseller->user_class]) : ''));

        return new MirrorLine(
            sourceKey: self::KEY,
            morphClass: $entry->getMorphClass(),
            morphId: (int) $entry->id,
            organizationId: (int) $entry->organization_id,
            key: Sqid::encode(DomainAccountingEntry::class, $entry->id),
            voucherKey: $voucherKey,
            voucherNumber: $number,
            voucherDate: $start->endOfMonth()->startOfDay(),
            voucherStatus: MirrorLine::STATUS_ISSUED,
            isCreditNote: $isCreditNote,
            recipientCustomerId: (int) $reseller->customer_id,
            recipientKey: 'customer:' . (int) $reseller->customer_id,
            recipientName: $recipientName,
            articleKey: null,
            // Kein Artikel: die Position heißt wie die Domain, der Buchungstyp steht darunter.
            articleName: null,
            articleIsLicence: false,
            name: (string) $entry->description,
            description: $description !== '' ? $description : null,
            quantity: (float) $years,
            unitName: 'Jahr',
            unitNet: $total->withScale(4)->dividedBy($years),
            totalNet: $total->withScale(2),
            currency: $currency,
            serviceFrom: $start,
            serviceTo: $start->addMonthsNoOverflow($years * 12)->subDay(),
            voucherText: null,
            voucherTextHint: null,
            position: $position,
            permalink: route('domain-reseller.show', $reseller),
            previewUrl: null,
            voucherExternalId: $number,
        );
    }

    /**
     * @param  Collection<int, MirrorLine>  $lines
     * @return Collection<int, MirrorVoucher>
     */
    private function vouchers(Collection $lines): Collection {
        $vouchers = [];
        foreach ($lines->groupBy(static fn(MirrorLine $line): string => $line->voucherKey) as $voucherKey => $group) {
            /** @var MirrorLine $head */
            $head = $group->first();
            $from = $group->map(static fn(MirrorLine $l): ?CarbonImmutable => $l->serviceFrom)->filter()->min();
            $to = $group->map(static fn(MirrorLine $l): ?CarbonImmutable => $l->serviceTo)->filter()->max();
            $vouchers[] = new MirrorVoucher(
                sourceKey: self::KEY,
                voucherKey: (string) $voucherKey,
                voucherNumber: $head->voucherNumber,
                voucherDate: $head->voucherDate,
                voucherStatus: $head->voucherStatus,
                isCreditNote: false,
                recipientCustomerId: $head->recipientCustomerId,
                recipientName: $head->recipientName,
                voucherText: null,
                voucherTextHint: null,
                serviceFrom: $from instanceof CarbonImmutable ? $from : null,
                serviceTo: $to instanceof CarbonImmutable ? $to : null,
                permalink: $head->permalink,
                previewUrl: null,
                lines: array_values($group->all()),
            );
        }

        return collect($vouchers);
    }

    /** Laufzeit in Jahren aus INVOICE_PERIOD („1Y“, „2 years“, „12M“), sonst aus der Menge, sonst ein Jahr. */
    /**
     * Katalogzeilen des Domainanbieters für die Klassen der beteiligten Subuser und die TLDs der Buchungen,
     * jede Gültigkeit (Buchungen liegen in der Vergangenheit): Staffel → TLD-Schlüssel → Zeilen, neueste zuerst.
     *
     * @param  Collection<int, DomainAccountingEntry>  $entries
     * @return array<string, array<string, list<ResalePriceEntry>>>
     */
    private function classPrices(Organization $organization, Collection $entries): array {
        $classes = [];
        $products = [];
        foreach ($entries as $entry) {
            $class = strtoupper(trim((string) $entry->resellerAccount?->user_class));
            if ($class === '') {
                continue;
            }
            $classes[$class] = true;
            foreach (self::tldKeysOf($entry) as $key) {
                $products[$key] = true;
            }
        }
        if ($classes === [] || $products === []) {
            return [];
        }
        $prices = [];
        $rows = ResalePriceEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->whereIn('price_class', array_keys($classes))
            ->orderByDesc('valid_from')
            ->get();
        foreach ($rows as $row) {
            $key = ResalePriceEntry::domainProductKey((string) $row->product);
            if (isset($products[$key])) {
                $prices[(string) $row->price_class][$key][] = $row;
            }
        }

        return $prices;
    }

    /**
     * Klassenpreis je Jahr für eine Buchung: längster passender TLD-Schlüssel, am Buchungstag gültige Zeile.
     *
     * @param  array<string, array<string, list<ResalePriceEntry>>>  $prices
     */
    private static function classUnit(array $prices, DomainAccountingEntry $entry, DomainResellerAccount $reseller, CarbonImmutable $date): ?Money {
        $byTld = $prices[strtoupper(trim((string) $reseller->user_class))] ?? [];
        foreach (self::tldKeysOf($entry) as $key) {
            foreach ($byTld[$key] ?? [] as $row) {
                if ($row->valid_from->greaterThan($date) || ($row->valid_to !== null && $row->valid_to->lessThan($date))) {
                    continue;
                }
                return ($row->list_unit_price ?? $row->purchase_unit_price)->withScale(4);
            }
        }

        return null;
    }

    /**
     * TLD-Schlüssel der Domain einer Buchung, längster Suffix zuerst (`examplecouk`, `couk`, `uk`).
     *
     * @return list<string>
     */
    private static function tldKeysOf(DomainAccountingEntry $entry): array {
        $domain = mb_strtolower(trim((string) ($entry->domainProjection->external_domain ?? $entry->description)));
        $labels = array_values(array_filter(explode('.', $domain), static fn (string $label): bool => $label !== ''));
        $keys = [];
        for ($i = 1, $n = count($labels); $i < $n; $i++) {
            $keys[] = ResalePriceEntry::domainProductKey(implode('.', array_slice($labels, $i)));
        }

        return $keys;
    }

    /**
     * Restposition je Monatsbeleg: die gebuchte Monatsrechnung des Portals (Buchhaltungsspiegel) minus die
     * Domainpositionen — DNS-Zonen, Gebühren und andere Posten ohne Domain. Nur in Belegansichten, nie als
     * verknüpfbare Zeile im Vorschlagslauf.
     *
     * @param  Collection<int, MirrorLine>  $lines
     * @return Collection<int, MirrorLine>
     */
    private function withRemainders(Organization $organization, Collection $lines): Collection {
        if ($lines->isEmpty()) {
            return $lines;
        }
        $mirror = app(InvoiceMirror::class);
        $extra = [];
        foreach ($lines->groupBy(static fn(MirrorLine $line): string => $line->voucherKey) as $voucherKey => $group) {
            /** @var MirrorLine $head */
            $head = $group->first();
            if ($head->voucherNumber === null || preg_match('/^(\d+):/', (string) $voucherKey, $m) !== 1) {
                continue;
            }
            $booked = $mirror->bookedNet($organization, $head->voucherNumber);
            if ($booked === null) {
                continue;
            }
            $sum = Money::sum($group->map(static fn(MirrorLine $line): Money => $line->isCreditNote ? $line->totalNet->negated() : $line->totalNet), $head->currency, 4);
            $rest = $booked->withScale(4)->minus($sum);
            if (! $rest->isPositive() || $rest->withScale(2)->isZero()) {
                continue;
            }
            $extra[] = new MirrorLine(
                sourceKey: self::KEY,
                morphClass: (new DomainResellerAccount)->getTable(),
                morphId: (int) $m[1],
                organizationId: $head->organizationId,
                key: 'rest:' . $voucherKey,
                voucherKey: $head->voucherKey,
                voucherNumber: $head->voucherNumber,
                voucherDate: $head->voucherDate,
                voucherStatus: $head->voucherStatus,
                isCreditNote: false,
                recipientCustomerId: $head->recipientCustomerId,
                recipientKey: $head->recipientKey,
                recipientName: $head->recipientName,
                articleKey: null,
                articleName: null,
                articleIsLicence: false,
                name: (string) __('resale.mirror.domain_rest'),
                description: (string) __('resale.mirror.domain_rest_hint'),
                quantity: 1.0,
                unitName: null,
                unitNet: $rest->withScale(4),
                totalNet: $rest->withScale(2),
                currency: $head->currency,
                serviceFrom: null,
                serviceTo: null,
                voucherText: null,
                voucherTextHint: null,
                position: (int) $group->max(static fn(MirrorLine $line): int => $line->position) + 1,
                permalink: $head->permalink,
                previewUrl: null,
                voucherExternalId: $head->voucherExternalId,
            );
        }

        return $extra === [] ? $lines : $lines->concat($extra)->values();
    }

    private static function yearsOf(DomainAccountingEntry $entry): int {
        $period = mb_strtolower(trim((string) $entry->invoice_period));
        if (preg_match('/^(\d+)\s*(y|year|years|j|jahr|jahre)?$/', $period, $m) === 1) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/^(\d+)\s*(m|month|months|monat|monate)$/', $period, $m) === 1) {
            return max(1, intdiv((int) $m[1], 12));
        }
        $quantity = (int) round((float) $entry->quantity);

        return $quantity >= 1 && $quantity <= 10 ? $quantity : 1;
    }

    public function bookedNet(Organization $organization, string $voucherNumber): ?Money {
        return null; // das Journal kennt keine Rechnungssummen, nur Buchungen
    }
}
