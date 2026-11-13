<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTaxCheckService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev\TaxCheck;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, DatevTaxCheckKind, EuerCategory};
use App\Enums\Invoicing\IncomingInvoiceTaxCase;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingEvent, AccountingTaxCode};
use App\Models\Finance\{DatevBookingBatch, DatevBookingEvent};
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Models\Travel\Expense;
use App\Services\Accounting\{AccountingEventRecorder, ChartOfAccountsTemplateService, LedgerDatevExportService};
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\DatevBookingService;
use App\Support\MorphMap;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661).
 *
 * Durchsicht jedes Stapels, bevor er geschrieben wird — die Journal-Übergabe
 * ({@see LedgerDatevExportService}) wie der belegbasierte Stapel
 * ({@see DatevBookingService}). Sie baut auf den Automatikkonten aus MVP-1622
 * auf (Vorgabe-Kennzeichen am Konto) und erfindet keine eigene Kontenspalte.
 *
 * - **Fehler** (sperren den Export): Erlös- oder Aufwandszeile mit
 *   Steuerpflicht ohne Kennzeichen, innergemeinschaftliche Lieferung oder
 *   § 13b mit Partner im übrigen Gemeinschaftsgebiet ohne dessen USt-IdNr.,
 *   Buchung auf stillgelegtem Konto.
 * - **Hinweise** (zur Durchsicht): Was die Exportlogik selbst behebt
 *   (aufgehobene Automatik mit BU 40), ein Satz, der vom Kennzeichen des
 *   Automatikkontos abweicht, und der inländische § 13b ohne USt-IdNr. (dort
 *   genügt die Steuernummer).
 *
 * Die Prüfung ändert die Exportlogik nicht. Sperrt ein Fehler zu Unrecht
 * (Sonderfall der Kanzlei), gibt ihn eine berechtigte Person mit Begründung
 * frei; die Freigabe steht revisionssicher in der Ereigniskette
 * ({@see AccountingEvent} bzw. {@see DatevBookingEvent}) und gilt für genau
 * diesen Befund.
 */
class DatevTaxCheckService {
    /** Ereignis der Freigabe in der Kette des Buchungskerns (Journal-Übergabe). */
    public const string LEDGER_RELEASE_EVENT = 'accounting.datev_check_released';

    /** Ereignis der Freigabe in der Kette des DATEV-Stapels (belegbasiert). */
    public const string BATCH_RELEASE_EVENT = 'tax_check_released';

    /** Kategorie „steuerfreie innergemeinschaftliche Lieferung“. */
    private const string CATEGORY_INTRA_COMMUNITY = 'K';

    /** Kategorie „Steuerschuld des Empfängers“. */
    private const string CATEGORY_REVERSE_CHARGE = 'AE';

    /** Kennziffer 60: inländische Steuerschuld des Empfängers (§ 13b Abs. 5 UStG). */
    private const string DOMESTIC_REVERSE_CHARGE_FIELD = '60';

    public function __construct(
        private readonly LedgerDatevExportService $ledgerExport,
        private readonly DatevBookingService $bookings,
        private readonly AccountingEventRecorder $events,
    ) {}

    /** Durchsicht der Journal-Übergabe eines Zeitraums. */
    public function forLedger(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): DatevTaxCheckResult {
        $query = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value]);
        DateRange::whereDateBetween($query, 'booked_on', $from, $to);
        $entries = $query
            ->with(['lines.account.defaultTaxCode', 'lines.taxCode', 'lines.costCenter', 'source'])
            ->orderBy('journal_no')
            ->get();

        $taxAccounts = $this->taxAccountIds($organization);
        $findings = [];
        /** @var array<string, array{count: int, entry: AccountingEntry}> $suspended */
        $suspended = [];

        foreach ($entries as $entry) {
            $reference = (string) __('accounting.datev_check.reference.journal', ['no' => (string) ($entry->journal_no ?? $entry->id)]);
            $url = route('finance.accounting.journal.show', $entry);
            $claimed = [];
            foreach ($entry->lines as $line) {
                if ($line->taxCode instanceof AccountingTaxCode) {
                    $claimed = [...$claimed, ...array_filter([(int) $line->taxCode->tax_account_id, (int) $line->taxCode->input_tax_account_id])];
                }
            }
            $unclaimedTax = $entry->lines->first(static fn (AccountingEntryLine $line): bool => $line->taxCode === null
                && in_array((int) $line->accounting_account_id, $taxAccounts, true)
                && ! in_array((int) $line->accounting_account_id, $claimed, true)
                && ! $line->signedAmount()->isZero());

            $vatIdChecked = false;
            foreach ($entry->lines as $line) {
                $account = $line->account;
                if (! $account instanceof AccountingAccount) {
                    continue;
                }

                if (! $account->is_active) {
                    $findings[] = new DatevTaxCheckFinding(
                        key: 'ledger:' . DatevTaxCheckKind::InactiveAccount->value . ':line:' . $line->id,
                        kind: DatevTaxCheckKind::InactiveAccount,
                        blocking: true,
                        message: (string) __('accounting.datev_check.message.inactive_account', ['account' => $account->displayLabel()]),
                        reference: $reference,
                        url: $url,
                    );
                }

                $code = $line->taxCode;
                if (! $code instanceof AccountingTaxCode && $unclaimedTax instanceof AccountingEntryLine
                    && in_array($account->type, [AccountType::Income, AccountType::Expense], true)
                    && $account->default_tax_code_id === null) {
                    $findings[] = new DatevTaxCheckFinding(
                        key: 'ledger:' . DatevTaxCheckKind::MissingTaxCode->value . ':line:' . $line->id,
                        kind: DatevTaxCheckKind::MissingTaxCode,
                        blocking: true,
                        message: (string) __('accounting.datev_check.message.missing_tax_code', [
                            'account' => $account->displayLabel(),
                            'tax_account' => (string) $unclaimedTax->account?->number,
                        ]),
                        reference: $reference,
                        url: $url,
                    );
                }

                if (! $code instanceof AccountingTaxCode || $code->isTaxAccount((int) $line->accounting_account_id)) {
                    continue;
                }

                $vatIdNeed = $this->vatIdNeed($code);
                if ($vatIdNeed !== null && ! $vatIdChecked) {
                    $vatIdChecked = true;
                    if (! $this->ledgerPartnerHasVatId($entry, $line)) {
                        $findings[] = new DatevTaxCheckFinding(
                            key: 'ledger:' . DatevTaxCheckKind::MissingVatId->value . ':entry:' . $entry->id,
                            kind: DatevTaxCheckKind::MissingVatId,
                            blocking: $vatIdNeed === 'required',
                            message: (string) __($vatIdNeed === 'required' ? 'accounting.datev_check.message.missing_vat_id' : 'accounting.datev_check.message.missing_vat_id_domestic', [
                                'code' => $code->code,
                                'name' => $code->name,
                            ]),
                            reference: $reference,
                            url: $url,
                        );
                    }
                }

                $automatic = $account->defaultTaxCode;
                if ($automatic instanceof AccountingTaxCode && NumberHelper::comparePrecise((string) $automatic->rate, (string) $code->rate, 2) !== 0) {
                    $findings[] = new DatevTaxCheckFinding(
                        key: 'ledger:' . DatevTaxCheckKind::RateMismatch->value . ':line:' . $line->id,
                        kind: DatevTaxCheckKind::RateMismatch,
                        blocking: false,
                        message: (string) __('accounting.datev_check.message.rate_mismatch', [
                            'account' => $account->displayLabel(),
                            'automatic' => $automatic->code,
                            'automatic_rate' => $this->rate((string) $automatic->rate),
                            'code' => $code->code,
                            'rate' => $this->rate((string) $code->rate),
                        ]),
                        reference: $reference,
                        url: $url,
                    );
                }
            }

            // Was der Export selbst behebt, steht nur als Hinweis da: BU 40 je Konto gezählt.
            foreach ($this->ledgerExport->batchRows($entry) as $row) {
                if ($row['tax_key'] === LedgerDatevExportService::SUSPEND_AUTOMATIC) {
                    $suspended[$row['account']] ??= ['count' => 0, 'entry' => $entry];
                    $suspended[$row['account']]['count']++;
                }
            }
        }

        ksort($suspended, SORT_STRING);
        foreach ($suspended as $account => $info) {
            $findings[] = new DatevTaxCheckFinding(
                key: 'ledger:' . DatevTaxCheckKind::AutomaticSuspended->value . ':account:' . $account,
                kind: DatevTaxCheckKind::AutomaticSuspended,
                blocking: false,
                message: (string) __('accounting.datev_check.message.automatic_suspended', ['count' => $info['count'], 'account' => (string) $account]),
                reference: (string) __('accounting.datev_check.reference.journal', ['no' => (string) ($info['entry']->journal_no ?? $info['entry']->id)]),
                url: route('finance.accounting.journal.show', $info['entry']),
            );
        }

        return new DatevTaxCheckResult($this->sorted($findings), $this->releasedLedgerKeys($organization));
    }

    /** Durchsicht eines belegbasierten Stapels aus seinen gespeicherten Sätzen. */
    public function forBatch(DatevBookingBatch $batch, DatevBookingConfig $config): DatevTaxCheckResult {
        $organization = Organization::query()->find($batch->organization_id);
        if (! $organization instanceof Organization) {
            return new DatevTaxCheckResult([]);
        }
        $batch->loadMissing('sources');

        $models = $this->batchSources($batch);
        $rebuilt = [];
        foreach ($this->bookings->buildBookingRows(collect(array_values($models)), $config) as $row) {
            $rebuilt[$this->rowKey($row['source_type'], $row['source_id'], NumberHelper::toUSFormat($row['amount'], 2), $row['revenue_account'])] = $row;
        }
        $automatic = $this->automaticRates($organization, $config);
        $local = $this->localAccounts($organization);

        $findings = [];
        $vatIdChecked = [];
        foreach ($batch->sources as $source) {
            $model = $models[$source->source_type . ':' . $source->source_id] ?? null;
            $reference = (string) __('accounting.datev_check.reference.document', ['ref' => (string) $source->document_ref]);
            $url = $this->documentUrl($model);
            $amount = NumberHelper::toUSFormat((float) $source->amount, 2);
            $row = $rebuilt[$this->rowKey($source->source_type, (int) $source->source_id, $amount, (string) $source->revenue_account)] ?? null;
            $rate = NumberHelper::normalizeDecimalString($row !== null ? NumberHelper::toUSFormat((float) $row['tax_rate'], 2) : $this->modelRate($model));
            $sourceKey = $source->source_type . ':' . $source->source_id;

            foreach (array_unique([(string) $source->revenue_account, (string) $source->debtor_account]) as $number) {
                $account = $local[$number] ?? null;
                if ($account instanceof AccountingAccount && ! $account->is_active) {
                    $findings[] = new DatevTaxCheckFinding(
                        key: 'batch:' . DatevTaxCheckKind::InactiveAccount->value . ':' . $sourceKey . ':' . $number,
                        kind: DatevTaxCheckKind::InactiveAccount,
                        blocking: true,
                        message: (string) __('accounting.datev_check.message.inactive_account', ['account' => $account->displayLabel()]),
                        reference: $reference,
                        url: $url,
                    );
                }
            }

            $revenueAccount = (string) $source->revenue_account;
            $isAutomatic = array_key_exists($revenueAccount, $automatic) || ($row['automatic'] ?? false) === true;
            if (trim((string) $source->tax_key) === '' && NumberHelper::isPositivePrecise($rate, 2) && ! $isAutomatic) {
                $findings[] = new DatevTaxCheckFinding(
                    key: 'batch:' . DatevTaxCheckKind::MissingTaxCode->value . ':' . $sourceKey . ':' . $revenueAccount . ':' . $amount,
                    kind: DatevTaxCheckKind::MissingTaxCode,
                    blocking: true,
                    message: (string) __('accounting.datev_check.message.missing_tax_code_batch', [
                        'ref' => (string) $source->document_ref,
                        'rate' => $this->rate($rate),
                        'account' => $revenueAccount,
                    ]),
                    reference: $reference,
                    url: $url,
                );
            }

            if (array_key_exists($revenueAccount, $automatic) && NumberHelper::comparePrecise($automatic[$revenueAccount], $rate, 2) !== 0) {
                $findings[] = new DatevTaxCheckFinding(
                    key: 'batch:' . DatevTaxCheckKind::RateMismatch->value . ':' . $sourceKey . ':' . $revenueAccount . ':' . $amount,
                    kind: DatevTaxCheckKind::RateMismatch,
                    blocking: false,
                    message: (string) __('accounting.datev_check.message.rate_mismatch_batch', [
                        'ref' => (string) $source->document_ref,
                        'rate' => $this->rate($rate),
                        'account' => $revenueAccount,
                        'automatic_rate' => $this->rate($automatic[$revenueAccount]),
                    ]),
                    reference: $reference,
                    url: $url,
                );
            }

            if ($model instanceof Invoice && ! isset($vatIdChecked[$sourceKey])) {
                $vatIdChecked[$sourceKey] = true;
                $need = $this->invoiceVatIdNeed($organization, $model);
                if ($need !== null && trim((string) ($model->customer->vat_id ?? '')) === '') {
                    $findings[] = new DatevTaxCheckFinding(
                        key: 'batch:' . DatevTaxCheckKind::MissingVatId->value . ':' . $sourceKey,
                        kind: DatevTaxCheckKind::MissingVatId,
                        blocking: $need === 'required',
                        message: (string) __($need === 'required' ? 'accounting.datev_check.message.missing_vat_id_batch' : 'accounting.datev_check.message.missing_vat_id_batch_domestic', [
                            'ref' => (string) $source->document_ref,
                        ]),
                        reference: $reference,
                        url: $url,
                    );
                }
            }
        }

        return new DatevTaxCheckResult($this->sorted($findings), $this->releasedBatchKeys((int) $batch->organization_id));
    }

    /**
     * Offene Fehler der Journal-Übergabe begründet freigeben. Protokolliert in
     * der Ereigniskette des Buchungskerns; liefert die Zahl der Freigaben.
     */
    public function releaseLedger(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, string $reason, User $actor): int {
        $open = $this->forLedger($organization, $from, $to)->openErrors();
        if ($open === []) {
            return 0;
        }

        $this->events->record($organization, self::LEDGER_RELEASE_EVENT, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'reason' => $reason,
            'keys' => array_map(static fn (DatevTaxCheckFinding $finding): string => $finding->key, $open),
        ], null, $actor);

        return count($open);
    }

    /** Offene Fehler eines belegbasierten Stapels begründet freigeben (Kette des Stapels). */
    public function releaseBatch(DatevBookingBatch $batch, DatevBookingConfig $config, string $reason, User $actor): int {
        $open = $this->forBatch($batch, $config)->openErrors();
        if ($open === []) {
            return 0;
        }

        $batch->record(self::BATCH_RELEASE_EVENT, [
            'reason' => $reason,
            'keys' => array_map(static fn (DatevTaxCheckFinding $finding): string => $finding->key, $open),
        ], $actor);

        return count($open);
    }

    /**
     * Konten, auf denen Steuer steht: Steuerkonten der Kennzeichen und Konten der
     * EÜR-Kategorien Umsatz- und Vorsteuer.
     *
     * @return list<int>
     */
    private function taxAccountIds(Organization $organization): array {
        $ids = [];
        foreach (AccountingTaxCode::query()->where('organization_id', $organization->id)->get(['tax_account_id', 'input_tax_account_id']) as $code) {
            $ids[] = (int) $code->tax_account_id;
            $ids[] = (int) $code->input_tax_account_id;
        }
        foreach (AccountingAccount::query()->where('organization_id', $organization->id)
            ->whereIn('euer_category', [EuerCategory::IncomeVat->value, EuerCategory::InputTax->value])
            ->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Braucht das Kennzeichen die USt-IdNr. des Partners? `required` bei der
     * innergemeinschaftlichen Lieferung und der Steuerschuld des Empfängers
     * im übrigen Gemeinschaftsgebiet, `hint` beim inländischen § 13b.
     */
    private function vatIdNeed(AccountingTaxCode $code): ?string {
        $category = strtoupper(trim((string) $code->tax_category));

        return match (true) {
            $category === self::CATEGORY_INTRA_COMMUNITY => 'required',
            $category === self::CATEGORY_REVERSE_CHARGE => $code->ustva_base_field === self::DOMESTIC_REVERSE_CHARGE_FIELD ? 'hint' : 'required',
            $code->tax_case === IncomingInvoiceTaxCase::ReverseChargeEu => 'required',
            $code->tax_case === IncomingInvoiceTaxCase::ReverseCharge => 'hint',
            default => null,
        };
    }

    /** Dieselbe Frage für eine Rechnung ohne Steuer im belegbasierten Stapel. */
    private function invoiceVatIdNeed(Organization $organization, Invoice $invoice): ?string {
        if (! ($invoice->tax_amount?->isZero() ?? true)) {
            return null;
        }
        $zeroRated = app(SalesInvoiceAdapter::class)->zeroRatedCategory($organization, $invoice);

        return match (true) {
            $zeroRated === null => null,
            $zeroRated['category'] === self::CATEGORY_INTRA_COMMUNITY => 'required',
            $zeroRated['category'] === self::CATEGORY_REVERSE_CHARGE => $zeroRated['domestic'] ? 'hint' : 'required',
            default => null,
        };
    }

    /**
     * USt-IdNr. des Partners einer Buchung: Gegenpartei der Zeile, sonst der
     * Kunde der Ausgangsrechnung bzw. der Rechnungssteller des Eingangs.
     */
    private function ledgerPartnerHasVatId(AccountingEntry $entry, AccountingEntryLine $line): bool {
        $candidates = [];
        foreach ($entry->lines as $other) {
            if ($other->counterparty_type !== null && $other->counterparty_id !== null) {
                $candidates[] = $other->counterparty;
            }
        }
        $source = $entry->source;
        if ($source instanceof Invoice) {
            $candidates[] = $source->customer;
        } elseif ($source instanceof IncomingEInvoice) {
            if (trim((string) $source->seller_vat_id) !== '') {
                return true;
            }
            $candidates[] = $source->supplier;
        } elseif ($source instanceof Model) {
            $candidates[] = $source;
        }

        foreach ($candidates as $candidate) {
            if ($candidate instanceof Model && trim((string) $candidate->getAttribute('vat_id')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function releasedLedgerKeys(Organization $organization): array {
        return $this->releasedKeys(AccountingEvent::query()
            ->where('organization_id', $organization->id)
            ->where('event', self::LEDGER_RELEASE_EVENT)
            ->get());
    }

    /** @return list<string> */
    private function releasedBatchKeys(int $organizationId): array {
        return $this->releasedKeys(DatevBookingEvent::query()
            ->where('organization_id', $organizationId)
            ->where('event', self::BATCH_RELEASE_EVENT)
            ->get());
    }

    /**
     * @param  Collection<int, AccountingEvent>|Collection<int, DatevBookingEvent>  $events
     * @return list<string>
     */
    private function releasedKeys(Collection $events): array {
        $keys = [];
        foreach ($events as $event) {
            $payload = is_array($event->payload) ? $event->payload : [];
            foreach ((array) ($payload['keys'] ?? []) as $key) {
                $keys[(string) $key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Quellbelege des Stapels, je Typ in einer Abfrage.
     *
     * @return array<string, Invoice|Expense>
     */
    private function batchSources(DatevBookingBatch $batch): array {
        $models = [];
        foreach ([Invoice::class => ['customer'], Expense::class => ['category']] as $class => $with) {
            $alias = MorphMap::alias($class);
            $ids = $batch->sources->where('source_type', $alias)->pluck('source_id')->map(intval(...))->unique()->values()->all();
            if ($ids === []) {
                continue;
            }
            foreach ($class::query()->where('organization_id', $batch->organization_id)->with($with)->whereIn('id', $ids)->get() as $model) {
                $models[$alias . ':' . $model->getKey()] = $model;
            }
        }

        return $models;
    }

    private function rowKey(string $type, int $id, string $amount, string $account): string {
        return $type . ':' . $id . ':' . $amount . ':' . $account;
    }

    /** Steuersatz des Belegs, falls der Satz nicht aus dem Stapel abgeleitet werden kann. */
    private function modelRate(?Model $model): string {
        $rate = $model instanceof Invoice || $model instanceof Expense ? $model->tax_rate : null;

        return $rate !== null ? NumberHelper::toUSFormat((float) $rate->getNumericValue(), 2) : '0.00';
    }

    /**
     * Automatikkonten mit ihrem Satz: aus dem lokalen Kontenplan (MVP-1622),
     * ohne lokalen Kontenplan aus der Vorlage des eingestellten Kontenrahmens.
     *
     * @return array<string, numeric-string> DATEV-Konto → Satz
     */
    private function automaticRates(Organization $organization, DatevBookingConfig $config): array {
        $rates = [];
        $accounts = AccountingAccount::query()->where('organization_id', $organization->id)->with('defaultTaxCode')->get();
        if ($accounts->isNotEmpty()) {
            foreach ($accounts as $account) {
                if ($account->defaultTaxCode instanceof AccountingTaxCode) {
                    $rates[$this->datevNumber($account)] = NumberHelper::normalizeDecimalString((string) $account->defaultTaxCode->rate);
                }
            }

            return $rates;
        }

        $template = (array) (app(ChartOfAccountsTemplateService::class)->available()[$config->skr->value] ?? []);
        $codeRates = [];
        foreach ((array) ($template['tax_codes'] ?? []) as $code) {
            $codeRates[(string) $code['code']] = (string) $code['rate'];
        }
        foreach ((array) ($template['accounts'] ?? []) as $definition) {
            $code = (string) ($definition['datev_automatic'] ?? '');
            if ($code !== '' && isset($codeRates[$code])) {
                $rates[(string) $definition['number']] = NumberHelper::normalizeDecimalString($codeRates[$code]);
            }
        }

        return $rates;
    }

    /** @return array<string, AccountingAccount> DATEV-Konto und eigene Nummer → Konto */
    private function localAccounts(Organization $organization): array {
        $map = [];
        foreach (AccountingAccount::query()->where('organization_id', $organization->id)->get() as $account) {
            $map[(string) $account->number] = $account;
            $map[$this->datevNumber($account)] = $account;
        }

        return $map;
    }

    private function datevNumber(AccountingAccount $account): string {
        return (string) ($account->datev_account !== null && $account->datev_account !== '' ? $account->datev_account : $account->number);
    }

    private function documentUrl(?Model $model): ?string {
        return match (true) {
            $model instanceof Invoice => route('invoices.show', $model),
            $model instanceof Expense => route('expenses.edit', $model),
            default => null,
        };
    }

    private function rate(string $rate): string {
        return NumberHelper::toGermanFormat((float) $rate, 2);
    }

    /**
     * Fehler zuerst, dann Hinweise; innerhalb in Belegreihenfolge.
     *
     * @param  list<DatevTaxCheckFinding>  $findings
     * @return list<DatevTaxCheckFinding>
     */
    private function sorted(array $findings): array {
        $errors = array_values(array_filter($findings, static fn (DatevTaxCheckFinding $finding): bool => $finding->blocking));
        $hints = array_values(array_filter($findings, static fn (DatevTaxCheckFinding $finding): bool => ! $finding->blocking));

        return [...$errors, ...$hints];
    }
}
