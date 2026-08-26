<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DepreciationAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\Accounting\{AccountingAccount, AccountingFiscalYear, AccountingPeriod, FixedAsset};
use App\Models\Organization;
use App\Services\Accounting\{DepreciationCalculator, DepreciationScheduleRow};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine, PostingRuleResolver};
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Jahres-AfA je Anlage → Soll AfA-Aufwand / Haben Anlagenkonto (direkte
 * Methode, Feature 133, MVP-698).
 *
 * Eine Quelle ist das Paar Anlage × Geschäftsjahr: Der Adapter liefert je
 * Jahr eine eigene Kopie der Anlage mit Jahreskontext, der Schlüssel
 * `depreciation:{id}:{jahr}` macht den Vorschlag idempotent. Gebucht wird
 * am Geschäftsjahresende — beim Abgang im Jahr am Abgangstag.
 *
 * Konten: die an der Anlage hinterlegten, sonst die Buchungsregeln der
 * Rollen FixedAsset/Depreciation. Fehlt beides, blockiert der Vorschlag;
 * geschlossene Jahre und Perioden blockieren ebenfalls.
 */
class DepreciationAdapter extends AbstractPostingAdapter {
    public function __construct(PostingRuleResolver $rules, private readonly DepreciationCalculator $calculator) {
        parent::__construct($rules);
    }

    public function kind(): PostingSourceKind {
        return PostingSourceKind::Depreciation;
    }

    /**
     * Anlagen × Geschäftsjahre, deren AfA-Buchungsdatum im Zeitraum liegt.
     *
     * @return Collection<int, Model>
     */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        $years = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->orderBy('starts_on')
            ->get();

        /** @var Collection<int, Model> $result */
        $result = collect();
        foreach ($years as $year) {
            foreach ($this->candidatesForYear($organization, $year) as $candidate) {
                $bookedOn = $this->bookedOn($candidate, $year);
                if ($bookedOn->between($from->startOfDay(), $to->endOfDay())) {
                    $result->push($candidate);
                }
            }
        }

        return $result;
    }

    /**
     * Anlagen mit AfA-Betrag > 0 im Geschäftsjahr, je mit Jahreskontext.
     *
     * @return Collection<int, FixedAsset>
     */
    public function candidatesForYear(Organization $organization, AccountingFiscalYear $year): Collection {
        $startMonth = $year->starts_on->month;
        $startYear = $year->starts_on->year;

        /** @var Collection<int, FixedAsset> $result */
        $result = collect();

        $assets = FixedAsset::query()
            ->where('organization_id', $organization->id)
            ->whereDate('acquired_on', '<=', $year->ends_on->toDateString())
            ->with(['assetAccount', 'depreciationAccount'])
            ->orderBy('asset_no')
            ->get();

        foreach ($assets as $asset) {
            $row = $this->calculator->rowForYear($asset, $startYear, $startMonth);
            if ($row === null || $row->amount->isZero()) {
                continue;
            }

            $result->push($asset->forFiscalYear($year));
        }

        return $result;
    }

    public function sourceKey(Model $source): string {
        assert($source instanceof FixedAsset);
        $year = $source->depreciationYear;
        if (! $year instanceof AccountingFiscalYear) {
            throw new \LogicException('Depreciation source needs a fiscal year context (FixedAsset::forFiscalYear).');
        }

        return $this->keyFor($source, $year);
    }

    /** Idempotenzschlüssel `depreciation:{id}:{startjahr}`. */
    public function keyFor(FixedAsset $asset, AccountingFiscalYear $year): string {
        return $this->kind()->keyPrefix() . ':' . $asset->getKey() . ':' . $year->starts_on->year;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof FixedAsset);
        $year = $source->depreciationYear;
        if (! $year instanceof AccountingFiscalYear) {
            throw new \LogicException('Depreciation source needs a fiscal year context (FixedAsset::forFiscalYear).');
        }

        $bookedOn = $this->bookedOn($source, $year);
        $row = $this->calculator->rowForYear($source, $year->starts_on->year, $year->starts_on->month);
        $amount = $row?->amount->getAmount() ?? '0.00';

        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

        if ($row === null || $row->amount->isZero()) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }

        if ($year->status->isHardClosed()) {
            $blockers[] = (string) __('accounting.inbox.blocker.year_closed', ['year' => $year->label]);
        } else {
            $period = AccountingPeriod::query()
                ->where('organization_id', $organization->id)
                ->covering($bookedOn)
                ->first();
            if ($period instanceof AccountingPeriod && ! $period->status->acceptsPostings()) {
                $blockers[] = (string) __('accounting.inbox.blocker.period_closed', ['date' => CarbonFmt::fdate($bookedOn)]);
            }
        }

        $expenseLine = $this->lineFor($organization, $source, PostingAccountRole::Depreciation, $source->depreciationAccount, $amount, '0.00', $bookedOn, $blockers, $ruleVersions);
        if ($expenseLine instanceof PostingProposalLine) {
            $lines[] = $expenseLine;
        }

        $assetLine = $this->lineFor($organization, $source, PostingAccountRole::FixedAsset, $source->assetAccount, '0.00', $amount, $bookedOn, $blockers, $ruleVersions);
        if ($assetLine instanceof PostingProposalLine) {
            $lines[] = $assetLine;
        }

        $title = (string) __('accounting.inbox.memo.depreciation', [
            'year' => $year->label,
            'no' => $source->displayNo(),
            'name' => $source->name,
        ]);

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->keyFor($source, $year),
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
                'fiscal_year' => $year->label,
                'depreciation' => $row instanceof DepreciationScheduleRow ? $row->toArray() : null,
            ],
        );
    }

    /** Buchungsdatum: Geschäftsjahresende, beim Abgang im Jahr der Abgangstag. */
    public function bookedOn(FixedAsset $asset, AccountingFiscalYear $year): CarbonImmutable {
        $disposed = $asset->disposedOn();
        $yearEnd = CarbonImmutable::parse($year->ends_on)->startOfDay();

        if ($disposed instanceof CarbonImmutable && $disposed->lessThan($yearEnd) && $disposed->greaterThanOrEqualTo(CarbonImmutable::parse($year->starts_on))) {
            return $disposed;
        }

        return $yearEnd;
    }

    /**
     * Zeile aus dem Anlagenkonto oder — ohne Konto an der Anlage — aus der
     * Buchungsregel der Rolle. Ein inaktives Anlagenkonto zählt wie keines.
     *
     * @param  numeric-string  $debit
     * @param  numeric-string  $credit
     * @param  list<string>  $blockers
     * @param  list<string>  $ruleVersions
     */
    private function lineFor(
        Organization $organization,
        FixedAsset $asset,
        PostingAccountRole $role,
        ?AccountingAccount $explicit,
        string $debit,
        string $credit,
        CarbonImmutable $on,
        array &$blockers,
        array &$ruleVersions,
    ): ?PostingProposalLine {
        if ($explicit instanceof AccountingAccount && $explicit->is_active && (int) $explicit->organization_id === (int) $organization->id) {
            $ruleVersions[] = 'asset:' . $asset->getKey();

            return new PostingProposalLine(
                role: $role,
                account: $explicit,
                debit: $debit,
                credit: $credit,
                memo: $asset->displayNo() . ' ' . $asset->name,
                ruleVersion: 'asset:' . $asset->getKey(),
            );
        }

        $rule = $this->rule($organization, $role, [], $on);
        if ($rule === null) {
            $blockers[] = $this->missingRuleBlocker($role);

            return null;
        }

        $line = $this->line($role, $rule, $debit, $credit, $asset->displayNo() . ' ' . $asset->name);
        if ($line instanceof PostingProposalLine) {
            $ruleVersions[] = $rule->versionTag();
        }

        return $line;
    }
}
