<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisclosesLabourCosts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\Invoicing\LabourCostDisclosure;
use App\Models\Platform\Organization;
use App\Settings\SettingsRegistry;
use CommonToolkit\ValueObjects\Money;

/**
 * Ausweis der Arbeitskosten nach § 35a EStG am Belegkopf (MVP-1053): die
 * Organisationsregel gilt, bis der Beleg sie mit `is_labour_cost_disclosed`
 * überschreibt. Gerechnet wird über
 * {@see \App\Services\Billing\DocumentTotalsCalculator::labourCosts()}.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait DisclosesLabourCosts {
    /** @return array{net: Money, tax: Money, gross: Money, undetermined: int}|null */
    abstract public function labourCosts(): ?array;

    public function disclosesLabourCosts(): bool {
        $override = $this->getAttribute('is_labour_cost_disclosed');
        if ($override !== null) {
            return (bool) $override;
        }
        $organization = $this->getRelationValue('organization');
        $customer = $this->getRelationValue('customer');

        return self::labourCostDisclosureRule($organization instanceof Organization ? $organization : null)
            ->appliesTo($customer instanceof \App\Models\Customer\Customer ? $customer : null);
    }

    /**
     * Ausweis für die Ausgabe: `null`, wenn der Beleg nicht ausweist oder keine Position einen Anteil trägt.
     *
     * @return array{net: Money, tax: Money, gross: Money, undetermined: int}|null
     */
    public function disclosedLabourCosts(): ?array {
        return $this->disclosesLabourCosts() ? $this->labourCosts() : null;
    }

    public static function labourCostDisclosureRule(?Organization $organization): LabourCostDisclosure {
        if ($organization === null) {
            return LabourCostDisclosure::Off;
        }

        return LabourCostDisclosure::tryFrom((string) app(SettingsRegistry::class)->effective('invoicing.labour_cost_disclosure', $organization)->value)
            ?? LabourCostDisclosure::Off;
    }
}
