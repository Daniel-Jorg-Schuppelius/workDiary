<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveWorkScheduleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\WorkSchedule\ScheduleType;
use Illuminate\Validation\Rule;

class SaveWorkScheduleRequest extends BaseFormRequest {
    /**
     * Normalisiert die Eingaben je nach Arbeitszeit-Typ. Insbesondere werden
     * die Pro-Wochentag-Vorgaben (`day_targets`) serverseitig in Minuten
     * umgerechnet — dem Client-Wert wird nicht vertraut.
     */
    protected function prepareForValidation(): void {
        $type = ScheduleType::tryFrom((string) $this->input('schedule_type')) ?? ScheduleType::Flextime;

        if ($type === ScheduleType::PerWeekday) {
            $map = $this->buildDayTargets((array) $this->input('day_targets', []));
            $this->merge([
                'schedule_type' => $type->value,
                'day_targets' => $map,
                'working_days' => array_map('intval', array_keys($map)),
                // Wochensoll = Summe der Tagessollwerte (für Anzeige/Reports).
                'weekly_minutes' => array_sum(array_map(static fn(array $d): int => (int) $d['minutes'], $map)),
            ]);

            return;
        }

        // Andere Typen führen keine Pro-Wochentag-Vorgaben.
        $this->merge(['schedule_type' => $type->value, 'day_targets' => null]);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $type = ScheduleType::tryFrom((string) $this->input('schedule_type')) ?? ScheduleType::Flextime;

        $rules = [
            'schedule_type' => ['required', Rule::enum(ScheduleType::class)],
            'working_days' => ['array'],
            'working_days.*' => ['integer', 'between:1,7'],
            'core_start' => ['nullable', 'date_format:H:i'],
            'core_end' => ['nullable', 'date_format:H:i', 'after:core_start'],
            'frame_start' => ['nullable', 'date_format:H:i'],
            'frame_end' => ['nullable', 'date_format:H:i', 'after:frame_start'],
            'break_after_minutes' => ['required', 'integer', 'min:60', 'max:720'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
            'day_targets' => ['nullable', 'array'],
            'day_targets.*.mode' => ['required_with:day_targets', 'in:hours,times'],
            'day_targets.*.minutes' => ['required_with:day_targets', 'integer', 'min:1', 'max:1440'],
            'day_targets.*.start' => ['nullable', 'date_format:H:i'],
            'day_targets.*.end' => ['nullable', 'date_format:H:i'],
            'day_targets.*.break' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];

        // array_merge (nicht +): rechte Seite überschreibt gleichnamige
        // Basis-Regeln (working_days/day_targets), sonst bliebe die Basisregel
        // bestehen und required würde nie greifen.
        return match ($type) {
            ScheduleType::Flextime => array_merge($rules, [
                'weekly_minutes' => ['required', 'integer', 'min:60', 'max:6000'],
                'daily_target_minutes' => ['required', 'integer', 'min:30', 'max:720'],
                'working_days' => ['required', 'array', 'min:1'],
            ]),
            ScheduleType::Weekly => array_merge($rules, [
                'weekly_minutes' => ['required', 'integer', 'min:60', 'max:6000'],
                'working_days' => ['required', 'array', 'min:1'],
            ]),
            ScheduleType::PerWeekday => array_merge($rules, [
                'day_targets' => ['required', 'array', 'min:1'],
                'weekly_minutes' => ['nullable', 'integer', 'min:0', 'max:6000'],
            ]),
            ScheduleType::Trust => $rules,
        };
    }

    /**
     * Baut aus den rohen Formulareingaben die normierte day_targets-Map:
     * nur aktive Tage, Minuten serverseitig berechnet.
     *
     * @param  array<int|string, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function buildDayTargets(array $raw): array {
        $out = [];
        foreach ($raw as $iso => $cfg) {
            $iso = (int) $iso;
            if ($iso < 1 || $iso > 7 || ! is_array($cfg)) {
                continue;
            }
            // Nur aktivierte Tage berücksichtigen.
            if (! filter_var($cfg['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $mode = ($cfg['mode'] ?? 'hours') === 'times' ? 'times' : 'hours';
            $minutes = 0;
            $entry = ['mode' => $mode];

            if ($mode === 'times') {
                $start = (string) ($cfg['start'] ?? '');
                $end = (string) ($cfg['end'] ?? '');
                $break = (int) ($cfg['break'] ?? 0);
                $minutes = max(0, $this->minutesBetween($start, $end) - $break);
                $entry += ['start' => $start, 'end' => $end, 'break' => $break];
            } else {
                $hours = (float) str_replace(',', '.', (string) ($cfg['hours'] ?? '0'));
                $minutes = (int) round($hours * 60);
            }

            if ($minutes <= 0) {
                continue;
            }
            $entry['minutes'] = $minutes;
            $out[$iso] = $entry;
        }
        ksort($out);

        return $out;
    }

    private function minutesBetween(string $start, string $end): int {
        if (preg_match('/^\d{1,2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{1,2}:\d{2}$/', $end) !== 1) {
            return 0;
        }
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));

        return max(0, ($eh * 60 + $em) - ($sh * 60 + $sm));
    }
}
