<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Enums\Finance\DatevBatchStatus;
use App\Models\{Expense, Invoice, Organization, User};
use App\Models\Finance\{DatevBookingBatch, DatevBookingEvent, DatevBookingSource};
use App\Services\Concerns\ResolvesActorId;
use App\Services\Export\ExportRunner;
use App\Services\Finance\Datev\{DatevBookingAdapter, DatevBookingConfig};
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\CryptoHelper;
use DateTimeImmutable;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\{DB, Storage};

/**
 * DATEV-Buchungsstapel (Feature 045): stellt gestellte Rechnungen/Gutschriften
 * (optional Spesen) eines Zeitraums als prüfbaren DATEV-V700-Buchungsstapel
 * zusammen. Ablauf: collectBookingReady() → createDraft() → preflight() → finalize().
 *
 * Doppel-Übergabe-Schutz: Quellen in einem aktiven Stapel (Draft ODER exportiert)
 * sind gesperrt (MVP-334: Teilauswahl je Zeitraum); finalize() prüft zusätzlich
 * hart gegen fremde exportierte Stapel. Extern geführte Kunden (BillingMode
 * external) gehören NICHT in den Stapel (Preflight-Warnung). Storno (MVP-334):
 * bereits übergebene stornierte Rechnungen werden als Generalumkehr nachgereicht.
 * Jeder Statuswechsel schreibt ein {@see DatevBookingEvent} (Hash-Kette, audit:verify).
 *
 * @phpstan-type BookingRow array{
 *   source_type: class-string,
 *   source_id: int,
 *   debtor_account: string,
 *   revenue_account: string,
 *   soll_haben: string,
 *   amount: float,
 *   tax_rate: float,
 *   tax_key: ?string,
 *   document_ref: string,
 *   text: string,
 *   date: string,
 *   is_credit_note: bool,
 *   is_reversal: bool
 * }
 */
class DatevBookingService {
    use ResolvesActorId;

    public const DISK = ExportRunner::DISK;

    public const BASE_PATH = 'exports/finance/datev';

    public function __construct(
        private readonly BillingModeResolver $billingModeResolver = new BillingModeResolver(),
        private readonly DatevBookingAdapter $adapter = new DatevBookingAdapter(),
    ) {}

    /**
     * Sammelt buchungsreife Rechnungen (Pflicht), optional freigegebene Spesen
     * des Zeitraums sowie optional Storno-Nachreichungen (Generalumkehr) —
     * jeweils nur Quellen, die noch in keinem aktiven Stapel hängen und nicht
     * extern geführt werden.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @return Collection<int, Invoice|Expense>
     */
    public function collectBookingReady(Organization $organization, array $period, bool $includeExpenses = false, bool $includeReversals = false): Collection {
        /** @var Collection<int, Invoice|Expense> $result */
        $result = $this->collectInvoices($organization, $period);

        if ($includeExpenses) {
            $result = $result->concat($this->collectExpenses($organization, $period));
        }

        if ($includeReversals) {
            $result = $result->concat($this->collectReversals($organization, $period));
        }

        return $result->values();
    }

    /**
     * Buchungsreife Rechnungen: Status gestellt (issued) oder bezahlt (paid),
     * Belegdatum (issued_on) im Zeitraum, nicht storniert, NICHT bereits in
     * einem aktiven Stapel und NICHT extern geführt.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @return Collection<int, Invoice>
     */
    private function collectInvoices(Organization $organization, array $period): Collection {
        $query = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->whereNotNull('issued_on')
            ->with('customer');

        $this->applyPeriod($query, 'issued_on', $period);
        $this->excludeAlreadyBooked($query, Invoice::class);

        return $query->orderBy('issued_on')->orderBy('id')->get()
            ->reject(fn(Invoice $invoice): bool => $this->isExternallyLed($invoice))
            ->values();
    }

    /**
     * Freigegebene Spesen (Status approved/reimbursed), Belegdatum im Zeitraum,
     * nicht bereits in einem aktiven Stapel.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @return Collection<int, Expense>
     */
    private function collectExpenses(Organization $organization, array $period): Collection {
        $query = Expense::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['approved', 'reimbursed'])
            ->with('category');

        $this->applyPeriod($query, 'date', $period);
        $this->excludeAlreadyBooked($query, Expense::class);

        return $query->orderBy('date')->orderBy('id')->get();
    }

    /**
     * Storno-Nachreichungen (MVP-334): stornierte Rechnungen, die bereits in
     * einem EXPORTIERTEN Stapel übergeben wurden (Original-Satz vorhanden) und
     * deren Generalumkehr-Satz noch in keinem aktiven Stapel hängt. Zeitraum-
     * Anker ist das Stornodatum (cancelled_at).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @return Collection<int, Invoice>
     */
    private function collectReversals(Organization $organization, array $period): Collection {
        $query = Invoice::query()
            ->where('organization_id', $organization->id)
            ->where('status', Invoice::STATUS_CANCELLED)
            ->whereNotNull('cancelled_at')
            ->with('customer');

        if (! empty($period['from'])) {
            $query->where('cancelled_at', '>=', Carbon::parse($period['from'])->startOfDay());
        }
        if (! empty($period['to'])) {
            $query->where('cancelled_at', '<=', Carbon::parse($period['to'])->endOfDay());
        }

        // Original muss übergeben worden sein (exportierter Stapel, kein GU-Satz) …
        $query->whereExists(function ($sub): void {
            $sub->from('datev_booking_sources')
                ->join('datev_booking_batches', 'datev_booking_batches.id', '=', 'datev_booking_sources.datev_booking_batch_id')
                ->whereColumn('datev_booking_sources.source_id', 'invoices.id')
                ->where('datev_booking_sources.source_type', Invoice::class)
                ->where('datev_booking_sources.is_reversal', false)
                ->where('datev_booking_batches.status', DatevBatchStatus::Exported->value)
                ->whereNull('datev_booking_batches.deleted_at');
        });

        // … und der Generalumkehr-Satz darf noch in keinem aktiven Stapel hängen.
        $query->whereNotExists(function ($sub): void {
            $sub->from('datev_booking_sources')
                ->join('datev_booking_batches', 'datev_booking_batches.id', '=', 'datev_booking_sources.datev_booking_batch_id')
                ->whereColumn('datev_booking_sources.source_id', 'invoices.id')
                ->where('datev_booking_sources.source_type', Invoice::class)
                ->where('datev_booking_sources.is_reversal', true)
                ->whereNull('datev_booking_batches.deleted_at');
        });

        return $query->orderBy('cancelled_at')->orderBy('id')->get();
    }

    /**
     * Bildet die Buchungssätze aus den Quellen — testbar ohne DB-Persistenz.
     * Je Rechnung ein Debitor-Satz: Soll Debitorenkonto an Haben Erlöskonto +
     * BU-Schlüssel (Bruttobetrag). Gutschrift umgekehrt (Soll/Haben getauscht).
     * Stornierte Rechnungen (MVP-334) werden als Generalumkehr-Satz gebildet:
     * identische Konten/S-H wie das Original, das GU-Kennzeichen kehrt die
     * Buchung DATEV-seitig um.
     *
     * @param  Collection<int, Invoice|Expense>  $sources
     * @return list<array{source_type: class-string, source_id: int, debtor_account: string, revenue_account: string, soll_haben: string, amount: float, tax_rate: float, tax_key: ?string, document_ref: string, text: string, date: string, is_credit_note: bool, is_reversal: bool}>
     */
    public function buildBookingRows(Collection $sources, DatevBookingConfig $config): array {
        $rows = [];

        foreach ($sources as $source) {
            if ($source instanceof Invoice) {
                // Phase 23 (MVP-243): Mischsatz-Belege werden nach dem
                // eingefrorenen tax_breakdown gesplittet — je Satz eine
                // Buchungszeile (statt nur Kopfsatz, Folge-Lücke 2).
                foreach ($this->invoiceRows($source, $config) as $row) {
                    $rows[] = $row;
                }

                continue;
            }
            $rows[] = $this->expenseRow($source, $config);
        }

        return $rows;
    }

    /**
     * @return list<array{source_type: class-string, source_id: int, debtor_account: string, revenue_account: string, soll_haben: string, amount: float, tax_rate: float, tax_key: ?string, document_ref: string, text: string, date: string, is_credit_note: bool, is_reversal: bool}>
     */
    private function invoiceRows(Invoice $invoice, DatevBookingConfig $config): array {
        $breakdown = (array) ($invoice->tax_breakdown ?? []);
        if (count($breakdown) <= 1) {
            return [$this->invoiceRow($invoice, $config)];
        }

        $base = $this->invoiceRow($invoice, $config);
        $rows = [];
        foreach ($breakdown as $slice) {
            $rate = (float) ($slice['rate'] ?? 0);
            $gross = round((float) ($slice['net'] ?? 0) + (float) ($slice['tax'] ?? 0), 2);
            if ($gross === 0.0) {
                continue;
            }
            $rows[] = [
                ...$base,
                'revenue_account' => $config->revenueAccountFor($rate),
                'amount' => round(abs($gross), 2),
                'tax_rate' => $rate,
                'tax_key' => $config->taxKeyFor($rate),
            ];
        }

        return $rows !== [] ? $rows : [$base];
    }

    /**
     * @return array{source_type: class-string, source_id: int, debtor_account: string, revenue_account: string, soll_haben: string, amount: float, tax_rate: float, tax_key: ?string, document_ref: string, text: string, date: string, is_credit_note: bool, is_reversal: bool}
     */
    private function invoiceRow(Invoice $invoice, DatevBookingConfig $config): array {
        $taxRate = (float) $invoice->tax_rate;
        $gross = (float) $invoice->total;
        $isCredit = $invoice->isCreditNote();
        // Storno-Nachreichung (MVP-334): identischer Satz wie das Original,
        // die Umkehr übernimmt das Generalumkehr-Kennzeichen im Export.
        $isReversal = $invoice->isCancelled();

        // Standardrechnung: Soll Debitor an Haben Erlös (S).
        // Gutschrift: Umkehrung ⇒ Haben Debitor an Soll Erlös (H).
        $sollHaben = $isCredit ? 'H' : 'S';

        return [
            'source_type' => Invoice::class,
            'source_id' => (int) $invoice->id,
            'debtor_account' => $config->debtorAccountFor($invoice->customer),
            'revenue_account' => $config->revenueAccountFor($taxRate),
            'soll_haben' => $sollHaben,
            'amount' => round(abs($gross), 2),
            'tax_rate' => $taxRate,
            'tax_key' => $config->taxKeyFor($taxRate),
            'document_ref' => (string) $invoice->number,
            'text' => $isReversal
                ? $this->clip(trim('Storno ' . $this->invoiceText($invoice)), 60)
                : $this->invoiceText($invoice),
            'date' => ($invoice->issued_on ?? $invoice->created_at ?? Carbon::now())->toDateString(),
            'is_credit_note' => $isCredit,
            'is_reversal' => $isReversal,
        ];
    }

    /**
     * Spese als Aufwandsbuchung: Soll Aufwandskonto an Haben Debitorenkonto.
     * MVP-334: Aufwandskonto + Vorsteuer-BU kommen aus dem Kategorie-Mapping
     * ({@see DatevBookingConfig::expenseAccountFor()}); ohne Mapping greift die
     * bisherige Vereinfachung (Erlöskonto-Slot, Steuersatz-BU).
     *
     * @return array{source_type: class-string, source_id: int, debtor_account: string, revenue_account: string, soll_haben: string, amount: float, tax_rate: float, tax_key: ?string, document_ref: string, text: string, date: string, is_credit_note: bool, is_reversal: bool}
     */
    private function expenseRow(Expense $expense, DatevBookingConfig $config): array {
        $taxRate = (float) $expense->tax_rate;
        $gross = (float) $expense->amount_gross;

        return [
            'source_type' => Expense::class,
            'source_id' => (int) $expense->id,
            'debtor_account' => (string) ($config->debtorBase),
            'revenue_account' => $config->expenseAccountFor($expense),
            'soll_haben' => 'S',
            'amount' => round(abs($gross), 2),
            'tax_rate' => $taxRate,
            'tax_key' => $config->expenseTaxKeyFor($expense),
            'document_ref' => 'E-' . (int) $expense->id,
            'text' => $this->clip(trim((string) ($expense->vendor ?: $expense->description)) ?: 'Auslage', 60),
            'date' => $expense->date->toDateString(),
            'is_credit_note' => false,
            'is_reversal' => false,
        ];
    }

    /**
     * Preflight: prüft Stammdaten und Konsistenz vor der Finalisierung.
     * Liefert eine Liste von Fehlern (blockierend) und Warnungen (informativ).
     *
     * @param  Collection<int, Invoice|Expense>  $sources
     * @return array{errors: list<string>, warnings: list<string>, total: float, count: int}
     */
    public function preflight(Organization $organization, Collection $sources, DatevBookingConfig $config): array {
        $errors = [];
        $warnings = [];

        if (! $config->hasClientNumbers()) {
            $errors[] = (string) __('finance.datev.preflight.missing_client_numbers');
        }

        if ($sources->isEmpty()) {
            $errors[] = (string) __('finance.datev.preflight.no_sources');
        }

        $rows = $this->buildBookingRows($sources, $config);
        $total = 0.0;

        foreach ($rows as $row) {
            $total += $this->signedRowAmount($row);

            if ($row['tax_key'] === null) {
                $warnings[] = (string) __('finance.datev.preflight.unknown_tax_key', [
                    'ref' => $row['document_ref'],
                    'rate' => number_format($row['tax_rate'], 2),
                ]);
            }

            if (trim($row['debtor_account']) === '' || $row['debtor_account'] === '0') {
                $errors[] = (string) __('finance.datev.preflight.missing_debtor', ['ref' => $row['document_ref']]);
            }

            if (trim($row['revenue_account']) === '') {
                $errors[] = (string) __('finance.datev.preflight.missing_revenue', ['ref' => $row['document_ref']]);
            }
        }

        // Hoheits-Hinweis: extern geführte Kunden tauchen gar nicht erst auf —
        // wir warnen, falls solche Rechnungen im Zeitraum existieren.
        $externalCount = $this->countExternallyLedInvoices($organization, $sources);
        if ($externalCount > 0) {
            $warnings[] = (string) __('finance.datev.preflight.external_excluded', ['count' => $externalCount]);
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'total' => round($total, 2),
            'count' => count($rows),
        ];
    }

    /**
     * Erzeugt einen Draft-Stapel und persistiert die Quellnachweise (Snapshots).
     * Verbraucht die Quellen NICHT — das geschieht erst bei finalize().
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @param  Collection<int, Invoice|Expense>  $sources
     */
    public function createDraft(
        Organization $organization,
        array $period,
        Collection $sources,
        DatevBookingConfig $config,
        ?User $actor = null,
    ): DatevBookingBatch {
        if ($sources->isEmpty()) {
            throw new DatevBookingException(
                'noSources',
                (string) __('finance.datev.error.no_sources'),
                ['organization_id' => $organization->id],
            );
        }

        $rows = $this->buildBookingRows($sources, $config);
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->signedRowAmount($row);
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($organization, $period, $rows, $config, $total, $actorId): DatevBookingBatch {
            /** @var DatevBookingBatch $batch */
            $batch = DatevBookingBatch::query()->create([
                'organization_id' => $organization->id,
                'batch_no' => $this->nextBatchNo($organization),
                'period_from' => ! empty($period['from']) ? Carbon::parse($period['from'])->toDateString() : Carbon::now()->startOfMonth()->toDateString(),
                'period_to' => ! empty($period['to']) ? Carbon::parse($period['to'])->toDateString() : Carbon::now()->endOfMonth()->toDateString(),
                'status' => DatevBatchStatus::Draft,
                'skr' => $config->skr->value,
                'advisor_number' => $config->advisorNumber,
                'client_number' => $config->clientNumber,
                'booking_count' => count($rows),
                'total_amount' => (string) round($total, 2),
                'finalized_locked' => $config->finalize,
                'selection_mode' => 'all',
                'created_by_user_id' => $actorId,
            ]);

            foreach ($rows as $row) {
                $batch->sources()->create([
                    'source_type' => $row['source_type'],
                    'source_id' => $row['source_id'],
                    'debtor_account' => $row['debtor_account'],
                    'revenue_account' => $row['revenue_account'],
                    'soll_haben' => $row['soll_haben'],
                    'amount' => (string) $row['amount'],
                    'tax_key' => $row['tax_key'],
                    'document_ref' => $this->clip($row['document_ref'], 36),
                    'is_reversal' => $row['is_reversal'],
                ]);
            }

            $this->recordEvent($batch, 'created', $actorId, [
                'status' => DatevBatchStatus::Draft->value,
                'booking_count' => count($rows),
                'total_amount' => (string) round($total, 2),
            ]);

            return $batch->refresh()->load('sources');
        });
    }

    /**
     * Teilauswahl (MVP-334): entfernt Quellsätze aus einem DRAFT-Stapel,
     * rechnet Kennzahlen neu und persistiert den Zuschnitt am Exportnachweis
     * (selection_mode = manual + Hash-Ketten-Event mit den entfernten Belegen).
     * Entfernte Quellen sind sofort wieder buchungsreif (Draft-Reservierung
     * entfällt mit der Quellzeile).
     *
     * @param  list<int>  $sourceIds  IDs der datev_booking_sources-Zeilen
     *
     * @throws DatevBookingException
     */
    public function removeSources(DatevBookingBatch $batch, array $sourceIds, ?User $actor = null): DatevBookingBatch {
        if ($batch->isFinal()) {
            throw new DatevBookingException(
                'alreadyFinalized',
                (string) __('finance.datev.error.already_finalized'),
                ['batch_id' => $batch->id],
            );
        }

        $batch->loadMissing('sources');
        $remove = $batch->sources->whereIn('id', array_map(intval(...), $sourceIds));

        if ($remove->isEmpty()) {
            throw new DatevBookingException(
                'noSelection',
                (string) __('finance.datev.error.no_selection'),
                ['batch_id' => $batch->id],
            );
        }

        if ($remove->count() >= $batch->sources->count()) {
            throw new DatevBookingException(
                'noSources',
                (string) __('finance.datev.error.selection_empty_batch'),
                ['batch_id' => $batch->id],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($batch, $remove, $actorId): DatevBookingBatch {
            $removedRefs = [];
            foreach ($remove as $source) {
                $removedRefs[] = (string) $source->document_ref;
                $source->delete();
            }

            $remaining = $batch->sources()->get();
            $total = 0.0;
            foreach ($remaining as $source) {
                $sign = $source->soll_haben === 'H' ? -1 : 1;
                $sign *= $source->is_reversal ? -1 : 1;
                $total += $sign * (float) $source->amount;
            }

            $batch->fill([
                'booking_count' => $remaining->count(),
                'total_amount' => (string) round($total, 2),
                'selection_mode' => 'manual',
            ])->save();

            $this->recordEvent($batch, 'sources_removed', $actorId, [
                'removed_refs' => $removedRefs,
                'booking_count' => $remaining->count(),
                'total_amount' => (string) round($total, 2),
            ]);

            return $batch->refresh()->load('sources');
        });
    }

    /**
     * Verwirft einen DRAFT-Stapel (SoftDelete) und gibt damit dessen Quellen
     * für den nächsten Lauf frei (MVP-334 — mehrere Stapel je Zeitraum).
     * Exportierte Stapel sind unlöschbar (Model-Guard greift zusätzlich).
     *
     * @throws DatevBookingException
     */
    public function discardDraft(DatevBookingBatch $batch, ?User $actor = null): void {
        if ($batch->isFinal()) {
            throw new DatevBookingException(
                'alreadyFinalized',
                (string) __('finance.datev.error.already_finalized'),
                ['batch_id' => $batch->id],
            );
        }

        $actorId = $this->resolveActorId($actor);

        DB::transaction(function () use ($batch, $actorId): void {
            $this->recordEvent($batch, 'discarded', $actorId, [
                'booking_count' => (int) $batch->booking_count,
            ]);
            $batch->delete();
        });
    }

    /**
     * Finalisiert einen Draft: erzeugt die DATEV-CSV, bildet SHA-256, legt die
     * Datei ab, markiert den Stapel als exported (unveränderlich) und schreibt
     * ein Hash-Event. Die Quellen sind über datev_booking_sources bereits
     * gegen erneute Übergabe geschützt.
     *
     * @throws DatevBookingException
     */
    public function finalize(DatevBookingBatch $batch, DatevBookingConfig $config, ?User $actor = null): DatevBookingBatch {
        if ($batch->isFinal()) {
            throw new DatevBookingException(
                'alreadyFinalized',
                (string) __('finance.datev.error.already_finalized'),
                ['batch_id' => $batch->id],
            );
        }

        FinancialFormatsSupport::ensureAvailable();

        $batch->loadMissing('sources');

        // Harter Doppel-Übergabe-Guard (MVP-334): keine Quelle dieses Stapels
        // darf bereits in einem ANDEREN exportierten Stapel hängen (Race bei
        // parallel angelegten Drafts vor der Draft-Reservierung).
        $this->assertSourcesNotExportedElsewhere($batch);

        $rows = $batch->sources->map(fn(DatevBookingSource $s): array => [
            'amount' => (float) $s->amount,
            'soll_haben' => (string) $s->soll_haben,
            'account' => (string) $s->debtor_account,
            'contra_account' => (string) $s->revenue_account,
            'tax_key' => $s->tax_key,
            'date' => new DateTimeImmutable($this->sourceDate($s, $batch)),
            'document_ref' => (string) $s->document_ref,
            'text' => $this->sourceText($s),
            'is_reversal' => (bool) $s->is_reversal,
        ])->all();

        $csv = $this->adapter->generate($batch, $config, array_values($rows));

        // Write→Read-Validierung (Kriterium 045): erzeugte Datei mit demselben
        // Toolkit wieder einlesen; bei Abweichung NICHT ablegen, sondern abbrechen.
        $roundtrip = $this->adapter->validateRoundtrip($csv, $config, (int) $batch->booking_count);
        if (! $roundtrip['ok']) {
            throw new DatevBookingException(
                'roundtripFailed',
                (string) __('finance.datev.error.roundtrip_failed', ['errors' => implode('; ', $roundtrip['errors'])]),
                ['batch_id' => $batch->id, 'roundtrip' => $roundtrip],
            );
        }

        $hash = CryptoHelper::hash($csv);

        $relativePath = sprintf(
            '%s/%d/%s/datev-buchungsstapel-%d-%s.csv',
            self::BASE_PATH,
            (int) $batch->organization_id,
            CarbonImmutable::now()->format('Y-m'),
            (int) $batch->batch_no,
            CarbonImmutable::now()->format('Ymd_His'),
        );

        $disk = Storage::disk(self::DISK);
        if (! $disk->put($relativePath, $csv)) {
            throw new DatevBookingException(
                'storageFailed',
                (string) __('finance.datev.error.storage_failed'),
                ['batch_id' => $batch->id, 'path' => $relativePath],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($batch, $relativePath, $hash, $actorId, $roundtrip): DatevBookingBatch {
            $batch->fill([
                'status' => DatevBatchStatus::Exported,
                'file_path' => $relativePath,
                'file_hash' => $hash,
                'finalized_at' => now(),
            ])->save();

            $this->recordEvent($batch, 'finalized', $actorId, [
                'status' => DatevBatchStatus::Exported->value,
                'file_hash' => $hash,
                'booking_count' => (int) $batch->booking_count,
                // Formatversion + Roundtrip-Beleg im revisionssicheren Nachweis.
                'format_type' => $roundtrip['format_type'],
                'format_version' => $roundtrip['version'],
                'roundtrip_ok' => $roundtrip['ok'],
            ]);

            // Feature-Nutzungszähler (036; Vollaudit 2026-07, N14).
            app(\App\Services\Metrics\OperationsMetricsService::class)->increment('finance.datev_export', (int) $batch->organization_id);

            return $batch->refresh();
        });
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>|\Illuminate\Database\Eloquent\Builder<Expense>  $query
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     */
    private function applyPeriod($query, string $column, array $period): void {
        if (! empty($period['from'])) {
            $query->where($column, '>=', Carbon::parse($period['from'])->toDateString());
        }
        if (! empty($period['to'])) {
            $query->where($column, '<=', Carbon::parse($period['to'])->toDateString());
        }
    }

    /**
     * Schließt Quellen aus, die bereits in einem AKTIVEN Stapel hängen —
     * Schutz gegen Doppel-Übergabe. Seit MVP-334 reservieren auch Drafts ihre
     * Quellen (mehrere Stapel je Zeitraum/Teilauswahl); ein verworfener Draft
     * (SoftDelete) bzw. entfernte Quellzeilen geben sie wieder frei.
     * Generalumkehr-Sätze (is_reversal) blockieren die Normal-Sammlung nicht.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>|\Illuminate\Database\Eloquent\Builder<Expense>  $query
     * @param  class-string  $sourceType
     */
    private function excludeAlreadyBooked($query, string $sourceType): void {
        $table = $query->getModel()->getTable();

        $query->whereNotExists(function ($sub) use ($sourceType, $table): void {
            $sub->from('datev_booking_sources')
                ->join('datev_booking_batches', 'datev_booking_batches.id', '=', 'datev_booking_sources.datev_booking_batch_id')
                ->whereColumn('datev_booking_sources.source_id', $table . '.id')
                ->where('datev_booking_sources.source_type', $sourceType)
                ->where('datev_booking_sources.is_reversal', false)
                ->whereIn('datev_booking_batches.status', [DatevBatchStatus::Draft->value, DatevBatchStatus::Exported->value])
                ->whereNull('datev_booking_batches.deleted_at');
        });
    }

    /**
     * Wirft, wenn eine Quelle des Stapels (gleiche Rolle: Original bzw.
     * Generalumkehr) bereits in einem ANDEREN exportierten Stapel hängt.
     *
     * @throws DatevBookingException
     */
    private function assertSourcesNotExportedElsewhere(DatevBookingBatch $batch): void {
        foreach ($batch->sources as $source) {
            $exists = DatevBookingSource::query()
                ->where('source_type', $source->source_type)
                ->where('source_id', $source->source_id)
                ->where('is_reversal', (bool) $source->is_reversal)
                ->where('datev_booking_batch_id', '!=', $batch->id)
                ->whereHas('batch', fn($q) => $q
                    ->where('organization_id', $batch->organization_id)
                    ->where('status', DatevBatchStatus::Exported->value))
                ->exists();

            if ($exists) {
                throw new DatevBookingException(
                    'sourceAlreadyExported',
                    (string) __('finance.datev.error.source_already_exported', ['ref' => (string) $source->document_ref]),
                    ['batch_id' => $batch->id, 'source_id' => $source->source_id],
                );
            }
        }
    }

    /**
     * Signierter Zeilenbetrag für Kennzahlen: Gutschrift dreht das Vorzeichen,
     * Generalumkehr dreht es erneut (Storno einer Gutschrift ⇒ positiv).
     *
     * @param  array{amount: float, is_credit_note: bool, is_reversal: bool}  $row
     */
    private function signedRowAmount(array $row): float {
        $sign = $row['is_credit_note'] ? -1 : 1;
        $sign *= $row['is_reversal'] ? -1 : 1;

        return $sign * $row['amount'];
    }

    private function isExternallyLed(Invoice $invoice): bool {
        $customer = $invoice->customer;

        return $this->billingModeResolver->effectiveFor($customer)->isExternal();
    }

    /**
     * @param  Collection<int, Invoice|Expense>  $sources
     */
    private function countExternallyLedInvoices(Organization $organization, Collection $sources): int {
        // $sources ist bereits gefiltert; für den Preflight-Hinweis genügt ein
        // konservativer Zähler extern geführter Rechnungen der Org.
        unset($sources);

        return Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->whereHas('customer', fn($q) => $q->where('billing_mode', '!=', 'workdiary')->whereNotNull('billing_mode'))
            ->count();
    }

    private function nextBatchNo(Organization $organization): int {
        $max = (int) DatevBookingBatch::query()
            ->withTrashed()
            ->where('organization_id', $organization->id)
            ->max('batch_no');

        return $max + 1;
    }

    private function sourceDate(DatevBookingSource $source, DatevBookingBatch $batch): string {
        return $batch->period_from->toDateString();
    }

    private function sourceText(DatevBookingSource $source): string {
        $invoice = $source->source_type === Invoice::class
            ? Invoice::query()->with('customer')->find($source->source_id)
            : null;

        if ($invoice instanceof Invoice) {
            return $this->invoiceText($invoice);
        }

        return (string) ($source->document_ref ?: 'Buchung');
    }

    private function invoiceText(Invoice $invoice): string {
        $customer = (string) ($invoice->customer->name ?? '');
        $label = $invoice->isCreditNote() ? 'Gutschrift' : 'Rechnung';

        return $this->clip(trim($label . ' ' . $customer), 60);
    }

    private function clip(string $value, int $max): string {
        $value = trim($value);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    /** @param  array<string, mixed>  $payload */
    private function recordEvent(DatevBookingBatch $batch, string $event, ?int $actorId, array $payload): DatevBookingEvent {
        return DatevBookingEvent::create([
            'organization_id' => $batch->organization_id,
            'datev_booking_batch_id' => $batch->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
