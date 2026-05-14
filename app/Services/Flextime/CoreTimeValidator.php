<?php

namespace App\Services\Flextime;

use App\Models\TimeEntry;
use App\Models\User;

class CoreTimeValidator
{
    public function __construct(protected WorkScheduleResolver $resolver) {}

    /**
     * Liefert eine Liste mit Verstoß-Beschreibungen (i18n-Key/-String). Leeres Array = ok.
     *
     * @return array<int, string>
     */
    public function violations(User $user, TimeEntry $entry): array
    {
        if (! $entry->started_at || ! $entry->ended_at) {
            return [];
        }

        $schedule = $this->resolver->for($user, $entry->started_at);
        $issues = [];

        if ($schedule->frame_start && $entry->started_at->format('H:i:s') < $schedule->frame_start) {
            $issues[] = __('Beginn vor Rahmenzeit (:t).', ['t' => substr($schedule->frame_start, 0, 5)]);
        }
        if ($schedule->frame_end && $entry->ended_at->format('H:i:s') > $schedule->frame_end) {
            $issues[] = __('Ende nach Rahmenzeit (:t).', ['t' => substr($schedule->frame_end, 0, 5)]);
        }

        // Kernzeit: an Arbeitstagen muss in [core_start, core_end] gearbeitet werden
        if ($schedule->core_start && $schedule->core_end && $schedule->appliesOnWeekday($entry->started_at->dayOfWeekIso)) {
            $coversCore = ($entry->started_at->format('H:i:s') <= $schedule->core_start)
                && ($entry->ended_at->format('H:i:s') >= $schedule->core_end);
            if (! $coversCore) {
                $issues[] = __('Kernarbeitszeit (:a–:b) nicht abgedeckt.', [
                    'a' => substr($schedule->core_start, 0, 5),
                    'b' => substr($schedule->core_end, 0, 5),
                ]);
            }
        }

        if ((int) $entry->minutes >= $schedule->break_after_minutes && (int) $entry->break_minutes < $schedule->break_minutes) {
            $issues[] = __('Pflichtpause :m min nicht erreicht.', ['m' => $schedule->break_minutes]);
        }

        return $issues;
    }
}
