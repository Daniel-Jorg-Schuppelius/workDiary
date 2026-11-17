<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxAdvisoryDeadlineRuleSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines\RuleSets;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Fields\FieldType;
use App\Enums\Period\PeriodKind;
use App\Models\Customer\Customer;
use App\Services\Calendar\Filing\{FilingDeadlineCalculator, FilingProfile};
use App\Services\Classification\ClassificationResolver;
use App\Services\Customer\Deadlines\Dto\DeadlineSpec;
use App\Services\Fields\{FieldDefinition, FieldSchema, FieldValues};
use App\Support\{HolidayRegions, OrganizationContext};
use App\Support\Period\PeriodKey;

/**
 * Regelsatz Steuerberatung (Feature 197, MVP-1631 und MVP-1676) — Fristen der
 * Kunden einer Steuerkanzlei, eines Buchhaltungs- oder Lohnbüros nach
 * Gesetzesstand 2026:
 *
 * - USt-Voranmeldung: 10. Tag nach Ablauf des Zeitraums (§ 18 Abs. 1 UStG),
 *   Dauerfristverlängerung +1 Monat (§ 46 UStDV);
 * - Sondervorauszahlung: 10.02. bei monatlicher Abgabe mit Verlängerung
 *   (§ 47, § 48 Abs. 1 UStDV);
 * - Lohnsteuer-Anmeldung: 10. Tag nach Ablauf des Zeitraums, ohne
 *   Verlängerung (§ 41a Abs. 1 und 2 EStG);
 * - Zusammenfassende Meldung: 25. Tag nach Ablauf des Meldezeitraums, ohne
 *   Verlängerung (§ 18a Abs. 1 UStG);
 * - Jahreserklärungen je Leistung `steuererklaerung` im Leistungsumfang
 *   (Produktgruppe = Steuerart): § 149 Abs. 2 und 3 AO, Übergang 2020–2024
 *   nach Art. 97 § 36 EGAO; eine Vorabanforderung (§ 149 Abs. 4 AO) setzt
 *   der Nutzer an der Frist von Hand;
 * - Sozialversicherung für Kunden mit der Leistung `lohn`: Beitragsnachweis
 *   und Beitragsfälligkeit (§ 28f Abs. 3, § 23 Abs. 1 SGB IV), je Bundesland
 *   der Einzugsstellen, frühester Termin.
 *
 * Feiertage am Ort des Finanzamts (§ 108 Abs. 3 AO); fehlt das Bundesland,
 * gilt die Region der Organisation und die Frist ist markiert. Der Merkmal
 * OSS (§ 18j UStG) ist eine Zusatzspalte des Leistungsplans; eine eigene
 * Frist entsteht daraus in diesem Regelsatz nicht.
 */
final class TaxAdvisoryDeadlineRuleSet extends AbstractCustomerDeadlineRuleSet {
    public const string KEY = 'tax_advisory';

    /** Eintragsarten (Slugs aus dem Branchenprofil, Feature 198). */
    public const string ENTRY_RETURN = 'steuererklaerung';

    public const string ENTRY_PAYROLL = 'lohn';

    public const string ENTRY_ADVANCE = 'voranmeldung';

    /** Präfix der Fristart Jahreserklärung; dahinter der Code der Steuerart (Produktgruppe). */
    public const string ANNUAL_RETURN = 'annual_return';

    /** @var list<string> */
    private const array INTERVALS = ['monthly', 'quarterly', 'annual', 'none'];

    /** @var list<string> */
    private const array CHANNELS = ['portal', 'upload', 'email', 'paper', 'datev'];

    public function __construct(
        private readonly FilingDeadlineCalculator $calculator,
        private readonly ClassificationResolver $classifications,
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('customer_deadline.rule_set.' . self::KEY);
    }

    public function kinds(): array {
        $kinds = [];
        foreach (['vat_advance', 'vat_special_prepayment', 'wage_tax', 'recapitulative', self::ANNUAL_RETURN, 'contribution_statement', 'contribution_due'] as $kind) {
            $kinds[$kind] = (string) __('customer_deadline.kind.' . $kind);
        }

        return $kinds;
    }

    public function kindLabel(string $kind): string {
        if (str_starts_with($kind, self::ANNUAL_RETURN . ':')) {
            $code = substr($kind, strlen(self::ANNUAL_RETURN) + 1);
            $classification = $this->classifications->resolveCode(OrganizationContext::currentId(), ClassificationDomain::ProductGroup, $code);

            return (string) __('customer_deadline.kind.annual_return_of', ['tax' => $classification?->displayLabel() ?? $code]);
        }

        return parent::kindLabel($kind);
    }

    public function traitSchema(): FieldSchema {
        $intervals = $this->options('interval', self::INTERVALS);
        $states = HolidayRegions::subdivisions('de');

        return new FieldSchema([
            $this->choice('vat_interval', (string) __('customer_deadline.trait.vat_interval'), $intervals, required: true),
            $this->flag('vat_extension', (string) __('customer_deadline.trait.vat_extension'), (string) __('customer_deadline.trait_help.vat_extension')),
            $this->choice('wage_tax_interval', (string) __('customer_deadline.trait.wage_tax_interval'), $intervals, required: true),
            $this->choice('recapitulative_interval', (string) __('customer_deadline.trait.recapitulative_interval'), $this->options('interval', ['none', 'monthly', 'quarterly'])),
            $this->flag('oss', (string) __('customer_deadline.trait.oss')),
            $this->flag('tax_advised', (string) __('customer_deadline.trait.tax_advised'), (string) __('customer_deadline.trait_help.tax_advised')),
            new FieldDefinition(key: 'fiscal_year_start', type: FieldType::Number, label: (string) __('customer_deadline.trait.fiscal_year_start'), help: (string) __('customer_deadline.trait_help.fiscal_year_start'), min: 1, max: 12, step: 1, listed: true),
            $this->flag('agriculture', (string) __('customer_deadline.trait.agriculture'), (string) __('customer_deadline.trait_help.agriculture')),
            $this->choice('tax_office_state', (string) __('customer_deadline.trait.tax_office_state'), $states, help: (string) __('customer_deadline.trait_help.tax_office_state')),
            $this->choice('contribution_states', (string) __('customer_deadline.trait.contribution_states'), $states, multi: true, help: (string) __('customer_deadline.trait_help.contribution_states')),
            $this->choice('document_channel', (string) __('customer_deadline.trait.document_channel'), $this->options('channel', self::CHANNELS)),
        ]);
    }

    public function derivedTraits(Customer $customer): array {
        return [];
    }

    public function deadlines(Customer $customer, FieldValues $traits, int $year): array {
        $state = $this->validRegion($traits->get('tax_office_state'));
        $assumed = $state === null;
        $profile = new FilingProfile(
            vatInterval: $this->periodKind($traits->get('vat_interval')),
            vatExtension: $traits->get('vat_extension') === true,
            // Ohne Angabe beraten: der Kunde einer Kanzlei ist es in aller Regel (§ 149 Abs. 3 AO).
            taxAdvised: $traits->get('tax_advised') !== false,
            holidayRegion: $state ?? $this->organizationRegion($customer),
            agriculture: $traits->get('agriculture') === true,
            fiscalYearStartMonth: max(1, min(12, (int) ($traits->get('fiscal_year_start') ?? 1))),
        );

        $specs = [];
        if ($profile->vatInterval !== null) {
            foreach ($this->periodsOf($profile->vatInterval, $year) as $period) {
                $specs[] = new DeadlineSpec('vat_advance', $period, $this->calculator->vatAdvance($profile, $period), $assumed);
            }
            if ($profile->vatInterval === PeriodKind::Month && $profile->vatExtension) {
                $specs[] = new DeadlineSpec('vat_special_prepayment', PeriodKey::of(PeriodKind::Year, $year), $this->calculator->specialPrepayment($profile, $year), $assumed);
            }
        }

        $wageKind = $this->periodKind($traits->get('wage_tax_interval'), withYear: true);
        if ($wageKind !== null) {
            foreach ($this->periodsOf($wageKind, $year) as $period) {
                $specs[] = new DeadlineSpec('wage_tax', $period, $this->calculator->wageTaxAdvance($profile, $period), $assumed);
            }
        }

        $recapitulativeKind = $this->periodKind($traits->get('recapitulative_interval'));
        if ($recapitulativeKind !== null) {
            foreach ($this->periodsOf($recapitulativeKind, $year) as $period) {
                $specs[] = new DeadlineSpec('recapitulative', $period, $this->calculator->recapitulative($profile, $period), $assumed);
            }
        }

        $yearKey = PeriodKey::of(PeriodKind::Year, $year);
        $returns = $this->services($customer, self::ENTRY_RETURN, $year);
        $annualDue = $this->calculator->annualReturn($profile, $year);
        $seen = [];
        foreach ($returns as $service) {
            $kind = self::ANNUAL_RETURN . ($service->product_group_code !== null ? ':' . $service->product_group_code : '');
            if (isset($seen[$kind]) || ! $service->coversPeriod($yearKey)) {
                continue;
            }
            $seen[$kind] = true;
            $specs[] = new DeadlineSpec($kind, $yearKey, $annualDue, $assumed);
        }

        $payroll = $this->services($customer, self::ENTRY_PAYROLL, $year);
        if ($payroll->isNotEmpty()) {
            $regions = array_values(array_filter(array_map($this->validRegion(...), (array) ($traits->get('contribution_states') ?? []))));
            $regionsAssumed = $regions === [];
            if ($regionsAssumed) {
                $regions = [$this->organizationRegion($customer)];
            }
            foreach ($this->periodsOf(PeriodKind::Month, $year) as $month) {
                if (! $this->coveredBy($payroll, $month)) {
                    continue;
                }
                $specs[] = new DeadlineSpec('contribution_statement', $month, $this->calculator->contributionStatement($regions, $month), $regionsAssumed);
                $specs[] = new DeadlineSpec('contribution_due', $month, $this->calculator->contributionDue($regions, $month), $regionsAssumed);
            }
        }

        return $specs;
    }

    /**
     * Zuordnung Auftrag → Fristart: Voranmeldung Umsatzsteuer erledigt die
     * USt-Voranmeldung, Voranmeldung Lohnsteuer oder Lohn die
     * Lohnsteuer-Anmeldung, Lohn den Beitragsnachweis und die Fälligkeit,
     * Steuererklärung mit der Steuerart die Jahreserklärung. Sondervorauszahlung
     * und Zusammenfassende Meldung erledigt nur eine Person ausdrücklich.
     */
    public function completes(string $kind, string $entryTypeSlug, ?string $productGroup): bool {
        if (str_starts_with($kind, self::ANNUAL_RETURN)) {
            $code = $kind === self::ANNUAL_RETURN ? null : substr($kind, strlen(self::ANNUAL_RETURN) + 1);

            return $entryTypeSlug === self::ENTRY_RETURN && $productGroup === $code;
        }

        return match ($kind) {
            'vat_advance' => $entryTypeSlug === self::ENTRY_ADVANCE && in_array($productGroup, [null, 'umsatzsteuer'], true),
            'wage_tax' => $entryTypeSlug === self::ENTRY_PAYROLL || ($entryTypeSlug === self::ENTRY_ADVANCE && $productGroup === 'lohnsteuer'),
            'contribution_statement', 'contribution_due' => $entryTypeSlug === self::ENTRY_PAYROLL,
            default => false,
        };
    }

    /** Turnus-Merkmal → Periodenart; „jährlich“ nur, wo es eine jährliche Anmeldung gibt (Lohnsteuer). */
    private function periodKind(mixed $value, bool $withYear = false): ?PeriodKind {
        return match ($value) {
            'monthly' => PeriodKind::Month,
            'quarterly' => PeriodKind::Quarter,
            'annual' => $withYear ? PeriodKind::Year : null,
            default => null,
        };
    }

    /** @return list<PeriodKey> Perioden der Art im Jahr */
    private function periodsOf(PeriodKind $kind, int $year): array {
        $periods = [];
        for ($ordinal = 1; $ordinal <= $kind->perYear(); $ordinal++) {
            $periods[] = PeriodKey::of($kind, $year, $ordinal);
        }

        return $periods;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function options(string $group, array $values): array {
        $out = [];
        foreach ($values as $value) {
            $out[$value] = (string) __('customer_deadline.option.' . $group . '.' . $value);
        }

        return $out;
    }
}
