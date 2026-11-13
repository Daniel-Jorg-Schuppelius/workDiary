<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesInvoiceAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind, SettlementKind, TaxCodeDirection};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Accounting\{AccountingEntry, AccountingOpenItem, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Accounting\{FiscalYearService, JournalService};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine, PostingRuleResolver, PrepaymentAdvance};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\Import\InvoiceSpec;
use App\Services\Invoicing\TaxResolver;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{NumberHelper, VatNumberHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * Ausgangsrechnung → Forderung an Erlös und Umsatzsteuer (Feature 125, MVP-673).
 *
 * Die Steueraufteilung kommt aus dem eingefrorenen `tax_breakdown` des Belegs:
 * je Steuersatz eine Erlös- und eine Steuerzeile. Der Adapter rechnet nichts
 * nach — täte er es, gäbe es zwei Wahrheiten über denselben Beleg.
 * Angerechnete Online-Vorauszahlungen löst er als erhaltene Anzahlung auf
 * (MVP-1542): Anzahlung und ihre Umsatzsteuer an Forderung, so dass die
 * Steuer der Rechnung mit der Steuer der Zahlung genau einmal entsteht.
 * Ein Stornobeleg gleicht genau den offenen Posten der stornierten Rechnung
 * aus (MVP-1545), nie den ältesten des Kunden.
 */
class SalesInvoiceAdapter extends AbstractPostingAdapter {
    /** Kennziffer der Umsätze, für die der Empfänger die Steuer schuldet (§ 13b Abs. 5 UStG). */
    public const string DOMESTIC_REVERSE_CHARGE_FIELD = '60';

    public function __construct(
        PostingRuleResolver $rules,
        private readonly PrepaymentAdvance $advances,
        private readonly JournalService $journal,
        private readonly FiscalYearService $fiscalYears,
    ) {
        parent::__construct($rules);
    }

    public function kind(): PostingSourceKind {
        return PostingSourceKind::SalesInvoice;
    }

    /**
     * Eine Erstattung nach dem Buchen ändert, was die Rechnung als Anzahlung
     * auflöst. Nach dem Storno nicht: Er gibt die Vorauszahlungen frei, und der
     * Stornobeleg dreht die gebuchte Auflösung zurück.
     */
    public function changedSincePosting(Model $source, AccountingEntry $entry): bool {
        assert($source instanceof Invoice);
        $booked = ((array) $entry->snapshot)[PrepaymentAdvance::SNAPSHOT_KEY] ?? null;
        if (! is_array($booked) || $source->type === Invoice::TYPE_CANCELLATION || $source->status === InvoiceStatus::Cancelled) {
            return false;
        }
        $organization = Organization::query()->find($source->organization_id);
        if (! $organization instanceof Organization) {
            return false;
        }

        $gross = static fn (array $credits): array => array_map(
            static fn (mixed $credit): string => is_array($credit) && is_numeric($credit['gross'] ?? null) ? (int) ($credit['id'] ?? 0) . ':' . NumberHelper::roundPrecise((string) $credit['gross'], 2) : '',
            array_values($credits),
        );

        return $gross($booked) !== $gross($this->advances->resolvedBy($organization, $source));
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $invoices */
        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            // Per Stornobeleg storniert bleibt die Rechnung zu buchen: Der Stornobeleg dreht sie zurück (MVP-1545).
            ->where(fn (Builder $query) => $query->whereIn('status', Invoice::ISSUED_STATUSES)
                ->orWhere(fn (Builder $query) => $query->where('status', InvoiceStatus::Cancelled->value)->whereHas('cancellations')))
            // MVP-707: Altrechnungen sind Eröffnungs-OP ohne Journalbuchung —
            // ihr Erlös wurde im Vorsystem gebucht.
            ->where('number_source', '!=', InvoiceSpec::NUMBER_SOURCE)
            ->whereNotNull('issued_on')
            ->whereDate('issued_on', '>=', $from->toDateString())
            ->whereDate('issued_on', '<=', $to->toDateString())
            ->with('customer')
            ->orderBy('issued_on')
            ->get();

        return $invoices;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof Invoice);

        $issuedOn = CarbonImmutable::parse($source->issued_on ?? now())->startOfDay();
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        if ($this->alreadyHandedOver($source)) {
            $blockers[] = (string) __('accounting.inbox.blocker.handed_over');
        }

        $breakdown = is_array($source->tax_breakdown) ? $source->tax_breakdown : [];
        if ($breakdown === []) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_tax_breakdown');
        }

        // Angerechnete Vorauszahlungen (MVP-1542); der Stornobeleg dreht die Auflösung der Rechnung zurück.
        $credits = $source->type === Invoice::TYPE_CANCELLATION
            ? $this->advances->reversedBy($organization, $source)
            : $this->advances->resolvedBy($organization, $source);
        $total = $source->total?->getAmount() ?? '0.00';
        $prepaid = NumberHelper::sumPrecise(array_column($credits, 'gross'), 2);
        if ($credits !== [] && $source->type !== Invoice::TYPE_CANCELLATION && NumberHelper::comparePrecise($prepaid, $total, 2) > 0) {
            $blockers[] = (string) __('accounting.inbox.blocker.prepayment_exceeds_invoice');
        }
        if ($credits !== [] && ($foreign = $this->foreignCurrencyBlocker($organization, $source->currency)) !== null) {
            $blockers[] = $foreign;
        }

        $receivable = $this->rule($organization, PostingAccountRole::Receivable, [], $issuedOn);
        $open = NumberHelper::subtractPrecise($total, $prepaid, 2);

        // Der Stornobeleg mindert die Forderung der stornierten Rechnung, nicht irgendeine des Kunden (MVP-1545).
        $cancelled = $source->type === Invoice::TYPE_CANCELLATION && $source->parent_invoice_id !== null
            ? Invoice::query()->where('organization_id', $organization->id)->find($source->parent_invoice_id)
            : null;
        $settles = [];
        if ($cancelled instanceof Invoice && NumberHelper::isNegativePrecise($open)) {
            $blocker = $this->cancelledInvoiceBlocker($organization, $cancelled, NumberHelper::negatePrecise($open));
            if ($blocker !== null) {
                $blockers[] = $blocker;
            }
            $settles = [
                'settles_source_type' => $cancelled->getMorphClass(),
                'settles_source_id' => (int) $cancelled->id,
                'settlement_kind' => SettlementKind::Cancellation->value,
            ];
        }

        if ($receivable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Receivable);
        } elseif ($credits === [] || ! NumberHelper::isZeroPrecise($open)) {
            // Die Forderung ist der offene Rest; voll vorausbezahlt entsteht kein offener Posten.
            $line = $this->line(
                PostingAccountRole::Receivable,
                $receivable,
                $open,
                '0.00',
                $source->number,
                MorphMap::alias(Customer::class),
                $source->customer_id,
            );
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $receivable->versionTag();
            }
        }

        foreach ($breakdown as $group) {
            // Die Aufschlüsselung trägt den Satz als Zahl (19), die Regeln als Zeichenkette (19.00).
            $rate = is_numeric($group['rate'] ?? null) ? NumberHelper::roundPrecise((string) $group['rate'], 2) : '0.00';
            // Die Aufschlüsselung liegt als JSON vor; Money normalisiert die
            // Rohwerte ohne Float-Zwischenschritt (Vollscan 2026-08-23, C1).
            $net = Money::of((string) ($group['net'] ?? '0'), $source->documentCurrency())->getAmount();
            $tax = Money::of((string) ($group['tax'] ?? '0'), $source->documentCurrency())->getAmount();
            $context = ['tax_rate' => $rate];

            $taxed = ! NumberHelper::isZeroPrecise($tax);
            $taxRule = $taxed ? $this->rule($organization, PostingAccountRole::TaxOutput, $context, $issuedOn) : null;
            $zeroRated = $taxed ? null : $this->zeroRatedTaxCode($organization, $source, $issuedOn);

            // Ein Umsatz ohne Steuer findet über sein Kennzeichen ein eigenes Erlöskonto (MVP-1622).
            $revenue = $this->rule($organization, PostingAccountRole::Revenue, $context + ($zeroRated !== null ? ['tax_code' => $zeroRated->code] : []), $issuedOn);
            if ($revenue === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Revenue, $context);
            } else {
                // Kennzeichen und Steuer stehen an der Erlöszeile: das der Regel, sonst das
                // des Steuersatzes, bei 0 % das der Steuerkategorie des Belegs (steuerfrei,
                // innergemeinschaftlich, Reverse Charge, Ausfuhr).
                $taxCodeId = $revenue->accounting_tax_code_id
                    ?? ($taxed ? $taxRule?->accounting_tax_code_id : $zeroRated?->id);
                $line = $this->line(
                    PostingAccountRole::Revenue,
                    $revenue,
                    '0.00',
                    $net,
                    $source->number,
                    $taxCodeId !== null ? MorphMap::alias(Customer::class) : null,
                    $taxCodeId !== null ? $source->customer_id : null,
                )?->withTax($taxCodeId, $taxed ? $tax : null);
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                    $ruleVersions[] = $revenue->versionTag();
                }
            }

            // Steuerzeile nur, wenn der Beleg auch Steuer ausweist.
            if (! $taxed) {
                continue;
            }

            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxOutput, $context);

                continue;
            }

            $line = $this->line(PostingAccountRole::TaxOutput, $taxRule, '0.00', $tax, $source->number)?->withTax(null, null);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $taxRule->versionTag();
            }
        }

        foreach ($credits as $credit) {
            $paidOn = CarbonImmutable::parse($credit['paid_on']);
            $context = ['tax_rate' => $credit['rate']];
            ['advance' => $advanceRule, 'tax' => $taxRule] = $this->advances->rules($organization, $credit['rate'], $paidOn);
            $memo = (string) __('accounting.inbox.memo.prepayment_resolved', ['number' => (string) $source->number, 'date' => $paidOn->format('d.m.Y')]);
            if ($advanceRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::AdvanceReceived, $context);
            } else {
                // Die Auflösung mindert Bemessungsgrundlage und Steuer der Rechnung um die schon versteuerte Anzahlung.
                $line = $this->line(PostingAccountRole::AdvanceReceived, $advanceRule, $credit['net'], '0.00', $memo, MorphMap::alias(Customer::class), $credit['customer_id'])
                    ?->withTax(
                        $advanceRule->accounting_tax_code_id ?? (NumberHelper::isZeroPrecise($credit['tax']) ? null : $taxRule?->accounting_tax_code_id),
                        NumberHelper::isZeroPrecise($credit['tax']) ? null : NumberHelper::negatePrecise($credit['tax']),
                    );
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                    $ruleVersions[] = $advanceRule->versionTag();
                }
            }
            if (NumberHelper::isZeroPrecise($credit['tax'])) {
                continue;
            }
            if ($taxRule === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxOutput, $context);

                continue;
            }
            // Die Steuer der Anzahlung entstand mit der Zahlung; hier mindert sie die Steuer der Rechnung.
            $line = $this->line(PostingAccountRole::TaxOutput, $taxRule, $credit['tax'], '0.00', $memo)?->withTax(null, null);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $taxRule->versionTag();
            }
        }

        // Fremdwährung zum Monatskurs (MVP-1012); ohne Kurs bleibt der Beleg in der Inbox.
        $fx = $this->inBaseCurrency($organization, $source->currency, $issuedOn, $lines);
        if ($fx['blocker'] !== null) {
            $blockers[] = $fx['blocker'];
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $issuedOn,
            memo: (string) __('accounting.inbox.memo.sales_invoice', [
                'number' => (string) $source->number,
                // Über die ID statt die Relation: eine Rechnung ohne Kunde ist
                // Altbestand, kein Grund für einen leeren Buchungstext.
                'customer' => (string) (Customer::query()->whereKey($source->customer_id)->value('name') ?? '—'),
            ]),
            lines: $fx['lines'],
            blockers: array_values(array_unique($blockers)),
            documentOn: $issuedOn,
            documentReference: (string) $source->number,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: (string) $source->number,
            // Ohne Fälligkeit hätte der offene Posten keine Altersstruktur.
            extra: $fx['extra'] + ['due_date' => $source->due_on?->toDateString()] + ($credits !== [] ? [PrepaymentAdvance::SNAPSHOT_KEY => $credits] : []) + $settles,
        );
    }

    /**
     * Steuerkategorie eines Umsatzes ohne Steuer (EN 16931) aus dem
     * eingefrorenen Steuerkontext des Belegs — dieselbe Kategorie, die
     * E-Rechnung und Befreiungshinweis lesen: EU-B2B ist bei Material eine
     * innergemeinschaftliche Lieferung (K), sonst eine sonstige Leistung mit
     * Steuerschuld des Empfängers (AE); Drittland ist bei Material eine Ausfuhr
     * (G), sonst nicht steuerbar (O, seit MVP-1684 vom TaxResolver eingefroren;
     * ältere Belege tragen dort G). Alles andere steuerfrei (E). `AE` an einen
     * Kunden im Inland ist ein steuerpflichtiger Umsatz, für den der Empfänger
     * die Steuer schuldet (§ 13b Abs. 5 UStG, Kennziffer 60, MVP-1622).
     * Kleinunternehmer melden keine Kennziffern (null), maßgeblich ist der Stand
     * beim Ausstellen. Der belegbasierte DATEV-Export wählt Konto und Schlüssel
     * über dieselbe Kategorie (MVP-1623).
     *
     * @return array{category: string, domestic: bool}|null
     */
    public function zeroRatedCategory(Organization $organization, Invoice $invoice): ?array {
        if (app(XRechnungGenerator::class)->sellerData($invoice)['small_business']) {
            return null;
        }

        $frozen = (string) data_get($invoice->tax_context, 'category', $invoice->is_reverse_charge ? 'AE' : '');
        $goods = $invoice->category === Invoice::CATEGORY_MATERIAL;
        $domestic = $frozen === 'AE' && $this->isDomestic($organization, $invoice);

        return [
            'category' => match ($frozen) {
                'AE' => $domestic ? 'AE' : ($goods ? 'K' : 'AE'),
                'K' => 'K',
                'G' => $goods ? 'G' : 'O',
                'O' => 'O',
                default => 'E',
            },
            'domestic' => $domestic,
        ];
    }

    /** Steuerkennzeichen eines Umsatzes ohne Steuer zu seiner Kategorie ({@see zeroRatedCategory()}). */
    private function zeroRatedTaxCode(Organization $organization, Invoice $invoice, CarbonImmutable $on): ?AccountingTaxCode {
        $zeroRated = $this->zeroRatedCategory($organization, $invoice);
        if ($zeroRated === null) {
            return null;
        }
        ['category' => $category, 'domestic' => $domestic] = $zeroRated;

        return AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('direction', '!=', TaxCodeDirection::SelfAssessed->value)
            ->where('tax_category', $category)
            // Kennziffer 60 trennt die inländische Steuerschuld des Empfängers von der sonstigen Leistung ins EU-Ausland (21).
            ->when($category === 'AE', fn ($query) => $domestic
                ? $query->where('ustva_base_field', self::DOMESTIC_REVERSE_CHARGE_FIELD)
                : $query->where(fn ($inner) => $inner->whereNull('ustva_base_field')->orWhere('ustva_base_field', '!=', self::DOMESTIC_REVERSE_CHARGE_FIELD)))
            ->validOn($on)
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Kunde im Land der Organisation: als Empfänger nach § 13b geführt, sonst nach
     * dem Land seiner USt-IdNr. bzw. seiner Anschrift (ohne beides: Inland).
     */
    private function isDomestic(Organization $organization, Invoice $invoice): bool {
        $customer = Customer::query()->find($invoice->customer_id);
        if (! $customer instanceof Customer || (bool) $customer->is_reverse_charge_recipient) {
            return $customer instanceof Customer;
        }
        $vatId = trim((string) $customer->vat_id);
        $country = $vatId !== ''
            ? VatNumberHelper::getCountryForVatPrefix((string) VatNumberHelper::extractCountryCode(VatNumberHelper::normalize($vatId)))?->value
            : null;
        $country ??= strtoupper(trim((string) $customer->country));

        return $country === '' || $country === app(TaxResolver::class)->sellerCountry($organization);
    }

    /**
     * Der Stornobeleg bucht erst nach seiner Rechnung, und nur solange deren
     * Posten den Betrag noch trägt — sonst liefe sein Ausgleich ins Leere.
     * Ohne Posten (Rechnung vor dem Buchhaltungsstart, Altrechnung, Konto
     * ohne offene Posten) gibt es nichts auszugleichen.
     *
     * @param  numeric-string  $amount
     */
    private function cancelledInvoiceBlocker(Organization $organization, Invoice $cancelled, string $amount): ?string {
        $entry = $this->journal->activeEntryForSource($organization, $this->sourceKey($cancelled));
        if (! $entry instanceof AccountingEntry || ! $entry->status->isPosted()) {
            $bookable = $cancelled->number_source !== InvoiceSpec::NUMBER_SOURCE
                && $cancelled->issued_on !== null
                && ! $this->alreadyHandedOver($cancelled)
                && $this->fiscalYears->periodFor($organization, CarbonImmutable::parse($cancelled->issued_on)->startOfDay()) !== null;

            return $bookable ? (string) __('accounting.inbox.blocker.cancelled_invoice_unposted', ['number' => (string) $cancelled->number]) : null;
        }

        $item = AccountingOpenItem::query()->where('organization_id', $organization->id)->where('accounting_entry_id', $entry->id)->orderBy('id')->first();
        $openAmount = $item?->open_amount?->getAmount() ?? '0.00';
        if ($item instanceof AccountingOpenItem && NumberHelper::comparePrecise($amount, $openAmount, 2) > 0) {
            return (string) __('accounting.inbox.blocker.cancelled_invoice_settled', ['number' => (string) $cancelled->number, 'open' => $item->open_amount?->format() ?? $openAmount]);
        }

        return null;
    }
}
