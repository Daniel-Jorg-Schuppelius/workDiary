<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountingEntryStatus, DepreciationMethod, FixedAssetDisposalKind, FixedAssetStatus};
use App\Models\Accounting\{AccountingEntry, AccountingFiscalYear, AccountingProfile, FixedAsset, FixedAssetSpecialDepreciation};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\Posting\Adapters\DepreciationAdapter;
use App\Services\Accounting\Posting\PostingInboxService;
use App\Services\Concerns\{AssertsStatusTransition, AssignsSequentialNo};
use App\Support\{MorphMap, Setting};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\ValueObjects\{Decimal, Money, Percentage};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anlagenregister (Feature 133, MVP-698): Anlegen, Pflegen, Abgang — und
 * der Jahresvorschlag der AfA in die Buchungs-Inbox.
 *
 * Der Service bucht nie selbst: Der Vorschlag wird als `ready`-Entwurf
 * vorbereitet; festgeschrieben wird über die Inbox (Vier-Augen-Prinzip
 * inklusive). Sobald eine AfA-Buchung festgeschrieben ist, sind die
 * wertbestimmenden Felder der Anlage eingefroren — sonst würde der Plan
 * rückwirkend etwas anderes behaupten als das Journal.
 */
class FixedAssetService {
    use AssertsStatusTransition;
    use AssignsSequentialNo;

    /** Felder, die nach der ersten Festbuchung nicht mehr änderbar sind. */
    private const VALUE_FIELDS = ['acquired_on', 'acquisition_cost', 'residual_value', 'useful_life_months', 'depreciation_method', 'declining_rate', 'currency'];

    /**
     * Degressive AfA nach § 7 Abs. 2 EStG: Anschaffungsfenster mit Höchstfaktor
     * auf den linearen Satz und absolutem Deckel in Prozent (MVP-980).
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const DECLINING_WINDOWS = [
        ['2009-01-01', '2010-12-31', '2.5', '25'],
        ['2020-01-01', '2022-12-31', '2.5', '25'],
        ['2024-04-01', '2024-12-31', '2', '20'],
        ['2025-07-01', '2027-12-31', '3', '30'],
    ];

    /** Sonder-AfA § 7g: zusammen höchstens 40 % der Anschaffungskosten, in fünf Jahren (MVP-981). */
    private const SPECIAL_MAX_PERCENT = '40';

    private const SPECIAL_YEARS = 5;

    public function __construct(
        private readonly DepreciationAdapter $adapter,
        private readonly PostingInboxService $inbox,
        private readonly JournalService $journal,
        private readonly AccountingEventRecorder $events,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Organization $organization, User $actor, array $attributes): FixedAsset {
        $attributes = $this->withMethodRules($attributes);
        $this->assertValues($attributes);

        return DB::transaction(function () use ($organization, $actor, $attributes): FixedAsset {
            return FixedAsset::query()->create([
                'organization_id' => $organization->id,
                'asset_no' => $this->nextNo(FixedAsset::class, 'asset_no', 'organization_id', (int) $organization->id),
                'name' => $attributes['name'],
                'asset_id' => $attributes['asset_id'] ?? null,
                'acquired_on' => $attributes['acquired_on'],
                'currency' => $attributes['currency'] ?? $this->journal->baseCurrency($organization),
                'acquisition_cost' => $attributes['acquisition_cost'],
                'residual_value' => $attributes['residual_value'] ?? '0.00',
                'useful_life_months' => (int) $attributes['useful_life_months'],
                'depreciation_method' => $attributes['depreciation_method'] ?? DepreciationMethod::Linear,
                'declining_rate' => $attributes['declining_rate'] ?? null,
                'asset_account_id' => $attributes['asset_account_id'] ?? null,
                'depreciation_account_id' => $attributes['depreciation_account_id'] ?? null,
                'status' => FixedAssetStatus::Active,
                'source_type' => $attributes['source_type'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
                'note' => $attributes['note'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(FixedAsset $asset, array $attributes): FixedAsset {
        if ($asset->isDisposed()) {
            throw ValidationException::withMessages([
                'status' => (string) __('accounting.fixed_assets.error.disposed_frozen'),
            ]);
        }

        $merged = $attributes + [
            'acquired_on' => $asset->acquired_on->toDateString(),
            'acquisition_cost' => $asset->acquisition_cost?->getAmount() ?? '0.00',
            'residual_value' => $asset->residual_value?->getAmount() ?? '0.00',
            'useful_life_months' => $asset->useful_life_months,
            'depreciation_method' => $asset->depreciation_method,
            'declining_rate' => $asset->declining_rate,
        ];
        $merged = $this->withMethodRules($merged);
        if (array_key_exists('depreciation_method', $attributes)) {
            $attributes['useful_life_months'] = $merged['useful_life_months'];
        }
        $this->assertValues($merged);

        if ($this->hasPostedDepreciation($asset)) {
            foreach (self::VALUE_FIELDS as $field) {
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }
                if ($this->normalized($field, $attributes[$field]) !== $this->normalized($field, $asset->getAttribute($field))) {
                    throw ValidationException::withMessages([
                        $field => (string) __('accounting.fixed_assets.error.values_frozen'),
                    ]);
                }
            }
        }

        $asset->fill(array_intersect_key($attributes, array_flip([
            'name', 'asset_id', 'acquired_on', 'acquisition_cost', 'residual_value', 'useful_life_months',
            'depreciation_method', 'declining_rate', 'asset_account_id', 'depreciation_account_id', 'note',
        ])));
        $asset->save();

        return $asset->refresh();
    }

    /**
     * Abgang: Statuswechsel active → disposed mit Abgangsdatum, Art und Erlös
     * (MVP-891). Die AfA des Abgangsjahres läuft zeitanteilig bis zum
     * Abgangsmonat; den Restbuchwert bucht der AssetDisposalAdapter über die
     * Inbox aus. Der Erlös selbst läuft über die Ausgangsrechnung.
     */
    public function dispose(
        FixedAsset $asset,
        CarbonImmutable $disposedOn,
        User $actor,
        ?string $note = null,
        FixedAssetDisposalKind $kind = FixedAssetDisposalKind::Scrap,
        ?string $proceeds = null,
    ): FixedAsset {
        $this->assertStatusTransition($asset->status, FixedAssetStatus::Disposed);

        if ($disposedOn->lessThan($asset->acquiredOn())) {
            throw ValidationException::withMessages([
                'disposed_on' => (string) __('accounting.fixed_assets.error.disposed_before_acquired'),
            ]);
        }
        $proceedsMoney = $kind === FixedAssetDisposalKind::Sale && $proceeds !== null && $proceeds !== ''
            ? Money::of($proceeds, $asset->currency)
            : null;

        $asset->update([
            'status' => FixedAssetStatus::Disposed,
            'disposed_on' => $disposedOn->toDateString(),
            'disposal_kind' => $kind,
            'disposal_proceeds_amount' => $proceedsMoney,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : $asset->note,
        ]);

        $asset->audit('accounting.fixed_asset_disposed', [
            'asset_no' => $asset->displayNo(),
            'disposed_on' => $disposedOn->toDateString(),
            'disposal_kind' => $kind->value,
            'disposal_proceeds_amount' => $proceedsMoney?->getAmount(),
            'actor_user_id' => $actor->id,
        ]);

        return $asset->refresh();
    }

    /**
     * AfA-Buchungen eines Geschäftsjahres als Inbox-Entwürfe vorbereiten.
     * Blockierte Anlagen bleiben offen (und sichtbar in der Inbox); bereits
     * vorbereitete oder gebuchte Jahre werden nicht doppelt angelegt.
     *
     * @return array{prepared: int, posted: int, failed: list<string>, skipped: int}
     */
    public function proposeForYear(Organization $organization, AccountingFiscalYear $year, User $actor): array {
        if ($year->status->isHardClosed()) {
            throw ValidationException::withMessages([
                'year' => (string) __('accounting.inbox.blocker.year_closed', ['year' => $year->label]),
            ]);
        }

        $batch = [];
        $skipped = 0;
        foreach ($this->adapter->candidatesForYear($organization, $year) as $candidate) {
            if ($this->journal->activeEntryForSource($organization, $this->adapter->keyFor($candidate, $year)) instanceof AccountingEntry) {
                $skipped++;

                continue;
            }

            $batch[] = ['proposal' => $this->adapter->proposalFor($organization, $candidate)];
        }

        $result = $this->inbox->processBatch($organization, $batch, $actor, false);

        $this->events->record($organization, 'accounting.depreciation_proposed', [
            'fiscal_year' => $year->label,
            'prepared' => $result['prepared'],
            'skipped' => $skipped,
            'failed' => count($result['failed']),
        ], null, $actor);

        return $result + ['skipped' => $skipped];
    }

    /**
     * Anlagen eines Geschäftsjahres, deren AfA noch nicht festgeschrieben ist
     * (Prüfstand des Abschlusses).
     *
     * @return list<FixedAsset>
     */
    public function unpostedForYear(Organization $organization, AccountingFiscalYear $year): array {
        $candidates = $this->adapter->candidatesForYear($organization, $year);
        if ($candidates->isEmpty()) {
            return [];
        }

        $entries = $this->journal->activeEntriesForSources(
            $organization,
            array_values($candidates->map(fn (FixedAsset $asset): string => $this->adapter->keyFor($asset, $year))->all()),
        );

        $open = [];
        foreach ($candidates as $candidate) {
            $entry = $entries[$this->adapter->keyFor($candidate, $year)] ?? null;
            if ($entry?->status->isPosted() === true) {
                continue;
            }
            $open[] = $candidate;
        }

        return $open;
    }

    /**
     * Buchungsstand je Planzeile: Journalbuchung (posted/ready) oder null.
     *
     * @param  list<DepreciationScheduleRow>  $rows
     * @return array<int, AccountingEntry|null> Startjahr → Buchung
     */
    public function entriesForSchedule(Organization $organization, FixedAsset $asset, array $rows): array {
        if ($rows === []) {
            return [];
        }

        $years = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy(fn (AccountingFiscalYear $year): int => $year->starts_on->year);

        $keys = [];
        foreach ($rows as $row) {
            $year = $years->get($row->fiscalYear);
            if ($year instanceof AccountingFiscalYear) {
                $keys[$row->fiscalYear] = $this->adapter->keyFor($asset, $year);
            }
        }

        $entries = $this->journal->activeEntriesForSources($organization, array_values($keys));

        $result = [];
        foreach ($rows as $row) {
            $key = $keys[$row->fiscalYear] ?? null;
            $result[$row->fiscalYear] = $key !== null ? ($entries[$key] ?? null) : null;
        }

        return $result;
    }

    public function hasPostedDepreciation(FixedAsset $asset): bool {
        return AccountingEntry::query()
            ->where('organization_id', $asset->organization_id)
            ->where('source_type', MorphMap::alias(FixedAsset::class))
            ->where('source_id', $asset->getKey())
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value])
            ->exists();
    }

    /** @param array<string, mixed> $attributes */
    /**
     * GWG und Sammelposten (MVP-892): Wertgrenzen aus den Einstellungen der
     * Organisation (keine gesetzlichen Beträge im Code), Laufzeit aus der
     * Methode — GWG ein Jahr, Sammelposten `pool_years` Jahre.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function withMethodRules(array $attributes): array {
        $method = $attributes['depreciation_method'] ?? DepreciationMethod::Linear;
        $method = $method instanceof DepreciationMethod ? $method : DepreciationMethod::tryFrom((string) $method) ?? DepreciationMethod::Linear;
        $cost = Decimal::ofNullable((string) ($attributes['acquisition_cost'] ?? ''), 2);
        if ($method === DepreciationMethod::Declining) {
            return $this->withDecliningRules($attributes);
        }
        $attributes['declining_rate'] = null;
        if ($method === DepreciationMethod::Linear || $cost === null) {
            return $attributes;
        }

        $amount = static fn (string $key, int $fallback): Decimal => Decimal::ofNullable((string) Setting::get($key, $fallback), 2) ?? Decimal::of($fallback, 2);
        if ($method === DepreciationMethod::Immediate) {
            $limit = $amount('finance.fixed_assets.gwg_limit', 800);
            if ($cost->greaterThan($limit)) {
                throw ValidationException::withMessages(['depreciation_method' => (string) __('accounting.fixed_assets.error.gwg_limit', ['limit' => $limit->getValue()])]);
            }
            $attributes['useful_life_months'] = 12;
        } else {
            $lower = $amount('finance.fixed_assets.pool_lower', 250);
            $upper = $amount('finance.fixed_assets.pool_upper', 1000);
            if ($cost->lessThanOrEqual($lower) || $cost->greaterThan($upper)) {
                throw ValidationException::withMessages(['depreciation_method' => (string) __('accounting.fixed_assets.error.pool_range', ['lower' => $lower->getValue(), 'upper' => $upper->getValue()])]);
            }
            $attributes['useful_life_months'] = 12 * max(1, (int) Setting::get('finance.fixed_assets.pool_years', 5));
        }

        return $attributes;
    }

    /**
     * Höchster zulässiger Satz der degressiven AfA für Anschaffung und
     * Nutzungsdauer, null außerhalb der gesetzlichen Fenster (MVP-980).
     */
    public static function maxDecliningRate(CarbonImmutable $acquiredOn, int $usefulLifeMonths): ?Percentage {
        if ($usefulLifeMonths < 1) {
            return null;
        }
        $day = $acquiredOn->toDateString();
        foreach (self::DECLINING_WINDOWS as [$from, $until, $factor, $cap]) {
            if ($day >= $from && $day <= $until) {
                // Abgeschnitten statt gerundet: aufgerundet läge der Satz über dem Vielfachen.
                $byFactor = Decimal::of($factor)->times(Decimal::of(1200))->dividedBy(Decimal::of($usefulLifeMonths), 2, RoundingMode::Truncate);

                return Percentage::of(Decimal::min($byFactor, Decimal::of($cap, 2))->getValue(), 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withDecliningRules(array $attributes): array {
        $acquired = CarbonImmutable::parse((string) ($attributes['acquired_on'] ?? 'now'));
        $max = self::maxDecliningRate($acquired, (int) ($attributes['useful_life_months'] ?? 0));
        if ($max === null) {
            throw ValidationException::withMessages(['depreciation_method' => (string) __('accounting.fixed_assets.error.declining_window')]);
        }
        $raw = $attributes['declining_rate'] ?? null;
        $rate = $raw instanceof Percentage ? $raw : Percentage::tryFrom(is_scalar($raw) ? (string) $raw : null, 2);
        if ($rate === null || ! $rate->isPositive() || $rate->compareTo($max) > 0) {
            throw ValidationException::withMessages(['declining_rate' => (string) __('accounting.fixed_assets.error.declining_rate', ['max' => $max->getNumericValue()])]);
        }
        $attributes['declining_rate'] = $rate;

        return $attributes;
    }

    /**
     * Sonder-AfA § 7g für ein Geschäftsjahr anlegen oder ersetzen (MVP-981):
     * nur im Begünstigungszeitraum, zusammen höchstens 40 % der AK/HK und nur,
     * solange die AfA dieses Jahres nicht vorbereitet oder gebucht ist.
     */
    public function saveSpecialDepreciation(FixedAsset $asset, int $fiscalYear, string $amount, ?string $note, User $actor): FixedAssetSpecialDepreciation {
        $this->assertSpecialEditable($asset, $fiscalYear);
        $startYear = $this->acquisitionFiscalYear($asset);
        if ($fiscalYear < $startYear || $fiscalYear >= $startYear + self::SPECIAL_YEARS) {
            throw ValidationException::withMessages(['fiscal_year' => (string) __('accounting.fixed_assets.error.special_period', ['from' => $startYear, 'until' => $startYear + self::SPECIAL_YEARS - 1])]);
        }
        $special = Money::ofNullable($amount, $asset->currency, 2);
        if ($special === null || ! $special->isPositive()) {
            throw ValidationException::withMessages(['depreciation_amount' => (string) __('accounting.fixed_assets.error.special_amount')]);
        }
        $others = Money::sum(
            $asset->specialDepreciations()->where('fiscal_year', '!=', $fiscalYear)->get()->map(static fn (FixedAssetSpecialDepreciation $row): Money => $row->depreciation_amount),
            $asset->currency,
        );
        $limit = Percentage::of(self::SPECIAL_MAX_PERCENT)->amountOf($asset->acquisition_cost ?? Money::zero($asset->currency));
        if ($others->plus($special)->greaterThan($limit)) {
            throw ValidationException::withMessages(['depreciation_amount' => (string) __('accounting.fixed_assets.error.special_limit', ['limit' => $limit->format()])]);
        }

        return FixedAssetSpecialDepreciation::query()->updateOrCreate(
            ['fixed_asset_id' => $asset->id, 'fiscal_year' => $fiscalYear],
            ['organization_id' => $asset->organization_id, 'depreciation_amount' => $special, 'currency' => $asset->currency, 'note' => $note, 'created_by' => $actor->id],
        );
    }

    public function removeSpecialDepreciation(FixedAssetSpecialDepreciation $special): void {
        $asset = $special->fixedAsset;
        if ($asset instanceof FixedAsset) {
            $this->assertSpecialEditable($asset, $special->fiscal_year);
        }
        $special->delete();
    }

    private function assertSpecialEditable(FixedAsset $asset, int $fiscalYear): void {
        if ($asset->isDisposed() || in_array($asset->depreciation_method, [DepreciationMethod::Immediate, DepreciationMethod::Pool], true)) {
            throw ValidationException::withMessages(['fiscal_year' => (string) __('accounting.fixed_assets.error.special_not_allowed')]);
        }
        $year = AccountingFiscalYear::query()->where('organization_id', $asset->organization_id)->get()
            ->first(static fn (AccountingFiscalYear $candidate): bool => $candidate->starts_on->year === $fiscalYear);
        if ($year instanceof AccountingFiscalYear && $this->journal->activeEntryForSource($asset->organization()->firstOrFail(), $this->adapter->keyFor($asset, $year)) instanceof AccountingEntry) {
            throw ValidationException::withMessages(['fiscal_year' => (string) __('accounting.fixed_assets.error.special_posted', ['year' => $fiscalYear])]);
        }
    }

    /** Startjahr des Geschäftsjahres der Anschaffung. */
    public function acquisitionFiscalYear(FixedAsset $asset): int {
        $profile = AccountingProfile::query()->where('organization_id', $asset->organization_id)->first();
        $startMonth = $profile instanceof AccountingProfile ? max(1, (int) $profile->fiscal_year_start_month) : 1;

        return app(DepreciationCalculator::class)->fiscalYearStartFor($asset->acquiredOn(), $startMonth)->year;
    }

    /** @param array<string, mixed> $attributes */
    private function assertValues(array $attributes): void {
        $cost = Decimal::ofNullable((string) ($attributes['acquisition_cost'] ?? ''), 2);
        $residual = Decimal::ofNullable((string) ($attributes['residual_value'] ?? '0'), 2);

        if ($cost === null || $residual === null || $residual->greaterThanOrEqual($cost)) {
            throw ValidationException::withMessages([
                'residual_value' => (string) __('accounting.fixed_assets.error.residual_exceeds_cost'),
            ]);
        }

        if ((int) ($attributes['useful_life_months'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'useful_life_months' => (string) __('accounting.fixed_assets.error.useful_life_required'),
            ]);
        }
    }

    private function normalized(string $field, mixed $value): string {
        if ($value instanceof Money) {
            return $value->getAmount();
        }
        if ($value instanceof Percentage) {
            return $value->getNumericValue();
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (in_array($field, ['acquisition_cost', 'residual_value'], true)) {
            return Decimal::ofNullable((string) $value, 2)?->getValue() ?? (string) $value;
        }
        if ($field === 'declining_rate') {
            return Percentage::tryFrom((string) $value, 2)?->getNumericValue() ?? (string) $value;
        }

        return (string) $value;
    }
}
