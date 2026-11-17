<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HomeCareDeadlineRuleSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines\RuleSets;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\Classification;
use App\Models\Customer\Customer;
use App\Services\Calendar\HolidayService;
use App\Services\Customer\Deadlines\Dto\DeadlineSpec;
use App\Services\Fields\{FieldSchema, FieldValues};
use App\Support\Period\PeriodKey;

/**
 * Regelsatz ambulante Pflege (Feature 197, MVP-1673): Beratungsbesuch nach
 * § 37 Abs. 3 SGB XI in der Fassung ab 01.01.2026 (Gesetz zur
 * Befugniserweiterung und Entbürokratisierung in der Pflege, BGBl. 2025 I
 * Nr. 371):
 *
 * - Pflicht nur bei Bezug von Pflegegeld (§ 37 Abs. 1) und Pflegegrad 2–5:
 *   einmal je Kalenderhalbjahr;
 * - Pflegegrad 4–5 auf Wunsch vierteljährlich — dann je Quartal;
 * - Pflegegrad 1, reine Sachleistung und Kombinationsleistung (§ 38): Anspruch,
 *   keine Pflicht, also keine Frist.
 *
 * Der Pflegegrad kommt aus der Klassifikation `pflegegrad` am Kunden und wird
 * nicht doppelt gepflegt. Frist ist der letzte Werktag der Periode am Sitz der
 * Organisation: der Besuch muss in der Periode stattfinden. Erledigt wird über
 * einen abgeschlossenen Auftrag `beratungsbesuch` mit derselben Periode.
 */
final class HomeCareDeadlineRuleSet extends AbstractCustomerDeadlineRuleSet {
    public const string KEY = 'home_care';

    public const string KIND = 'counselling_visit';

    /** Eintragsart (Slug aus dem Pflegeprofil, Feature 198). */
    public const string ENTRY_VISIT = 'beratungsbesuch';

    /** @var list<string> */
    private const array BENEFITS = ['care_allowance', 'combination', 'in_kind'];

    public function __construct(private readonly HolidayService $holidays) {}

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('customer_deadline.rule_set.' . self::KEY);
    }

    public function kinds(): array {
        return [self::KIND => (string) __('customer_deadline.kind.' . self::KIND)];
    }

    public function traitSchema(): FieldSchema {
        $benefits = [];
        foreach (self::BENEFITS as $benefit) {
            $benefits[$benefit] = (string) __('customer_deadline.option.benefit.' . $benefit);
        }

        return new FieldSchema([
            $this->choice('benefit', (string) __('customer_deadline.trait.benefit'), $benefits, help: (string) __('customer_deadline.trait_help.benefit'), required: true),
            $this->flag('quarterly_requested', (string) __('customer_deadline.trait.quarterly_requested'), (string) __('customer_deadline.trait_help.quarterly_requested')),
        ]);
    }

    public function derivedTraits(Customer $customer): array {
        $grade = $this->careGrade($customer);

        return [(string) __('customer_deadline.trait.care_grade') => $grade !== null ? (string) __('customer_deadline.care_grade', ['grade' => $grade]) : (string) __('customer_deadline.care_grade_missing')];
    }

    public function deadlines(Customer $customer, FieldValues $traits, int $year): array {
        $grade = $this->careGrade($customer);
        if ($grade === null || $grade < 2 || $traits->get('benefit') !== 'care_allowance') {
            return [];
        }
        $kind = $grade >= 4 && $traits->get('quarterly_requested') === true ? PeriodKind::Quarter : PeriodKind::HalfYear;
        $region = $this->organizationRegion($customer);

        $specs = [];
        for ($ordinal = 1; $ordinal <= $kind->perYear(); $ordinal++) {
            $period = PeriodKey::of($kind, $year, $ordinal);
            $specs[] = new DeadlineSpec(self::KIND, $period, $this->holidays->previousStatutoryBusinessDay($period->end(), $region));
        }

        return $specs;
    }

    public function completes(string $kind, string $entryTypeSlug, ?string $productGroup): bool {
        return $kind === self::KIND && $entryTypeSlug === self::ENTRY_VISIT;
    }

    /** Höchster Pflegegrad (1–5) aus der Klassifikation am Kunden; null ohne Angabe. */
    public function careGrade(Customer $customer): ?int {
        $codes = Classification::query()->withoutGlobalScopes()
            ->join('classifiables', 'classifiables.classification_id', '=', 'classifications.id')
            ->where('classifiables.classifiable_type', $customer->getMorphClass())
            ->where('classifiables.classifiable_id', $customer->id)
            ->where('classifications.domain', ClassificationDomain::Pflegegrad->value)
            ->pluck('classifications.code');

        $grade = null;
        foreach ($codes as $code) {
            if (is_string($code) && preg_match('/^pg([1-5])$/', $code, $match) === 1) {
                $grade = max($grade ?? 0, (int) $match[1]);
            }
        }

        return $grade;
    }
}
