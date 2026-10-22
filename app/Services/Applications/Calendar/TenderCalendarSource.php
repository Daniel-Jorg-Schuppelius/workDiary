<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderCalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications\Calendar;

use App\Enums\Applications\ApplicationOpportunityStatus;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\Platform\User;
use App\Services\Calendar\Contracts\CalendarEventSource;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fristen offener Ausschreibungen im Kalender (MVP-1299): im eigenen Kalender
 * die, für die man verantwortlich ist, in der Teamansicht alle.
 */
class TenderCalendarSource implements CalendarEventSource {
    private const COLOR = '#7c3aed';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function events(CarbonInterface $start, CarbonInterface $end, User $user, bool $teamScope, ?int $filterUserId): array {
        // Nach einer Herabstufung bleiben Akten stehen; ihre Fristen gehören dann nicht in den Kalender.
        if (! $this->features->isEnabled('module.applications') || ! $user->can('viewAny', ApplicationOpportunity::class)) {
            return [];
        }

        $deadlines = [
            'question_deadline' => __('Rückfragefrist'),
            'participation_deadline' => __('Teilnahmefrist'),
            'submission_deadline' => __('Abgabefrist'),
            'opening_at' => __('Eröffnungstermin'),
            'binding_until' => __('Bindefrist'),
        ];
        $range = DateRange::days($start, $end);

        $tenders = ApplicationOpportunity::query()
            ->whereIn('status', ApplicationOpportunityStatus::open())
            ->when(! $teamScope, fn (Builder $q) => $q->where('responsible_user_id', $user->id))
            ->when($teamScope && $filterUserId !== null, fn (Builder $q) => $q->where('responsible_user_id', $filterUserId))
            ->where(function (Builder $q) use ($deadlines, $range): void {
                foreach (array_keys($deadlines) as $column) {
                    $q->orWhereBetween($column, $range);
                }
            })
            ->get();

        $events = [];
        foreach ($tenders as $tender) {
            foreach ($deadlines as $column => $label) {
                $date = $tender->getAttribute($column);
                if (! $date instanceof CarbonInterface || $date->lt($start->copy()->startOfDay()) || $date->gt($end->copy()->endOfDay())) {
                    continue;
                }
                $events[] = [
                    'id' => 'tender-' . $tender->sqid . '-' . $column,
                    'title' => $label . ' · ' . $tender->title,
                    // Nur der Eröffnungstermin hat eine Uhrzeit; die Fristen sind ganztägig.
                    'start' => $column === 'opening_at' ? $date->toIso8601String() : $date->toDateString(),
                    'end' => null,
                    'color' => self::COLOR,
                    'url' => route('tenders.show', $tender),
                ];
            }
        }

        return $events;
    }
}
