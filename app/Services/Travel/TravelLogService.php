<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Travel;

use App\Models\TimeEntry;
use App\Models\TravelLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates persistence of {@see TravelLog} entries and, when configured,
 * synchronises a paired {@see TimeEntry} with `kind=travel` so the travel time
 * is visible on the daily dashboard and in reports.
 */
class TravelLogService
{
    public function __construct(private readonly MileageRateResolver $rates) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TravelLog
    {
        return DB::transaction(function () use ($attributes): TravelLog {
            $attributes = $this->applyDefaults($attributes);
            $log = TravelLog::create($attributes);
            $this->syncTimeEntry($log);

            return $log->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TravelLog $log, array $attributes): TravelLog
    {
        return DB::transaction(function () use ($log, $attributes): TravelLog {
            $attributes = $this->applyDefaults($attributes, $log);
            $log->fill($attributes);
            $log->save();
            $this->syncTimeEntry($log);

            return $log->refresh();
        });
    }

    public function delete(TravelLog $log): void
    {
        DB::transaction(function () use ($log): void {
            TimeEntry::query()->where('travel_log_id', $log->id)->delete();
            $log->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyDefaults(array $attributes, ?TravelLog $existing = null): array
    {
        $vehicle = (string) ($attributes['vehicle'] ?? ($existing !== null ? $existing->vehicle : TravelLog::VEHICLE_PRIVATE));

        if (! array_key_exists('rate_per_km', $attributes) || $attributes['rate_per_km'] === null || $attributes['rate_per_km'] === '') {
            $vehicleId = $attributes['vehicle_id'] ?? $existing?->vehicle_id;
            $vehicleEntity = $vehicleId !== null ? Vehicle::query()->find((int) $vehicleId) : null;
            if ($vehicleEntity instanceof Vehicle && $vehicleEntity->default_rate_per_km !== null) {
                $attributes['rate_per_km'] = (string) $vehicleEntity->default_rate_per_km;
            } else {
                $attributes['rate_per_km'] = $this->rates->rateFor($vehicle, $attributes['organization_id'] ?? $existing?->organization_id);
            }
        }

        return $attributes;
    }

    private function syncTimeEntry(TravelLog $log): void
    {
        if (! config('timesheet.travel.auto_create_time_entry', true)) {
            return;
        }
        if (! $log->started_at || ! $log->ended_at || $log->duration_minutes <= 0) {
            // Without start/end timestamps we cannot place the entry on a timeline.
            TimeEntry::query()->where('travel_log_id', $log->id)->delete();

            return;
        }

        $payload = [
            'organization_id' => $log->organization_id,
            'user_id' => $log->user_id,
            'project_id' => $log->project_id,
            'task_id' => $log->task_id,
            'customer_id' => $log->customer_id,
            'attendance_id' => $log->attendance_id,
            'travel_log_id' => $log->id,
            'date' => $log->date,
            'started_at' => $log->started_at,
            'ended_at' => $log->ended_at,
            'minutes' => $log->duration_minutes,
            'kind' => TimeEntry::KIND_TRAVEL,
            'activity_type' => TimeEntry::ACTIVITY_TRAVEL,
            'description' => $log->purpose,
            'billable' => false,
        ];

        $existing = TimeEntry::query()->where('travel_log_id', $log->id)->first();
        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        TimeEntry::create($payload);
    }
}
