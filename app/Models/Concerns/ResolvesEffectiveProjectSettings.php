<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesEffectiveProjectSettings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\ProjectBillingRule;
use App\Support\Setting;

/**
 * Effektive Projekt-Einstellungen mit Vererbung (eigener Wert > Parent
 * rekursiv > Kunde bzw. Org-Setting): Stundensätze, Abrechenbarkeit,
 * Wetter-Abruf, Abrechnungs-Taktung und Billing-Regeln. Aus dem
 * Project-Modell extrahiert (Refactoring Welle 2, B6b).
 *
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property bool|null $billable
 * @property bool|null $weather_auto_fetch
 * @property int|null $billing_increment_minutes
 * @property int|null $billing_grouping_gap_minutes
 * @property \App\Models\Project|null $parent
 * @property \App\Models\Customer|null $customer
 */
trait ResolvesEffectiveProjectSettings {
    /**
     * Stundensatz mit Vererbung: eigener Wert > Parent (rekursiv) > Customer.
     */
    public function effectiveHourlyRate(): ?float {
        if ($this->hourly_rate !== null) {
            return (float) $this->hourly_rate;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveHourlyRate();
        }

        return $this->customer?->hourly_rate !== null ? (float) $this->customer->hourly_rate : null;
    }

    public function effectiveInternalRate(): ?float {
        if ($this->internal_rate !== null) {
            return (float) $this->internal_rate;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveInternalRate();
        }

        return $this->customer?->internal_rate !== null ? (float) $this->customer->internal_rate : null;
    }

    public function effectiveBillable(): bool {
        if ($this->billable !== null) {
            return (bool) $this->billable;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveBillable();
        }

        return (bool) ($this->customer->billable ?? true);
    }

    /**
     * Automatischer Wetter-Abruf mit Vererbung (Feature 062, Rang 12):
     * eigener Wert > Parent (rekursiv) > Org-Setting `weather.auto_fetch` > false.
     * null bedeutet „erben".
     */
    public function effectiveWeatherAutoFetch(): bool {
        if ($this->weather_auto_fetch !== null) {
            return (bool) $this->weather_auto_fetch;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveWeatherAutoFetch();
        }

        return (bool) Setting::get('weather.auto_fetch', false);
    }

    /**
     * Abrechnungs-Taktung in Minuten mit Vererbung:
     * eigener Wert > Parent (rekursiv) > Kunde > 1 (minutengenau).
     */
    public function effectiveBillingIncrement(): int {
        if ($this->billing_increment_minutes !== null) {
            return max(1, (int) $this->billing_increment_minutes);
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveBillingIncrement();
        }
        $customerValue = $this->customer?->billing_increment_minutes;

        return $customerValue !== null ? max(1, (int) $customerValue) : 1;
    }

    /**
     * Max. Lücke (Minuten), bis zu der Einträge zusammengefasst werden, mit
     * Vererbung: eigener Wert > Parent (rekursiv) > Kunde > 0 (keine Zusammenfassung).
     */
    public function effectiveBillingGroupingGap(): int {
        if ($this->billing_grouping_gap_minutes !== null) {
            return max(0, (int) $this->billing_grouping_gap_minutes);
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveBillingGroupingGap();
        }
        $customerValue = $this->customer?->billing_grouping_gap_minutes;

        return $customerValue !== null ? max(0, (int) $customerValue) : 0;
    }

    /**
     * Liefert die passendste Billing-Regel für ein Kind (kind-Match vor Fallback,
     * höchste priority). Fällt rekursiv auf Parent-Projekt zurück.
     */
    public function resolveBillingRule(?string $kind, string $plugin = 'lexoffice'): ?ProjectBillingRule {
        $rule = $this->billingRules()
            ->where('plugin_id', $plugin)
            ->forKind($kind)
            ->first();
        if ($rule !== null) {
            return $rule;
        }

        return $this->parent?->resolveBillingRule($kind, $plugin);
    }
}
