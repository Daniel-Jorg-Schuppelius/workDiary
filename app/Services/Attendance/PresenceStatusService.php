<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\Attendance\PresenceStatusKind;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Time\PresenceStatus;
use App\Support\{CarbonFmt, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Validation\ValidationException;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): jede Person
 * setzt ihren Stand selbst — „nicht stören“, „kurz weg“ (mit Ende), „außer
 * Haus“ (mit Ende). Gespeichert wird nur der aktuelle Stand, eine Zeile je
 * Person; ein neuer Stand ersetzt den alten. Der Stand erlischt mit seinem
 * Ende, beim Ausstempeln ({@see AttendanceClockService}) und beim
 * Zurücksetzen. Abgelaufene Zeilen löscht der Dienst beim nächsten Lesen der
 * Organisation — so bleibt kein Verlauf liegen.
 *
 * Wie das Board selbst nur, wenn die Organisation es eingeschaltet hat
 * (`presence.board_enabled`). Gründe, Orte oder Freitext gibt es nicht.
 * Je Anfrage (Scoped) gemerkt: Board, Chat und Personenauswahl lesen die
 * Stände einer Organisation einmal.
 */
#[Scoped]
final class PresenceStatusService {
    /** Spätestes Ende eines Standes ab jetzt, in Stunden (eine Woche). */
    public const int MAX_HOURS = 168;

    /** @var array<int, array<int, PresenceStatus>> Organisation → Person → Stand, je Anfrage */
    private array $memo = [];

    public function enabled(?Organization $organization): bool {
        return $organization instanceof Organization && (bool) data_get($organization->settings, 'presence.board_enabled', false);
    }

    /**
     * Stand setzen (ersetzt einen vorhandenen). Das Ende muss in der Zukunft und
     * höchstens {@see MAX_HOURS} Stunden entfernt liegen; „kurz weg“ und „außer
     * Haus“ brauchen eines.
     *
     * @throws ValidationException
     */
    public function set(User $user, PresenceStatusKind $kind, ?CarbonImmutable $until, ?CarbonImmutable $now = null): PresenceStatus {
        $now ??= CarbonImmutable::now();
        if ($until === null && $kind->requiresUntil()) {
            throw ValidationException::withMessages(['valid_until' => __('presence.validation.until_required')]);
        }
        if ($until !== null && ($until->lessThanOrEqualTo($now) || $until->greaterThan($now->addHours(self::MAX_HOURS)))) {
            throw ValidationException::withMessages(['valid_until' => __('presence.validation.until_range', ['hours' => self::MAX_HOURS])]);
        }

        $status = PresenceStatus::query()->withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['user_id' => $user->id],
            ['organization_id' => (int) $user->organization_id, 'kind' => $kind, 'valid_until' => $until],
        );
        $this->flush();

        return $status;
    }

    /** Stand zurücksetzen (auch beim Ausstempeln). */
    public function clear(User|int $user): void {
        PresenceStatus::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->delete();
        $this->flush();
    }

    /** Gültiger Stand der Person oder null. */
    public function current(User $user, ?CarbonImmutable $now = null): ?PresenceStatus {
        return $this->forOrganization((int) $user->organization_id, $now)[(int) $user->id] ?? null;
    }

    /**
     * Gültige Stände der Organisation, je Person. Abgelaufene Zeilen werden
     * dabei gelöscht (kein Verlauf).
     *
     * @return array<int, PresenceStatus>
     */
    public function forOrganization(int $organizationId, ?CarbonImmutable $now = null): array {
        if ($now === null && isset($this->memo[$organizationId])) {
            return $this->memo[$organizationId];
        }
        $at = $now ?? CarbonImmutable::now();
        $query = PresenceStatus::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId);
        (clone $query)->whereNotNull('valid_until')->where('valid_until', '<=', $at)->delete();

        $statuses = [];
        foreach ($query->currentAt($at)->get() as $status) {
            $statuses[(int) $status->user_id] = $status;
        }
        if ($now === null) {
            $this->memo[$organizationId] = $statuses;
        }

        return $statuses;
    }

    /**
     * Stände für Anzeigen außerhalb des Boards (Chat, Personenauswahl): leer,
     * wenn die Organisation das Board nicht eingeschaltet hat.
     *
     * @return array<int, PresenceStatus>
     */
    public function visibleFor(?Organization $organization): array {
        return $this->enabled($organization) && $organization !== null ? $this->forOrganization((int) $organization->id) : [];
    }

    /** Kurzer Anzeigetext „Nicht stören“ bzw. „Kurz weg bis 14:30“ in der Zeitzone der Organisation. */
    public function describe(PresenceStatus $status): string {
        if ($status->valid_until === null) {
            return $status->kind->label();
        }
        $zone = Tz::current();
        $until = CarbonImmutable::instance($status->valid_until)->setTimezone($zone);
        $time = $until->isSameDay(CarbonImmutable::now($zone)) ? CarbonFmt::ftime($until) : CarbonFmt::fdatetime($until);

        return (string) __('presence.until', ['kind' => $status->kind->label(), 'time' => $time]);
    }

    public function flush(): void {
        $this->memo = [];
    }
}
