<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectorTourService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance;

use App\Enums\Diary\Status;
use App\Models\AssetCompliance\AssetInspectionSchedule;
use App\Models\Diary\{DiaryEntry, Tour};
use App\Models\Platform\User;
use App\Services\AssetCompliance\Contracts\InspectionTourPlanner;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prüfertouren (MVP-918): fällige Prüftermine eines internen Prüfers werden
 * zu Aufträgen am Gerätestandort und als Tour geplant; die Reihenfolge
 * optimiert die Tourenplanung (OSRM, sonst Luftlinie). Der Prüftermin merkt
 * sich seinen Auftrag, damit er nicht zweimal verplant wird.
 */
final class InspectorTourService {
    public function __construct(private readonly InspectionTourPlanner $planner) {}

    /** @return Collection<int, AssetInspectionSchedule> */
    public function dueFor(User $inspector, CarbonImmutable $until): Collection {
        return AssetInspectionSchedule::query()
            ->open()
            ->where('inspector_user_id', $inspector->id)
            ->whereNull('diary_entry_id')
            ->where('due_on', '<', DateRange::dayAfter($until))
            ->with(['asset', 'assignment.profile'])
            ->orderBy('due_on')
            ->get();
    }

    /** @param  list<int>  $scheduleIds */
    public function plan(User $inspector, CarbonImmutable $date, CarbonImmutable $until, array $scheduleIds): Tour {
        $selected = $this->dueFor($inspector, $until)->whereIn('id', $scheduleIds)->pluck('id')->all();
        if ($selected === []) {
            throw new RuntimeException((string) __('inspection_tour.none_selected'));
        }

        return DB::transaction(function () use ($inspector, $date, $selected): Tour {
            // Unter Zeilensperre neu lesen: zwei gleichzeitige Planungen legten sonst je Prüftermin
            // zwei Aufträge an (Konsolidierungs-Audit 2026-10, k3-4).
            $schedules = AssetInspectionSchedule::query()
                ->whereIn('id', $selected)
                ->whereNull('diary_entry_id')
                ->with(['asset', 'assignment.profile'])
                ->orderBy('due_on')
                ->lockForUpdate()
                ->get();
            if ($schedules->isEmpty()) {
                throw new RuntimeException((string) __('inspection_tour.none_selected'));
            }

            $orderIds = [];
            foreach ($schedules as $schedule) {
                $asset = $schedule->asset;
                $entry = new DiaryEntry;
                $entry->organization_id = $schedule->organization_id;
                $entry->user_id = (int) $inspector->id;
                $entry->assigned_user_id = (int) $inspector->id;
                $entry->customer_id = $asset?->customer_id;
                $entry->asset_id = $schedule->asset_id;
                $entry->title = (string) __('inspection_tour.entry_title', ['profile' => $schedule->assignment->profile->name ?? '—', 'asset' => $asset->name ?? '—']);
                $entry->content = (string) __('inspection_tour.entry_content', ['due' => $schedule->due_on->format('d.m.Y')]);
                $entry->status = Status::Open;
                $entry->scheduled_for = Carbon::instance($date->startOfDay());
                $entry->address_line = $asset?->location_text;
                $entry->address_lat = $asset?->location_lat;
                $entry->address_lng = $asset?->location_lng;
                $entry->save();

                $schedule->forceFill(['diary_entry_id' => $entry->id, 'planned_on' => $date->toDateString()])->save();
                $orderIds[] = (int) $entry->id;
            }

            return $this->planner->planTour($inspector, $date, $orderIds);
        });
    }
}
