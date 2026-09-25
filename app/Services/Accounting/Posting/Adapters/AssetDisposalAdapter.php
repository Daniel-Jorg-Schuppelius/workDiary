<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDisposalAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{DepreciationMethod, FixedAssetStatus, PostingAccountRole, PostingSourceKind};
use App\Models\Accounting\{AccountingFiscalYear, AccountingPeriod, AccountingProfile, FixedAsset};
use App\Models\Platform\Organization;
use App\Services\Accounting\DepreciationCalculator;
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine, PostingRuleResolver};
use App\Support\CarbonFmt;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Anlagenabgang (Feature 133, MVP-891): bucht den Restbuchwert am Abgangstag
 * aus — Soll Abgangskonto, Haben Anlagenkonto. Liegt der Erlös über dem
 * Restbuchwert, geht er auf „Restbuchwert bei Buchgewinn“, sonst auf
 * „… bei Buchverlust“ (DATEV-Praxis). Den Erlös selbst bucht die
 * Ausgangsrechnung; hier entsteht keine Umsatzsteuer.
 */
class AssetDisposalAdapter extends AbstractPostingAdapter {
    public function __construct(PostingRuleResolver $rules, private readonly DepreciationCalculator $calculator) {
        parent::__construct($rules);
    }

    public function kind(): PostingSourceKind {
        return PostingSourceKind::AssetDisposal;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> */
        return FixedAsset::query()
            ->where('organization_id', $organization->id)
            ->where('status', FixedAssetStatus::Disposed->value)
            // Sammelposten bleiben nach dem Abgang bestehen (MVP-892).
            ->where('depreciation_method', '!=', DepreciationMethod::Pool->value)
            ->where('disposed_on', '>=', $from->toDateString())
            ->where('disposed_on', '<', DateRange::dayAfter($to))
            ->with(['assetAccount'])
            ->orderBy('asset_no')
            ->get()
            ->filter(fn (FixedAsset $asset): bool => ! $this->residualValue($asset, $organization)->isZero())
            ->values();
    }

    public function sourceKey(Model $source): string {
        return $this->kind()->keyPrefix() . ':' . $source->getKey();
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof FixedAsset);
        $bookedOn = $source->disposedOn() ?? CarbonImmutable::today();
        $residual = $this->residualValue($source, $organization);
        $proceeds = $source->disposal_proceeds_amount ?? Money::zero($source->currency);
        $role = $proceeds->greaterThanOrEqual($residual) && ! $proceeds->isZero() ? PostingAccountRole::DisposalGain : PostingAccountRole::DisposalLoss;
        $amount = $residual->getAmount();

        $blockers = [];
        $ruleVersions = [];
        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }
        if ($residual->isZero()) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }
        $year = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->where('starts_on', '<', DateRange::dayAfter($bookedOn))
            ->where('ends_on', '>=', $bookedOn->toDateString())
            ->first();
        if ($year instanceof AccountingFiscalYear && $year->status->isHardClosed()) {
            $blockers[] = (string) __('accounting.inbox.blocker.year_closed', ['year' => $year->label]);
        } else {
            $period = AccountingPeriod::query()->where('organization_id', $organization->id)->covering($bookedOn)->first();
            if ($period instanceof AccountingPeriod && ! $period->status->acceptsPostings()) {
                $blockers[] = (string) __('accounting.inbox.blocker.period_closed', ['date' => CarbonFmt::fdate($bookedOn)]);
            }
        }

        $lines = array_values(array_filter([
            $this->fixedAssetLine($organization, $source, $role, null, $amount, '0.00', $bookedOn, $blockers, $ruleVersions),
            $this->fixedAssetLine($organization, $source, PostingAccountRole::FixedAsset, $source->assetAccount, '0.00', $amount, $bookedOn, $blockers, $ruleVersions),
        ], static fn (?PostingProposalLine $line): bool => $line instanceof PostingProposalLine));

        $title = (string) __('accounting.inbox.memo.asset_disposal', ['no' => $source->displayNo(), 'name' => $source->name]);

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $bookedOn,
            memo: $title,
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $bookedOn,
            documentReference: $source->displayNo(),
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: $title,
            extra: [
                'fixed_asset_no' => $source->displayNo(),
                'disposal_kind' => $source->disposal_kind?->value,
                'residual_value' => $amount,
                'proceeds' => $proceeds->getAmount(),
            ],
        );
    }

    /** Restbuchwert am Abgangstag aus dem AfA-Plan (ohne Plan: die AK/HK). */
    public function residualValue(FixedAsset $asset, Organization $organization): Money {
        $profileMonth = $this->fiscalYearStartMonth($organization);
        $rows = $this->calculator->scheduleFor($asset, $profileMonth);
        $last = end($rows);

        return $last !== false ? $last->bookValueEnd : ($asset->acquisition_cost ?? Money::zero($asset->currency));
    }

    private function fiscalYearStartMonth(Organization $organization): int {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? max(1, (int) $profile->fiscal_year_start_month) : 1;
    }
}
