<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CertificateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Enums\Event\ParticipantStatus;
use App\Models\{Event, EventParticipant, User};
use App\Notifications\Event\CertificateExpiryNotification;
use Illuminate\Support\{Carbon, Collection};

/**
 * Verwaltet Pflichtnachweise (Zertifikate) je Teilnehmer + Event.
 * PDF-Generierung ist optional und kann später als Attachment ergänzt werden.
 */
class CertificateService {
    /**
     * Stellt ein Zertifikat aus. Setzt automatisch das Ablaufdatum aus
     * Event > Kategorie > Default-Config.
     */
    public function issue(Event $event, User $user, ?Carbon $issuedAt = null): EventParticipant {
        $pivot = EventParticipant::query()
            ->where('event_id', $event->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $issued = ($issuedAt ?? now())->copy()->startOfDay();

        $validMonths = $event->certificate_valid_months
            ?? $event->category->certificate_valid_months
            ?? (int) config('events.certificate.default_valid_months', 12);

        $pivot->forceFill([
            'status' => ParticipantStatus::Attended,
            'attended_at' => $pivot->attended_at ?? $event->started_at,
            'certificate_issued_at' => $issued,
            'certificate_expires_at' => $validMonths > 0 ? $issued->copy()->addMonths($validMonths) : null,
        ])->save();

        return $pivot;
    }

    /**
     * Liefert Teilnehmer-Pivots, deren Zertifikat bald abläuft.
     *
     * @return Collection<int, EventParticipant>
     */
    public function expiringSoon(?Carbon $on = null): Collection {
        $on ??= now();
        /** @var array<int, int> $warnDays */
        $warnDays = (array) config('events.certificate.expiry_warning_days', [60, 30, 7]);
        if ($warnDays === []) {
            return collect();
        }

        $maxDays = max($warnDays);
        $threshold = $on->copy()->addDays($maxDays);

        return EventParticipant::query()
            ->whereNotNull('certificate_expires_at')
            ->where('certificate_expires_at', '>=', $on->toDateString())
            ->where('certificate_expires_at', '<=', $threshold->toDateString())
            ->get();
    }

    /**
     * Versendet Ablauf-Warnungen für alle Teilnehmer mit Zertifikat,
     * dessen Restlaufzeit einem konfigurierten Warn-Schwellwert
     * entspricht (auf den Tag genau, um Duplikate zu vermeiden).
     *
     * @return int Anzahl versendeter Benachrichtigungen.
     */
    public function notifyExpiring(?Carbon $on = null): int {
        $on ??= now()->startOfDay();
        /** @var array<int, int> $warnDays */
        $warnDays = (array) config('events.certificate.expiry_warning_days', [60, 30, 7]);
        $sent = 0;

        foreach ($warnDays as $days) {
            $targetDate = $on->copy()->addDays($days)->toDateString();

            $pivots = EventParticipant::query()
                ->with(['event'])
                ->whereDate('certificate_expires_at', $targetDate)
                ->get();

            foreach ($pivots as $pivot) {
                $user = User::find($pivot->user_id);
                if (! $user) {
                    continue;
                }
                $user->notify(new CertificateExpiryNotification(
                    eventId: (int) $pivot->event_id,
                    eventTitle: (string) ($pivot->event->title ?? ''),
                    expiresAt: Carbon::parse($pivot->certificate_expires_at),
                    daysRemaining: $days,
                ));
                $sent++;
            }
        }

        return $sent;
    }
}
