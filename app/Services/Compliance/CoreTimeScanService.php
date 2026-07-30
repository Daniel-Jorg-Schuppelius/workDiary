<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoreTimeScanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\{Organization, TimeEntry, User};
use App\Services\Flextime\CoreTimeValidator;
use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Kernzeit-/Rahmenzeit-/Pflichtpausen-Befunde je Mitarbeiter für den
 * Compliance-Scan (Vollreview W2.1): wertet Projektzeit-Einträge über den
 * {@see CoreTimeValidator} aus und aggregiert je Nutzer/Tag/Regel auf ein
 * {@see AttendanceComplianceFinding} (Dedup-Schlüssel des Recorders ist
 * kind+Tag — mehrere Einträge desselben Tages werden zusammengefasst:
 * Rahmenzeit-Minuten summiert, Kernzeit-/Pausen-Messwert = schlechtester Wert).
 */
final class CoreTimeScanService {
    public function __construct(private readonly CoreTimeValidator $validator) {}

    /**
     * @return array<int, list<AttendanceComplianceFinding>>  Befunde je user_id
     */
    public function findingsForRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $users = User::query()
            ->where('organization_id', $organization->getKey())
            ->get()
            ->keyBy(static fn (User $u): int => (int) $u->id);

        if ($users->isEmpty()) {
            return [];
        }

        $entries = TimeEntry::query()
            ->whereIn('user_id', $users->keys()->all())
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('started_at')
            ->get();

        $tz = Tz::current();

        /** @var array<int, array<string, array<string, array{value: int, threshold: int}>>> $byUserDateKind */
        $byUserDateKind = [];

        foreach ($entries as $entry) {
            $user = $users->get((int) $entry->user_id);
            if ($user === null || $entry->started_at === null) {
                continue;
            }

            $date = $entry->started_at->copy()->setTimezone($tz)->toDateString();

            foreach ($this->validator->structuredViolations($user, $entry) as $violation) {
                $kind = $violation['kind'];
                $slot = $byUserDateKind[(int) $entry->user_id][$date][$kind] ?? null;

                if ($slot === null) {
                    $byUserDateKind[(int) $entry->user_id][$date][$kind] = [
                        'value' => $violation['value'],
                        'threshold' => $violation['threshold'],
                    ];
                    continue;
                }

                if ($kind === CoreTimeValidator::KIND_FRAME_TIME) {
                    // Minuten außerhalb der Rahmenzeit über den Tag summieren.
                    $slot['value'] += $violation['value'];
                } else {
                    // Kernzeit-Abdeckung/Pause: schlechtester Wert des Tages.
                    $slot['value'] = min($slot['value'], $violation['value']);
                    $slot['threshold'] = max($slot['threshold'], $violation['threshold']);
                }

                $byUserDateKind[(int) $entry->user_id][$date][$kind] = $slot;
            }
        }

        /** @var array<int, list<AttendanceComplianceFinding>> $result */
        $result = [];
        foreach ($byUserDateKind as $userId => $byDate) {
            foreach ($byDate as $date => $byKind) {
                foreach ($byKind as $kind => $data) {
                    $result[$userId][] = new AttendanceComplianceFinding(
                        userId: $userId,
                        date: $date,
                        kind: $kind,
                        severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                        value: $data['value'],
                        threshold: $data['threshold'],
                    );
                }
            }
        }

        return $result;
    }
}
