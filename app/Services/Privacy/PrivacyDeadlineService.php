<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyDeadlineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\DataSubjectRequestStatus;
use App\Models\Privacy\{DataSubjectRequest, Incident, IncidentEvent};
use Illuminate\Support\Carbon;

/**
 * Fristen-Erinnerungen fuer Betroffenenanfragen (Art. 12: ein Monat). Schreibt
 * idempotent ein `deadline_reminder`-Ereignis, sobald eine offene Anfrage in das
 * Vorlauf-Fenster bzw. die Ueberfaelligkeit laeuft – einmal je Anfrage.
 */
class PrivacyDeadlineService {
    public function __construct(private readonly PrivacyEventService $events) {}

    /** @return int Anzahl neu erinnerter Anfragen */
    public function remind(): int {
        $lead = (int) config('dataprotection.dsr_reminder_lead_days', 7);
        $threshold = Carbon::now()->addDays($lead);

        $openStatuses = array_values(array_filter(
            DataSubjectRequestStatus::cases(),
            static fn (DataSubjectRequestStatus $s): bool => $s->isOpen(),
        ));

        $due = DataSubjectRequest::query()
            ->withoutGlobalScopes()
            ->whereIn('status', array_map(static fn ($s) => $s->value, $openStatuses))
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $threshold)
            ->whereDoesntHave('events', static fn ($q) => $q->where('event', 'deadline_reminder'))
            ->get();

        foreach ($due as $request) {
            $this->events->record($request, 'deadline_reminder', null, [
                'deadline_at' => $request->deadline_at?->toIso8601String(),
                'overdue' => $request->deadline_at?->isPast() ?? false,
            ]);
        }

        return $due->count();
    }

    /**
     * Erinnert an Datenschutzvorfaelle, deren 72-h-Meldefrist verstrichen ist und
     * die noch nicht an die Aufsichtsbehoerde gemeldet wurden. Idempotent (einmal
     * je Vorfall).
     *
     * @return int Anzahl neu erinnerter Vorfaelle
     */
    public function remindIncidents(): int {
        $due = Incident::query()
            ->withoutGlobalScopes()
            ->whereNotNull('authority_deadline_at')
            ->where('authority_deadline_at', '<=', Carbon::now())
            ->whereNull('authority_notified_at')
            ->where('status', '!=', 'closed')
            ->whereDoesntHave('events', static fn ($q) => $q->where('event', 'deadline_reminder'))
            ->get();

        foreach ($due as $incident) {
            IncidentEvent::create([
                'organization_id' => $incident->organization_id,
                'incident_id' => $incident->id,
                'actor_type' => 'system',
                'event' => 'deadline_reminder',
                'metadata' => ['authority_deadline_at' => $incident->authority_deadline_at?->toIso8601String()],
            ]);
        }

        return $due->count();
    }
}
