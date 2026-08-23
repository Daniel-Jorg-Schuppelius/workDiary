<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartOfAccountsTemplateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountType, EuerCategory, PostingAccountRole, PostingSourceKind, TaxCodeDirection};
use App\Models\Accounting\{AccountingAccount, AccountingPostingRule, AccountingTaxCode};
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, File};
use Illuminate\Validation\ValidationException;

/**
 * Kontenplan-Vorlagen (Feature 125, MVP-678).
 *
 * Eine Vorlage bringt Konten, Steuerkennzeichen **und** Buchungsregeln mit —
 * erst zusammen ergeben sie einen benutzbaren Einstieg. Ein Kontenplan ohne
 * Regeln wäre eine Liste, die bei jedem Beleg blockiert.
 *
 * Die Anwendung ist additiv und idempotent: Vorhandene Kontonummern bleiben
 * unangetastet, nur Fehlendes kommt dazu. Ein zweiter Lauf ändert nichts —
 * und ein selbst gepflegtes Konto wird nie überschrieben.
 *
 * Rechtlicher Rahmen der Vorlagen: siehe Kopf der Vorlagendateien und
 * `kontenrahmen-lizenzrecherche-2026-08.md` im Architektur-Repo.
 */
class ChartOfAccountsTemplateService {
    public function __construct(private readonly ?string $path = null) {}

    public function directory(): string {
        return $this->path ?? database_path('data/chartofaccounts');
    }

    /**
     * Verfügbare Vorlagen (Code → Metadaten).
     *
     * @return array<string, array<string, mixed>>
     */
    public function available(): array {
        $templates = [];

        if (! File::isDirectory($this->directory())) {
            return $templates;
        }

        foreach (File::files($this->directory()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            /** @var array<string, mixed> $template */
            $template = require $file->getPathname();
            if (! isset($template['code'])) {
                continue;
            }

            $templates[(string) $template['code']] = $template;
        }

        ksort($templates);

        return $templates;
    }

    /** @return array<string, mixed> */
    public function get(string $code): array {
        $templates = $this->available();
        if (! isset($templates[$code])) {
            throw ValidationException::withMessages([
                'template' => (string) __('accounting.template.error.unknown', ['code' => $code]),
            ]);
        }

        return $templates[$code];
    }

    /**
     * Vorlage auf eine Organisation anwenden.
     *
     * @return array{accounts: int, tax_codes: int, rules: int, skipped: int}
     */
    public function apply(Organization $organization, string $code, ?CarbonImmutable $validFrom = null): array {
        $template = $this->get($code);
        $validFrom ??= CarbonImmutable::now()->startOfYear();

        return DB::transaction(function () use ($organization, $template, $validFrom): array {
            $accounts = $this->applyAccounts($organization, $template['accounts'] ?? []);
            $taxCodes = $this->applyTaxCodes($organization, $template['tax_codes'] ?? [], $accounts['byNumber'], $validFrom);
            $rules = $this->applyRules($organization, $template['rules'] ?? [], $accounts['byNumber'], $taxCodes['byCode'], $validFrom);

            return [
                'accounts' => $accounts['created'],
                'tax_codes' => $taxCodes['created'],
                'rules' => $rules['created'],
                'skipped' => $accounts['skipped'] + $taxCodes['skipped'] + $rules['skipped'],
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array{created: int, skipped: int, byNumber: array<string, AccountingAccount>}
     */
    private function applyAccounts(Organization $organization, array $definitions): array {
        $created = 0;
        $skipped = 0;
        $byNumber = [];

        foreach ($definitions as $definition) {
            $number = (string) $definition['number'];
            $existing = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $number)
                ->first();

            if ($existing instanceof AccountingAccount) {
                $byNumber[$number] = $existing;
                $skipped++;

                continue;
            }

            $type = AccountType::from((string) $definition['type']);
            $byNumber[$number] = AccountingAccount::query()->create([
                'organization_id' => $organization->id,
                'number' => $number,
                'name' => (string) $definition['name'],
                'type' => $type,
                'normal_balance' => $type->normalBalance(),
                'is_open_item' => (bool) ($definition['is_open_item'] ?? false),
                'is_bank' => (bool) ($definition['is_bank'] ?? false),
                'is_cash' => (bool) ($definition['is_cash'] ?? false),
                'is_clearing' => (bool) ($definition['is_clearing'] ?? false),
                'euer_category' => isset($definition['euer_category']) ? EuerCategory::from((string) $definition['euer_category']) : null,
                'deductible_percent' => (string) ($definition['deductible_percent'] ?? '100.00'),
                // Die Vorlage trägt die eigene Nummer als DATEV-Zuordnung ein;
                // wer abweichende Konten führt, ändert das Feld am Konto.
                'datev_account' => $number,
                'is_active' => true,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'byNumber' => $byNumber];
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @param  array<string, AccountingAccount>  $accounts
     * @return array{created: int, skipped: int, byCode: array<string, AccountingTaxCode>}
     */
    private function applyTaxCodes(Organization $organization, array $definitions, array $accounts, CarbonImmutable $validFrom): array {
        $created = 0;
        $skipped = 0;
        $byCode = [];

        foreach ($definitions as $definition) {
            $code = (string) $definition['code'];
            $existing = AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->where('code', $code)
                ->first();

            if ($existing instanceof AccountingTaxCode) {
                $byCode[$code] = $existing;
                $skipped++;

                continue;
            }

            $taxAccount = $definition['tax_account'] !== null
                ? ($accounts[(string) $definition['tax_account']] ?? null)
                : null;

            $byCode[$code] = AccountingTaxCode::query()->create([
                'organization_id' => $organization->id,
                'code' => $code,
                'name' => (string) $definition['name'],
                'direction' => TaxCodeDirection::from((string) $definition['direction']),
                'rate' => (string) $definition['rate'],
                'tax_account_id' => $taxAccount?->id,
                'valid_from' => $validFrom->toDateString(),
                'is_active' => true,
                'ustva_base_field' => isset($definition['ustva_base_field']) ? (string) $definition['ustva_base_field'] : null,
                'ustva_tax_field' => isset($definition['ustva_tax_field']) ? (string) $definition['ustva_tax_field'] : null,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'byCode' => $byCode];
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @param  array<string, AccountingAccount>  $accounts
     * @param  array<string, AccountingTaxCode>  $taxCodes
     * @return array{created: int, skipped: int}
     */
    private function applyRules(Organization $organization, array $definitions, array $accounts, array $taxCodes, CarbonImmutable $validFrom): array {
        $created = 0;
        $skipped = 0;

        foreach ($definitions as $definition) {
            $account = $accounts[(string) $definition['account']] ?? null;
            if (! $account instanceof AccountingAccount) {
                $skipped++;

                continue;
            }

            $kind = PostingSourceKind::from((string) $definition['source_kind']);
            $role = PostingAccountRole::from((string) $definition['role']);
            $match = $definition['match'] ?? null;

            // Bereits gepflegte Regeln bleiben: Die Vorlage ergänzt, sie
            // überschreibt keine Entscheidung der Organisation.
            $exists = AccountingPostingRule::query()
                ->where('organization_id', $organization->id)
                ->where('source_kind', $kind->value)
                ->where('role', $role->value)
                ->get()
                ->contains(fn (AccountingPostingRule $rule): bool => ($rule->match_criteria ?? null) == $match);

            if ($exists) {
                $skipped++;

                continue;
            }

            AccountingPostingRule::query()->create([
                'organization_id' => $organization->id,
                'source_kind' => $kind,
                'role' => $role,
                'accounting_account_id' => $account->id,
                'accounting_tax_code_id' => isset($definition['tax_code'])
                    ? ($taxCodes[(string) $definition['tax_code']] ?? null)?->id
                    : null,
                'match_criteria' => $match,
                'priority' => (int) ($definition['priority'] ?? 100),
                'version' => 1,
                'valid_from' => $validFrom->toDateString(),
                'is_active' => true,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
