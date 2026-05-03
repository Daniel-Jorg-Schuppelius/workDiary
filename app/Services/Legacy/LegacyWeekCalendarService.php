<?php

namespace App\Services\Legacy;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class LegacyWeekCalendarService {
    /**
     * @return array{monday: Carbon, sunday: Carbon, weekOffset: int, selectedWeek: string}
     */
    public function resolveWindow(int $weekOffset, string $weekDate): array {
        $baseMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $monday = $baseMonday->copy()->addWeeks($weekOffset);

        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekDate, $matches) === 1) {
            $isoYear = (int) $matches[1];
            $isoWeek = (int) $matches[2];
            $monday = Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
            $weekOffset = $baseMonday->diffInWeeks($monday, false);
        }

        return [
            'monday' => $monday,
            'sunday' => $monday->copy()->addDays(6)->endOfDay(),
            'weekOffset' => $weekOffset,
            'selectedWeek' => $monday->format('o-\\WW'),
        ];
    }

    /**
     * @param iterable<object> $entries
     * @param iterable<object> $oncalls
     * @param iterable<object> $notdiensts
     * @return array{entriesByUserDay: array<int, array<string, array<int, object>>>, oncallByUserDay: array<int, array<string, bool>>, notdienstByUserDay: array<int, array<string, bool>>}
     */
    public function buildWeekMaps(iterable $entries, iterable $oncalls, iterable $notdiensts): array {
        $entriesByUserDay = [];
        foreach ($entries as $entry) {
            $this->fillEntriesByDay($entriesByUserDay, $entry);
        }

        $oncallByUserDay = [];
        foreach ($oncalls as $oncall) {
            $this->fillFlagsByDay($oncallByUserDay, $oncall);
        }

        $notdienstByUserDay = [];
        foreach ($notdiensts as $notdienst) {
            $this->fillFlagsByDay($notdienstByUserDay, $notdienst);
        }

        return [
            'entriesByUserDay' => $entriesByUserDay,
            'oncallByUserDay' => $oncallByUserDay,
            'notdienstByUserDay' => $notdienstByUserDay,
        ];
    }

    /**
     * @param array<int, array<string, array<int, object>>> $bucket
     */
    private function fillEntriesByDay(array &$bucket, object $entry): void {
        $start = $this->normalizeDate(data_get($entry, 'von'));
        $end = $this->normalizeDate(data_get($entry, 'bis'));
        if (! $start || ! $end) {
            return;
        }

        $userId = (int) data_get($entry, 'user');
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $bucket[$userId][$cursor->format('Y-m-d')][] = $entry;
            $cursor->addDay();
        }
    }

    /**
     * @param array<int, array<string, bool>> $bucket
     */
    private function fillFlagsByDay(array &$bucket, object $item): void {
        $start = $this->normalizeDate(data_get($item, 'von'));
        $end = $this->normalizeDate(data_get($item, 'bis'));
        if (! $start || ! $end) {
            return;
        }

        $userId = (int) data_get($item, 'user');
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $bucket[$userId][$cursor->format('Y-m-d')] = true;
            $cursor->addDay();
        }
    }

    private function normalizeDate(mixed $value): ?Carbon {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
