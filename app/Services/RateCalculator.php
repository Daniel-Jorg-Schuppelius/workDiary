<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RateCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Models\Billing\CustomerBillingRate;
use App\Models\TimeEntry;
use App\Services\Billing\AgreementRateResolver;

/**
 * Calculates billable revenue and internal cost for a TimeEntry following the
 * Kimai rate hierarchy: TimeEntry override -> Kundenkondition (Feature 098)
 * -> User -> Activity (Task) -> Project -> Customer.
 *
 * A non-null fixed_rate on the entry overrides hourly calculation and yields a
 * flat fee regardless of duration.
 */
class RateCalculator {
    /**
     * Sonderkonditions-Satz (Feature 098); greift nur ohne Entry-Override und
     * VOR dem User-Satz, sonst würde ein Mitarbeiter-Satz die Kondition schlagen.
     */
    private function resolveAgreementRate(TimeEntry $entry): ?CustomerBillingRate {
        if ($entry->hourly_rate !== null) {
            return null;
        }

        return app(AgreementRateResolver::class)->rateFor($entry);
    }

    /**
     * Resolve the effective hourly rate for the given entry.
     */
    private function resolveHourlyRate(TimeEntry $entry, ?CustomerBillingRate $agreementRate = null): ?float {
        if ($entry->hourly_rate !== null) {
            return $entry->hourly_rate->toFloat();
        }

        if ($agreementRate !== null) {
            return (float) $agreementRate->hourly_rate;
        }

        $user = $entry->user;
        if ($user && $user->hourly_rate !== null) {
            return $user->hourly_rate->toFloat();
        }

        $task = $entry->task;
        if ($task && $task->hourly_rate !== null) {
            return $task->hourly_rate->toFloat();
        }

        $project = $entry->project;
        if ($project && $project->hourly_rate !== null) {
            return $project->hourly_rate->toFloat();
        }

        $customer = $project?->customer;
        if ($customer && $customer->hourly_rate !== null) {
            return $customer->hourly_rate->toFloat();
        }

        return null;
    }

    /**
     * Resolve the effective internal (cost) rate for the given entry.
     */
    private function resolveInternalRate(TimeEntry $entry): ?float {
        $user = $entry->user;
        if ($user && $user->internal_rate !== null) {
            return $user->internal_rate->toFloat();
        }

        $task = $entry->task;
        if ($task && $task->internal_rate !== null) {
            return $task->internal_rate->toFloat();
        }

        $project = $entry->project;
        if ($project && $project->internal_rate !== null) {
            return $project->internal_rate->toFloat();
        }

        $customer = $project?->customer;
        if ($customer && $customer->internal_rate !== null) {
            return $customer->internal_rate->toFloat();
        }

        return null;
    }

    /**
     * Determine whether the entry should be considered billable, taking the
     * project/customer billable flags into account.
     */
    private function isBillable(TimeEntry $entry): bool {
        if (! $entry->billable) {
            return false;
        }

        $project = $entry->project;
        if ($project && property_exists($project, 'billable') && $project->billable === false) {
            return false;
        }

        $customer = $project?->customer;
        if ($customer && $customer->billable === false) {
            return false;
        }

        return true;
    }

    /**
     * Compute revenue (rate) and internal cost for the entry. Returns array
     * with keys `rate`, `internal_rate`, `hourly_rate` (resolved snapshot) and
     * `agreement_rate_id` (Feature 098: gesetzt, wenn eine Kundenkondition den
     * Satz geliefert hat).
     *
     * @return array{rate: float, internal_rate: float, hourly_rate: float|null, agreement_rate_id: int|null}
     */
    public function compute(TimeEntry $entry): array {
        $hours = ((int) $entry->minutes) / 60.0;
        $agreementRate = $this->resolveAgreementRate($entry);

        if ($entry->fixed_rate !== null) {
            $revenue = $this->isBillable($entry) ? $entry->fixed_rate->toFloat() : 0.0;
            $hourly = $this->resolveHourlyRate($entry, $agreementRate);
        } else {
            $hourly = $this->resolveHourlyRate($entry, $agreementRate);
            $revenue = ($hourly !== null && $this->isBillable($entry)) ? round($hours * $hourly, 2) : 0.0;
        }

        $internalHourly = $this->resolveInternalRate($entry);
        $internal = $internalHourly !== null ? round($hours * $internalHourly, 2) : 0.0;

        return [
            'rate' => $revenue,
            'internal_rate' => $internal,
            'hourly_rate' => $hourly,
            'agreement_rate_id' => $agreementRate?->id,
        ];
    }
}
