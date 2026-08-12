<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WishMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Shift\AvailabilityKind;
use App\Models\{AvailabilityWindow, DesiredShift, ScheduledShift};
use Illuminate\Support\Collection;

/**
 * MVP-515 (Feature 103): gleicht zugewiesene Schichten mit Wunschdiensten,
 * Freiwünschen und Verfügbarkeiten ab, damit die Planung „Wunsch erfüllt" /
 * „Konflikt" direkt am Schicht-Badge sieht.
 *
 * Nur Anzeige-Assistenz: Konflikte blockieren nichts — harte Regeln bleiben
 * beim ShiftComplianceService.
 */
class WishMatcher {
    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return array<int, array{state: 'fulfilled'|'conflict', label: string}> shift_id → Marker
     */
    public function forShifts(Collection $shifts): array {
        if ($shifts->isEmpty()) {
            return [];
        }

        /** @var list<int> $userIds */
        $userIds = $shifts->pluck('user_id')->unique()->values()->all();
        $dates = $shifts->map(fn (ScheduledShift $s): string => $s->date->toDateString());

        /** @var array<string, list<DesiredShift>> $wishesByUserDate */
        $wishesByUserDate = [];
        $wishRows = DesiredShift::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$dates->min(), $dates->max()])
            ->get();
        foreach ($wishRows as $wish) {
            $wishesByUserDate[$wish->user_id . '|' . $wish->date->toDateString()][] = $wish;
        }

        /** @var array<int, list<AvailabilityWindow>> $windowsByUser */
        $windowsByUser = [];
        $windowRows = AvailabilityWindow::query()
            ->whereIn('user_id', $userIds)
            ->get();
        foreach ($windowRows as $window) {
            $windowsByUser[(int) $window->user_id][] = $window;
        }

        $out = [];
        foreach ($shifts as $shift) {
            $marker = $this->matchShift($shift, $wishesByUserDate, $windowsByUser);
            if ($marker !== null) {
                $out[(int) $shift->id] = $marker;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<DesiredShift>>  $wishesByUserDate
     * @param  array<int, list<AvailabilityWindow>>  $windowsByUser
     * @return array{state: 'fulfilled'|'conflict', label: string}|null
     */
    private function matchShift(ScheduledShift $shift, array $wishesByUserDate, array $windowsByUser): ?array {
        $wishes = $wishesByUserDate[$shift->user_id . '|' . $shift->date->toDateString()] ?? [];

        // Konflikt hat Vorrang: Ausschlusswunsch (avoid/off) auf den Tag bzw.
        // den konkreten Schichttyp — oder ein Nicht-verfügbar-Fenster.
        foreach ($wishes as $wish) {
            $typeMatches = $wish->shift_type_id === null || $wish->shift_type_id === $shift->shift_type_id;
            if ($typeMatches && $wish->preference->isExclusion()) {
                return [
                    'state' => 'conflict',
                    'label' => $wish->preference->label() . $this->prioritySuffix($wish->priority),
                ];
            }
        }

        $preferredPriority = null;
        $hasPreferredWindow = false;
        foreach ($windowsByUser[(int) $shift->user_id] ?? [] as $window) {
            if (! $window->appliesToDate($shift->date)) {
                continue;
            }
            if ($window->kind === AvailabilityKind::Unavailable) {
                return [
                    'state' => 'conflict',
                    'label' => $window->kind->label() . $this->prioritySuffix($window->priority),
                ];
            }
            if ($window->kind === AvailabilityKind::Preferred) {
                $hasPreferredWindow = true;
                $priority = $window->priority;
                if ($priority !== null && ($preferredPriority === null || $priority < $preferredPriority)) {
                    $preferredPriority = $priority;
                }
            }
        }

        foreach ($wishes as $wish) {
            $typeMatches = $wish->shift_type_id === null || $wish->shift_type_id === $shift->shift_type_id;
            if ($typeMatches && ! $wish->preference->isExclusion()) {
                return [
                    'state' => 'fulfilled',
                    'label' => (string) __('schedule.wish.fulfilled') . $this->prioritySuffix($wish->priority),
                ];
            }
        }

        if ($hasPreferredWindow) {
            return [
                'state' => 'fulfilled',
                'label' => AvailabilityKind::Preferred->label() . $this->prioritySuffix($preferredPriority),
            ];
        }

        return null;
    }

    private function prioritySuffix(?int $priority): string {
        return $priority === null ? '' : ' (' . __('schedule.wish.priority_short') . ' ' . $priority . ')';
    }
}
