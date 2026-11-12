<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationEnforcement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Diary\DiaryEntry;
use App\Models\Hr\Qualification;
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\ScheduledShift;
use App\Services\Diary\Contracts\AssignmentQualificationGuard;
use App\Support\CarbonFmt;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Hinweis oder Sperre bei fehlender Pflichtqualifikation (Feature 013,
 * MVP-1514), Einstellung `qualifications.enforcement` der Organisation.
 * Ob eine Qualifikation am Einsatztag gilt, entscheidet allein der
 * {@see QualificationGate}; hier fällt nur die Entscheidung, ob eine
 * Zuweisung abgelehnt wird.
 */
final class QualificationEnforcement implements AssignmentQualificationGuard {
    public const string SETTING = 'qualifications.enforcement';

    public const string MODE_HINT = 'hint';

    public const string MODE_BLOCK = 'block';

    public function __construct(private readonly QualificationGate $gate = new QualificationGate) {}

    public function blocks(?int $organizationId): bool {
        if ($organizationId === null) {
            return false;
        }

        $current = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $organization = $current instanceof Organization && (int) $current->id === $organizationId
            ? $current
            : Organization::query()->withoutGlobalScopes()->find($organizationId);

        return data_get($organization?->settings, self::SETTING) === self::MODE_BLOCK;
    }

    /** Ablehnungstext, wenn die Organisation sperrt und der Schicht eine Pflichtqualifikation fehlt. */
    public function shiftBlockMessage(ScheduledShift $shift): ?string {
        if (! $this->blocks($shift->organization_id !== null ? (int) $shift->organization_id : null)) {
            return null;
        }

        $user = $shift->user;

        return $user instanceof User ? $this->message('schedule.qualification.blocked', $user, $this->gate->missingFor($shift), $shift->date) : null;
    }

    /**
     * Ablehnungstext für die Zuweisung eines Auftrags an eine Person; Stichtag
     * ist der geplante Beginn, sonst der geplante Tag.
     */
    public function entryBlockMessage(DiaryEntry $entry, int $userId, ?CarbonInterface $date = null): ?string {
        return $this->blocks((int) $entry->organization_id) ? $this->entryMissingMessage('schedule.qualification.blocked', $entry, $userId, $date) : null;
    }

    /** Hinweis statt Sperre (MVP-1528): der Person fehlt eine Pflichtqualifikation, die Organisation sperrt aber nicht. */
    public function entryHint(DiaryEntry $entry, int $userId, ?CarbonInterface $date = null): ?string {
        return $this->blocks((int) $entry->organization_id) ? null : $this->entryMissingMessage('schedule.qualification.hint', $entry, $userId, $date);
    }

    private function entryMissingMessage(string $key, DiaryEntry $entry, int $userId, ?CarbonInterface $date): ?string {
        /** @var Collection<int, Qualification> $required */
        $required = $entry->requiredQualifications()->get();
        if ($required->isEmpty()) {
            return null;
        }

        $user = User::query()->withoutGlobalScopes()->with('qualifications')->find($userId);
        if (! $user instanceof User) {
            return null;
        }

        $date ??= $entry->start_at !== null
            ? CarbonImmutable::parse((string) $entry->start_at)
            : ($entry->scheduled_for !== null ? CarbonImmutable::parse((string) $entry->scheduled_for) : null);
        $status = $this->gate->statusFor($user, $required, $date);

        return $this->message($key, $user, $required->filter(fn (Qualification $q): bool => ($status[(int) $q->id] ?? 'missing') === 'missing')->values(), $date);
    }

    /** @throws ValidationException */
    public function assertEntryAssignable(DiaryEntry $entry, int $userId, ?CarbonInterface $date = null, string $field = 'assigned_user_id'): void {
        $message = $this->entryBlockMessage($entry, $userId, $date);
        if ($message !== null) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** @param Collection<int, Qualification> $missing */
    private function message(string $key, User $user, Collection $missing, ?CarbonInterface $date): ?string {
        if ($missing->isEmpty()) {
            return null;
        }

        return (string) __($key, [
            'user' => $user->name,
            'qualifications' => $missing->map(fn (Qualification $q): string => (string) ($q->abbreviation ?: $q->name))->implode(', '),
            'date' => $date !== null ? CarbonFmt::fdate($date) : '—',
        ]);
    }
}
