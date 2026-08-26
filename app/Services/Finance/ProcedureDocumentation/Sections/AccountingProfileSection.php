<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingProfileSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Models\Accounting\{AccountingAccount, AccountingFiscalYear, AccountingProfile};
use App\Models\Organization;
use App\Services\Accounting\{AccountingSovereigntyResolver, ChartOfAccountsTemplateService, TaxationMethodResolver, VatFilingProfileResolver};
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};

/**
 * Buchhaltungsprofil (MVP-671 ff.): Buchungshoheit, Gewinnermittlung,
 * Basiswährung, Geschäftsjahr, Versteuerungsart, Meldezeitraum, Kontenplan
 * (erkannter Kontenrahmen über die Vorlagen-Überdeckung) und Geschäftsjahre.
 */
final class AccountingProfileSection implements ProcedureSection {
    use FormatsSectionValues;

    /** Ab dieser Überdeckung der Vorlagenkonten gilt ein Kontenrahmen als erkannt. */
    private const TEMPLATE_MATCH_RATIO = 0.5;

    public function __construct(
        private readonly AccountingSovereigntyResolver $sovereignty,
        private readonly TaxationMethodResolver $taxation,
        private readonly VatFilingProfileResolver $filing,
        private readonly ChartOfAccountsTemplateService $templates,
    ) {}

    public function key(): string {
        return 'accounting';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.accounting');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $orgId = (int) $organization->id;
        /** @var AccountingProfile|null $profile */
        $profile = AccountingProfile::query()->withoutGlobalScopes()->where('organization_id', $orgId)->with('activatedBy:id,name')->first();

        if ($profile === null) {
            return [
                'fields' => [
                    'sovereignty' => $this->field('procedure-documentation.accounting.sovereignty', $this->sovereignty->at($organization)->label()),
                ],
                'notes' => [(string) __('procedure-documentation.accounting.none')],
            ];
        }

        $accountNumbers = array_values(AccountingAccount::query()->withoutGlobalScopes()->where('organization_id', $orgId)->pluck('number')
            ->map(static fn ($n): string => (string) $n)->all());

        $fiscalYears = [];
        foreach (AccountingFiscalYear::query()->withoutGlobalScopes()->where('organization_id', $orgId)->orderBy('starts_on')->get() as $year) {
            $fiscalYears[] = [
                $this->text($year->label),
                $this->date($year->starts_on),
                $this->date($year->ends_on),
                $year->status->label(),
                $this->dateTime($year->closed_at),
            ];
        }

        return [
            'fields' => [
                'sovereignty' => $this->field('procedure-documentation.accounting.sovereignty', $profile->sovereignty->label()),
                'external_provider' => $this->field('procedure-documentation.accounting.external_provider', $profile->external_provider),
                'profit_determination' => $this->field('procedure-documentation.accounting.profit_determination', $profile->profit_determination->label()),
                'base_currency' => $this->field('procedure-documentation.accounting.base_currency', $profile->base_currency->value),
                'fiscal_year_start_month' => $this->field('procedure-documentation.accounting.fiscal_year_start_month', $profile->fiscal_year_start_month),
                'starts_on' => $this->field('procedure-documentation.accounting.starts_on', $this->date($profile->starts_on)),
                'activated_at' => $this->field('procedure-documentation.accounting.activated_at', $this->dateTime($profile->activated_at) . ($profile->activatedBy !== null ? ' · ' . $profile->activatedBy->name : '')),
                'taxation_method' => $this->field('procedure-documentation.accounting.taxation_method', $this->taxation->at($organization)->label()),
                'vat_filing_interval' => $this->field('procedure-documentation.accounting.vat_filing_interval', $this->filing->at($organization)->label()),
                'accounts_count' => $this->field('procedure-documentation.accounting.accounts_count', count($accountNumbers)),
                'chart_template' => $this->field('procedure-documentation.accounting.chart_template', $this->detectTemplate($accountNumbers)),
            ],
            'tables' => [
                'fiscal_years' => [
                    'title' => (string) __('procedure-documentation.accounting.table.fiscal_years'),
                    'columns' => [
                        (string) __('procedure-documentation.accounting.col.label'),
                        (string) __('procedure-documentation.accounting.col.from'),
                        (string) __('procedure-documentation.accounting.col.to'),
                        (string) __('procedure-documentation.accounting.col.status'),
                        (string) __('procedure-documentation.accounting.col.closed'),
                    ],
                    'rows' => $fiscalYears,
                ],
            ],
        ];
    }

    /**
     * Der Kontenrahmen wird nicht gespeichert (Vorlagen sind additiv) —
     * erkannt wird die Vorlage mit der größten Überdeckung ihrer Kontonummern.
     *
     * @param  list<string>  $accountNumbers
     */
    private function detectTemplate(array $accountNumbers): ?string {
        if ($accountNumbers === []) {
            return null;
        }
        $present = array_fill_keys($accountNumbers, true);
        $best = null;
        $bestRatio = 0.0;
        foreach ($this->templates->available() as $template) {
            /** @var list<array<string, mixed>> $accounts */
            $accounts = is_array($template['accounts'] ?? null) ? $template['accounts'] : [];
            $total = count($accounts);
            if ($total === 0) {
                continue;
            }
            $hits = 0;
            foreach ($accounts as $account) {
                if (isset($present[(string) ($account['number'] ?? '')])) {
                    $hits++;
                }
            }
            $ratio = $hits / $total;
            if ($ratio > $bestRatio) {
                $bestRatio = $ratio;
                $best = ['name' => (string) ($template['name'] ?? $template['code'] ?? '?'), 'hits' => $hits, 'total' => $total];
            }
        }
        if ($best === null || $bestRatio < self::TEMPLATE_MATCH_RATIO) {
            return null;
        }

        return (string) __('procedure-documentation.accounting.chart_template_detected', $best);
    }
}
