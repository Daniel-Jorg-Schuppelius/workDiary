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
use App\Models\Accounting\{AccountingPostingRule, AccountingProfile};
use App\Models\Finance\DatevBookingSource;
use App\Models\Organization;
use App\Services\Accounting\Posting\{PostingProposalLine, PostingRuleResolver, PostingSourceAdapter};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
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
            ->where('source_type', $source::class)
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

    protected function baseCurrency(Organization $organization): CurrencyCode {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? $profile->base_currency : CurrencyCode::Euro;
    }

    protected function money(mixed $value): string {
        return number_format((float) (is_object($value) && method_exists($value, 'getAmount') ? $value->getAmount() : $value), 2, '.', '');
    }
}
