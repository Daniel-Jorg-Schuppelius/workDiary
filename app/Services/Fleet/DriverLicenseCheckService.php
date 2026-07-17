<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheckService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Fleet;

use App\Models\{DriverLicenseCheck, Organization, User};
use Carbon\{Carbon, CarbonInterface};

/**
 * Führerscheinkontrolle (MVP-417): Fälligkeiten je Fahrer und Sperrwirkung
 * auf Fahrzeugreservierungen. Sperr-Semantik: ÜBERFÄLLIG ist nur, wer schon
 * einmal kontrolliert wurde und dessen letzte Kontrolle + Frist verstrichen
 * ist (oder dessen Führerschein-Gültigkeit abgelaufen ist) — Fahrer ohne
 * jede Kontrolle werden gemeldet, aber nicht gesperrt (kein Big-Bang bei
 * Aktivierung in Bestands-Organisationen).
 */
class DriverLicenseCheckService {
    public const DEFAULT_INTERVAL_MONTHS = 6;

    /** Kontrolle dokumentieren; die nächste Fälligkeit folgt dem Org-Intervall. */
    public function record(User $driver, User $checker, CarbonInterface $checkedAt, ?string $licenseClasses = null, ?CarbonInterface $validUntil = null, ?string $note = null): DriverLicenseCheck {
        $checkedAt = Carbon::parse($checkedAt->toDateString());

        return DriverLicenseCheck::create([
            'organization_id' => $driver->organization_id,
            'user_id' => $driver->id,
            'checked_by' => $checker->id,
            'checked_at' => $checkedAt->toDateString(),
            'license_classes' => $licenseClasses,
            'license_valid_until' => $validUntil?->toDateString(),
            'next_due_on' => $checkedAt->copy()->addMonthsNoOverflow($this->intervalMonths($driver->organization))->toDateString(),
            'note' => ($note !== null && trim($note) !== '') ? $note : null,
        ]);
    }

    public function latestFor(int $userId): ?DriverLicenseCheck {
        return DriverLicenseCheck::query()
            ->where('user_id', $userId)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Überfällig = letzte Kontrolle existiert und Fälligkeit/Gültigkeit verstrichen. */
    public function isOverdue(int $userId, ?CarbonInterface $today = null): bool {
        $today = Carbon::parse(($today ?? Carbon::today())->toDateString());
        $latest = $this->latestFor($userId);
        if ($latest === null) {
            return false;
        }
        if ($latest->license_valid_until !== null && $today->greaterThan($latest->license_valid_until)) {
            return true;
        }

        return $today->greaterThan($latest->next_due_on);
    }

    private function intervalMonths(?Organization $organization): int {
        $stored = $organization?->settings['fleet']['license_check_interval_months'] ?? null;

        return is_numeric($stored) && (int) $stored >= 1 ? (int) $stored : self::DEFAULT_INTERVAL_MONTHS;
    }
}
