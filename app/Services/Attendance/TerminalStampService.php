<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalStampService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Models\{AttendanceTerminal, ExternalReference, User, UserBadge};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Verarbeitet ein Stempelereignis eines Hardware-Terminals (Feature 061,
 * MVP-130). Badge-Kennung → Nutzer (gehasht), dann Anlegen eines
 * Anwesenheitsstempels über den **bestehenden** {@see AttendanceClockService} —
 * dadurch sind Terminal-Stempel identisch zu Browser-Stempeln und erscheinen
 * automatisch in allen Auswertungen (Quelle `terminal`).
 *
 * - **Kommen/Gehen** über den bestehenden Toggle (`current()`); explizites
 *   `in`/`out` wird respektiert. Doppel-Kommen / Gehen-ohne-Kommen fängt der
 *   ClockService ab → als Status zurückgemeldet (Plausibilität).
 * - **Offline-Nachlieferung** mit Originalzeit (`occurred_at`).
 * - **Dedup** über die Ereignis-ID ({@see ExternalReference}, Plugin `terminal`,
 *   Typ `stamp`) — ein erneut zugestelltes Ereignis erzeugt keinen zweiten Stempel.
 */
class TerminalStampService {
    public const PLUGIN_ID = 'terminal';

    public const EXTERNAL_TYPE = 'stamp';

    public function __construct(private readonly AttendanceClockService $clock) {}

    /**
     * @param  string  $eventType  fachlicher Ereignistyp: `work` (Kommen/Gehen, Default),
     *                             `break` (Pausen-Toggle) oder `homeoffice`/`errand`
     *                             (Zwischen-Status, MVP-532) — orthogonal zu $event.
     * @param  int|null  $queued  vom Terminal gemeldeter Offline-Pufferstand (MVP-516).
     * @return array{status: 'clocked_in'|'clocked_out'|'break_started'|'break_ended'|'homeoffice_started'|'homeoffice_ended'|'errand_started'|'errand_ended'|'skipped'|'unknown_badge'|'noop'|'rejected', user: ?User}
     */
    public function stamp(AttendanceTerminal $terminal, string $badgeUid, string $event = 'toggle', ?string $occurredAt = null, ?string $eventId = null, string $eventType = 'work', ?int $queued = null): array {
        // Gesundheitsstatus (+ optional Pufferstand) fortschreiben — auch bei
        // abgewiesenen Ereignissen.
        $health = ['last_seen_at' => Carbon::now()];
        if ($queued !== null) {
            $health['last_buffer_size'] = max(0, $queued);
        }
        $terminal->forceFill($health)->save();

        if ($eventId !== null && $eventId !== '' && $this->alreadySeen($terminal, $eventId)) {
            return ['status' => 'skipped', 'user' => null];
        }

        $user = $this->resolveUser((int) $terminal->organization_id, $badgeUid);
        if (! $user instanceof User) {
            // Security-Signal (Feature 096, MVP-443): unbekannte Badges nur
            // zählen — die Badge-UID selbst bleibt aus dem Log (Hash-Prinzip).
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::TerminalBadgeUnknown,
                ['terminal' => $terminal->name, 'organization_id' => (int) $terminal->organization_id],
            );

            return ['status' => 'unknown_badge', 'user' => null];
        }

        $normalizedType = strtolower(trim($eventType));
        $isBreak = $normalizedType === 'break';
        // MVP-532: Zwischen-Status Homeoffice/Dienstgang als Toggle analog Pause.
        $intermediate = in_array($normalizedType, ['homeoffice', 'errand'], true) ? $normalizedType : null;
        $context = ['device' => $terminal->name, 'source' => AttendanceSource::Terminal->value];

        try {
            if ($intermediate !== null) {
                if ($occurredAt !== null && $occurredAt !== '') {
                    $context['occurred_at'] = $occurredAt;
                }
                $attendance = $this->clock->toggleIntermediate($user, $intermediate, $context);
                if ($attendance === null) {
                    return ['status' => 'noop', 'user' => $user]; // Zwischen-Status ohne offenes Kommen
                }
                $status = $attendance->{$intermediate . '_started_at'} !== null
                    ? $intermediate . '_started'
                    : $intermediate . '_ended';
            } elseif ($isBreak) {
                // Pausen-Toggle statt Kommen/Gehen; die Richtung ($event) ist hier
                // bedeutungslos.
                if ($occurredAt !== null && $occurredAt !== '') {
                    $context['occurred_at'] = $occurredAt;
                }
                $attendance = $this->clock->toggleBreak($user, $context);
                if ($attendance === null) {
                    return ['status' => 'noop', 'user' => $user]; // Pause ohne offenes Kommen
                }
                $status = $attendance->break_started_at !== null ? 'break_started' : 'break_ended';
            } else {
                $action = $this->resolveAction($user, $event);
                if ($action === 'in') {
                    if ($occurredAt !== null && $occurredAt !== '') {
                        $context['started_at'] = $occurredAt;
                    }
                    $attendance = $this->clock->clockIn($user, $context);
                    $status = 'clocked_in';
                } else {
                    if ($occurredAt !== null && $occurredAt !== '') {
                        $context['ended_at'] = $occurredAt;
                    }
                    $attendance = $this->clock->clockOut($user, $context);
                    if ($attendance === null) {
                        return ['status' => 'noop', 'user' => $user]; // Gehen ohne offenes Kommen
                    }
                    $status = 'clocked_out';
                }
            }
        } catch (Throwable) {
            return ['status' => 'rejected', 'user' => $user]; // Doppel-Kommen, ungültige Zeit u. Ä.
        }

        if ($eventId !== null && $eventId !== '') {
            ExternalReference::query()->withoutGlobalScopes()->create([
                'organization_id' => $terminal->organization_id,
                'plugin_id' => self::PLUGIN_ID,
                'external_type' => self::EXTERNAL_TYPE,
                'referenceable_type' => $attendance->getMorphClass(),
                'referenceable_id' => $attendance->getKey(),
                'external_id' => $eventId,
                'payload' => ['event' => $event, 'event_type' => $intermediate ?? ($isBreak ? 'break' : 'work'), 'terminal_id' => $terminal->id],
                'synced_at' => Carbon::now(),
            ]);
        }

        return ['status' => $status, 'user' => $user];
    }

    private function alreadySeen(AttendanceTerminal $terminal, string $eventId): bool {
        return ExternalReference::query()
            ->forPlugin($terminal->organization_id, self::PLUGIN_ID, self::EXTERNAL_TYPE)
            ->forExternalId($eventId)
            ->exists();
    }

    /** @return 'in'|'out' */
    private function resolveAction(User $user, string $event): string {
        $normalized = strtolower(trim($event));
        if (in_array($normalized, ['in', 'checkin', 'kommen'], true)) {
            return 'in';
        }
        if (in_array($normalized, ['out', 'checkout', 'gehen'], true)) {
            return 'out';
        }

        // Toggle: offener Stempel → gehen, sonst kommen.
        return $this->clock->current($user) !== null ? 'out' : 'in';
    }

    private function resolveUser(int $organizationId, string $badgeUid): ?User {
        if (trim($badgeUid) === '') {
            return null;
        }

        $today = Carbon::today();
        $badge = UserBadge::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('badge_hash', UserBadge::hashBadge($badgeUid))
            ->whereNull('revoked_at')
            // MVP-516: Gültigkeitszeitraum — außerhalb gilt der Badge als unbekannt.
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->first();
        if (! $badge instanceof UserBadge) {
            return null;
        }

        return User::query()->withoutGlobalScopes()
            ->whereKey($badge->user_id)
            ->where('organization_id', $organizationId)
            ->first();
    }
}
