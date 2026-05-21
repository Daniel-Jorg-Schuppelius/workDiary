<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Models\Event;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;

/**
 * Expandiert RFC-5545-RRULEs des Master-Events und materialisiert daraus
 * konkrete Event-Datensätze (kopiert Felder, setzt series_id=Master).
 */
class RecurrenceService {
    public function __construct(
        private readonly ArrayTransformer $transformer = new ArrayTransformer(),
    ) {
    }

    /**
     * @return list<DateTimeImmutable>
     */
    public function expand(Event $master, ?Carbon $until = null): array {
        if (empty($master->recurrence_rule)) {
            return [];
        }

        $startDate = new DateTimeImmutable($master->started_at->format(DATE_ATOM));
        $rule = new Rule($master->recurrence_rule, $startDate, null, $master->timezone ?? 'Europe/Berlin');

        $upperBound = $until?->toDateTimeImmutable()
            ?? (new DateTimeImmutable())->modify('+' . (int) config('events.materialization_days', 90) . ' days');

        $constraint = new BetweenConstraint($startDate, $upperBound, true);
        $recurrences = $this->transformer->transform($rule, $constraint);

        $dates = [];
        foreach ($recurrences as $recurrence) {
            $dates[] = DateTimeImmutable::createFromInterface($recurrence->getStart());
        }

        return $dates;
    }

    /**
     * Stellt sicher, dass für jedes RRULE-Vorkommen im Materialization-Fenster
     * ein Event existiert. Idempotent über (series_id, started_at).
     *
     * @return int  Anzahl neu erzeugter Events.
     */
    public function materialize(Event $master, ?Carbon $until = null): int {
        $occurrences = $this->expand($master, $until);
        if ($occurrences === []) {
            return 0;
        }

        $duration = $master->started_at->diffInSeconds($master->ended_at);
        $created = 0;

        $existing = Event::query()
            ->where('series_id', $master->getKey())
            ->pluck('started_at')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d H:i:s'))
            ->all();

        foreach ($occurrences as $occurrence) {
            $start = Carbon::instance($occurrence);
            // Master selbst auslassen
            if ($start->equalTo($master->started_at)) {
                continue;
            }
            if (in_array($start->format('Y-m-d H:i:s'), $existing, true)) {
                continue;
            }

            $end = $start->copy()->addSeconds($duration);
            Event::create([
                'organization_id' => $master->organization_id,
                'title' => $master->title,
                'description' => $master->description,
                'topic' => $master->topic,
                'event_type' => $master->event_type,
                'category_id' => $master->category_id,
                'started_at' => $start,
                'ended_at' => $end,
                'is_all_day' => $master->is_all_day,
                'timezone' => $master->timezone,
                'status' => $master->status,
                'visibility' => $master->visibility,
                'responsible_user_id' => $master->responsible_user_id,
                'customer_id' => $master->customer_id,
                'external_contact_note' => $master->external_contact_note,
                'max_participants' => $master->max_participants,
                'is_mandatory' => $master->is_mandatory,
                'certificate_valid_months' => $master->certificate_valid_months,
                'series_id' => $master->getKey(),
                'recurrence_rule' => null, // nur Master trägt die Regel
                'reminder_overrides' => $master->reminder_overrides,
            ]);
            $created++;
        }

        return $created;
    }
}
