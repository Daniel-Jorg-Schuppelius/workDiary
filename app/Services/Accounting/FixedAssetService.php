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

use App\Enums\Finance\{AccountingEntryStatus, DepreciationMethod, FixedAssetStatus};
use App\Models\Accounting\{AccountingEntry, AccountingFiscalYear, FixedAsset};
use App\Models\{Organization, User};
use App\Services\Accounting\Posting\Adapters\DepreciationAdapter;
use App\Services\Accounting\Posting\PostingInboxService;
use App\Services\Concerns\{AssertsStatusTransition, AssignsSequentialNo};
use Carbon\CarbonImmutable;
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
    private const VALUE_FIELDS = ['acquired_on', 'acquisition_cost', 'residual_value', 'useful_life_months', 'depreciation_method', 'currency'];

    public function __construct(
        private readonly DepreciationAdapter $adapter,
        private readonly PostingInboxService $inbox,
        private readonly JournalService $journal,
        private readonly AccountingEventRecorder $events,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Organization $organization, User $actor, array $attributes): FixedAsset {
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
        ];
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
            'depreciation_method', 'asset_account_id', 'depreciation_account_id', 'note',
        ])));
        $asset->save();

        return $asset->refresh();
    }

    /**
     * Abgang: Statuswechsel active → disposed mit Abgangsdatum. Die AfA des
     * Abgangsjahres läuft zeitanteilig bis zum Abgangsmonat; die Buchung des
     * Restbuchwerts (Anlagenabgang) ist bewusst nicht Teil des MVP.
     */
    public function dispose(FixedAsset $asset, CarbonImmutable $disposedOn, User $actor, ?string $note = null): FixedAsset {
        $this->assertStatusTransition($asset->status, FixedAssetStatus::Disposed);

        if ($disposedOn->lessThan($asset->acquiredOn())) {
            throw ValidationException::withMessages([
                'disposed_on' => (string) __('accounting.fixed_assets.error.disposed_before_acquired'),
            ]);
        }

        $asset->update([
            'status' => FixedAssetStatus::Disposed,
            'disposed_on' => $disposedOn->toDateString(),
            'note' => $note !== null && trim($note) !== '' ? trim($note) : $asset->note,
        ]);

        $asset->audit('accounting.fixed_asset_disposed', [
            'asset_no' => $asset->displayNo(),
            'disposed_on' => $disposedOn->toDateString(),
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
            ->where('source_type', FixedAsset::class)
            ->where('source_id', $asset->getKey())
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value])
            ->exists();
    }

    /** @param array<string, mixed> $attributes */
    private function assertValues(array $attributes): void {
        $cost = (string) ($attributes['acquisition_cost'] ?? '0');
        $residual = (string) ($attributes['residual_value'] ?? '0');

        if (! is_numeric($cost) || ! is_numeric($residual) || bccomp($residual, $cost, 2) >= 0) {
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
        if ($value instanceof \CommonToolkit\ValueObjects\Money) {
            return $value->getAmount();
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (in_array($field, ['acquisition_cost', 'residual_value'], true) && is_numeric((string) $value)) {
            return bcadd((string) $value, '0', 2);
        }

        return (string) $value;
    }
}
