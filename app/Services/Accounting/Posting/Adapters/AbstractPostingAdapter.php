<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractPostingAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\PostingAccountRole;
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingProfile, FixedAsset};
use App\Models\Finance\DatevBookingSource;
use App\Models\Platform\Organization;
use App\Services\Accounting\ExchangeRateService;
use App\Services\Accounting\Posting\{PostingProposalLine, PostingRuleResolver, PostingSourceAdapter};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Model;

/**
 * Gemeinsame Mechanik der Quellenadapter (Feature 125, MVP-673):
 * Kontenauflösung über die Regeln, Blocker-Texte und der Dublettenschutz
 * gegen bereits übergebene Belege.
 */
abstract class AbstractPostingAdapter implements PostingSourceAdapter {
    public function __construct(protected readonly PostingRuleResolver $rules) {}

    public function sourceKey(Model $source): string {
        return $this->kind()->keyPrefix() . ':' . $source->getKey();
    }

    /**
     * Konto für eine Rolle oder null. Der Aufrufer entscheidet, ob daraus ein
     * Blocker wird — manche Rollen sind fachlich optional (etwa die Steuer
     * bei einem steuerfreien Beleg).
     *
     * @param  array<string, mixed>  $context
     */
    protected function rule(Organization $organization, PostingAccountRole $role, array $context, CarbonImmutable $on): ?AccountingPostingRule {
        return $this->rules->resolve($organization, $this->kind(), $role, $context, $on);
    }

    /**
     * Blocker-Text für ein fehlendes Mapping — nennt Rolle und Merkmale.
     *
     * @param  array<string, mixed>  $context
     */
    protected function missingRuleBlocker(PostingAccountRole $role, array $context = []): string {
        $criteria = $context === []
            ? ''
            : ' (' . implode(', ', array_map(
                static fn (string $key, mixed $value): string => $key . '=' . (string) $value,
                array_keys($context),
                array_values($context),
            )) . ')';

        return (string) __('accounting.inbox.blocker.missing_rule', [
            'role' => $role->label(),
            'criteria' => $criteria,
        ]);
    }

    /**
     * @param  numeric-string  $debit
     * @param  numeric-string  $credit
     * @param  numeric-string|null  $taxAmount
     */
    protected function line(
        PostingAccountRole $role,
        AccountingPostingRule $rule,
        string $debit,
        string $credit,
        ?string $memo = null,
        ?string $counterpartyType = null,
        ?int $counterpartyId = null,
        ?string $taxAmount = null,
    ): ?PostingProposalLine {
        $account = $rule->account;
        if ($account === null) {
            return null;
        }

        return new PostingProposalLine(
            role: $role,
            account: $account,
            debit: $debit,
            credit: $credit,
            taxCodeId: $rule->accounting_tax_code_id,
            taxAmount: $taxAmount,
            memo: $memo,
            counterpartyType: $counterpartyType,
            counterpartyId: $counterpartyId,
            ruleVersion: $rule->versionTag(),
        );
    }

    /**
     * Ist die Quelle bereits in einem finalisierten DATEV-Stapel enthalten?
     * Dann hat sie ihr Konto schon außerhalb gefunden; ein zweites Mal wäre
     * eine zweite Wahrheit über denselben Vorgang.
     */
    protected function alreadyHandedOver(Model $source): bool {
        return DatevBookingSource::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereHas('batch', fn ($query) => $query->where('status', 'exported'))
            ->exists();
    }

    /**
     * Blockergrund, wenn der Beleg nicht auf die Basiswährung lautet
     * (§ 16 Abs. 6 UStG verlangt eine belegbare Umrechnung nach
     * BMF-Monatskursen; die gibt es im MVP nicht). Lieber gar keine Buchung
     * als eine, die einen Fremdwährungsbetrag als Euro ausgibt.
     */
    protected function foreignCurrencyBlocker(Organization $organization, mixed $currency): ?string {
        if ($currency === null || $currency === '') {
            return null;
        }

        $code = $currency instanceof CurrencyCode ? $currency->value : strtoupper((string) $currency);
        $base = $this->baseCurrency($organization);

        if ($code === $base->value) {
            return null;
        }

        return (string) __('accounting.inbox.blocker.foreign_currency', [
            'currency' => $code,
            'base' => $base->value,
        ]);
    }

    /**
     * Fremdwährungsbeleg zum Monatskurs des Belegdatums in die Basiswährung
     * (§ 16 Abs. 6 UStG, MVP-1012). Die Rundungsdifferenz trägt die größte Zeile
     * der kleineren Seite; Kurs und Originalbetrag gehen in den Nachweis.
     *
     * @param  list<PostingProposalLine>  $lines
     * @return array{lines: list<PostingProposalLine>, blocker: string|null, extra: array<string, mixed>}
     */
    protected function inBaseCurrency(Organization $organization, mixed $currency, CarbonImmutable $on, array $lines): array {
        $code = $currency instanceof CurrencyCode ? $currency : CurrencyCode::tryFrom(strtoupper((string) $currency));
        $base = $this->baseCurrency($organization);
        if ($code === null || $code === $base) {
            return ['lines' => $lines, 'blocker' => null, 'extra' => []];
        }
        $rate = app(ExchangeRateService::class)->rateFor($organization, $code, $on);
        if ($rate === null) {
            return ['lines' => $lines, 'blocker' => (string) __('accounting.inbox.blocker.no_exchange_rate', [
                'currency' => $code->value, 'month' => $on->format('m/Y'), 'base' => $base->value,
            ]), 'extra' => []];
        }

        $convert = static fn (string $amount): string => Decimal::of($amount, 2)->dividedBy($rate->rate, 2)->getValue();
        $converted = array_map(static fn (PostingProposalLine $line): PostingProposalLine => $line->withAmounts(
            $convert($line->debit),
            $convert($line->credit),
            $line->taxAmount !== null ? $convert($line->taxAmount) : null,
        ), $lines);

        $sum = static fn (array $items, string $side): Decimal => array_reduce($items, static fn (Decimal $carry, PostingProposalLine $line): Decimal => $carry->plus(Decimal::of($line->{$side}, 2)), Decimal::of('0', 2));
        $difference = $sum($converted, 'debit')->minus($sum($converted, 'credit'));
        if (! $difference->isZero()) {
            $side = $difference->isPositive() ? 'credit' : 'debit';
            $index = 0;
            foreach ($converted as $i => $candidate) {
                if (Decimal::of($candidate->{$side}, 2)->greaterThan(Decimal::of($converted[$index]->{$side}, 2))) {
                    $index = $i;
                }
            }
            $line = $converted[$index];
            $adjusted = Decimal::of($line->{$side}, 2)->plus($difference->abs())->getValue();
            $converted[$index] = $side === 'credit'
                ? $line->withAmounts($line->debit, $adjusted, $line->taxAmount)
                : $line->withAmounts($adjusted, $line->credit, $line->taxAmount);
        }

        return ['lines' => array_values($converted), 'blocker' => null, 'extra' => ['exchange_rate' => [
            'currency' => $code->value,
            'base' => $base->value,
            'period' => $rate->period->format('Y-m'),
            'rate' => $rate->rate->getValue(),
            'source' => $rate->source,
            'original_total' => $sum($lines, 'debit')->getValue(),
        ]]];
    }

    protected function baseCurrency(Organization $organization): CurrencyCode {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? $profile->base_currency : CurrencyCode::Euro;
    }

    /**
     * Zeile aus dem Anlagenkonto oder — ohne Konto an der Anlage — aus der
     * Buchungsregel der Rolle (AfA und Abgang, Feature 133). Ein inaktives Anlagenkonto zählt wie keines.
     *
     * @param  numeric-string  $debit
     * @param  numeric-string  $credit
     * @param  list<string>  $blockers
     * @param  list<string>  $ruleVersions
     */
    protected function fixedAssetLine(
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
