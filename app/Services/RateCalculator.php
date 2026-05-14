<?php

namespace App\Services;

use App\Models\TimeEntry;

/**
 * Calculates billable revenue and internal cost for a TimeEntry following the
 * Kimai rate hierarchy: TimeEntry override -> User -> Activity (Task) -> Project -> Customer.
 *
 * A non-null fixed_rate on the entry overrides hourly calculation and yields a
 * flat fee regardless of duration.
 */
class RateCalculator {
    /**
     * Resolve the effective hourly rate for the given entry.
     */
    public function resolveHourlyRate(TimeEntry $entry): ?float {
        if ($entry->hourly_rate !== null) {
            return (float) $entry->hourly_rate;
        }

        $user = $entry->user;
        if ($user && $user->hourly_rate !== null) {
            return (float) $user->hourly_rate;
        }

        $task = $entry->task;
        if ($task && $task->hourly_rate !== null) {
            return (float) $task->hourly_rate;
        }

        $project = $entry->project;
        if ($project && $project->hourly_rate !== null) {
            return (float) $project->hourly_rate;
        }

        $customer = $project?->customer;
        if ($customer && $customer->hourly_rate !== null) {
            return (float) $customer->hourly_rate;
        }

        return null;
    }

    /**
     * Resolve the effective internal (cost) rate for the given entry.
     */
    public function resolveInternalRate(TimeEntry $entry): ?float {
        $user = $entry->user;
        if ($user && $user->internal_rate !== null) {
            return (float) $user->internal_rate;
        }

        $task = $entry->task;
        if ($task && $task->internal_rate !== null) {
            return (float) $task->internal_rate;
        }

        $project = $entry->project;
        if ($project && $project->internal_rate !== null) {
            return (float) $project->internal_rate;
        }

        $customer = $project?->customer;
        if ($customer && $customer->internal_rate !== null) {
            return (float) $customer->internal_rate;
        }

        return null;
    }

    /**
     * Determine whether the entry should be considered billable, taking the
     * project/customer billable flags into account.
     */
    public function isBillable(TimeEntry $entry): bool {
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
     * with keys `rate`, `internal_rate`, `hourly_rate` (resolved snapshot).
     *
     * @return array{rate: float, internal_rate: float, hourly_rate: float|null}
     */
    public function compute(TimeEntry $entry): array {
        $hours = ((int) $entry->minutes) / 60.0;

        if ($entry->fixed_rate !== null) {
            $revenue = $this->isBillable($entry) ? (float) $entry->fixed_rate : 0.0;
            $hourly = $this->resolveHourlyRate($entry);
        } else {
            $hourly = $this->resolveHourlyRate($entry);
            $revenue = ($hourly !== null && $this->isBillable($entry)) ? round($hours * $hourly, 2) : 0.0;
        }

        $internalHourly = $this->resolveInternalRate($entry);
        $internal = $internalHourly !== null ? round($hours * $internalHourly, 2) : 0.0;

        return [
            'rate' => $revenue,
            'internal_rate' => $internal,
            'hourly_rate' => $hourly,
        ];
    }
}
