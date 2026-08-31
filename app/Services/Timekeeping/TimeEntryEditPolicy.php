<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryEditPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timekeeping;

use App\Models\{TimeEntry, User};
use App\Services\TimeApproval\MonthClosureService;
use App\Support\Setting;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Determines whether a TimeEntry is still self-editable by its owner.
 *
 * Three independent constraints — failing ANY of them blocks the owner
 * (admins bypass via the policy's HasAdminBypass trait):
 *
 *   1. Correction window: now must be within "edit_window.days" days
 *      after the entry's `date`. Default 7 days, overridable per setting
 *      `timesheet.edit_window_days`.
 *   2. The owning Timesheet (if any) must not be signed or locked.
 *   3. The entry must not be `exported` (e.g. transmitted to lexoffice).
 */
class TimeEntryEditPolicy {
    public const REASON_WINDOW = 'window';

    public const REASON_TIMESHEET_LOCKED = 'timesheet_locked';

    public const REASON_TIMESHEET_SIGNED = 'timesheet_signed';

    public const REASON_EXPORTED = 'exported';

    /** Der Monat des Eintrags ist für diesen Mitarbeiter freigegeben/gesperrt. */
    public const REASON_MONTH_CLOSED = 'month_closed';

    public function windowDays(): int {
        $setting = Setting::get('timesheet.edit_window_days');
        if ($setting !== null && $setting !== '') {
            return max(0, (int) $setting);
        }

        return max(0, (int) config('timesheet.edit_window.days', 7));
    }

    public function isWithinWindow(TimeEntry $entry, ?CarbonInterface $now = null): bool {
        $date = $entry->date;
        if ($date === null) {
            return true;
        }

        $now ??= CarbonImmutable::now();
        $deadline = CarbonImmutable::instance($date)->endOfDay()->addDays($this->windowDays());

        return $now->lessThanOrEqualTo($deadline);
    }

    /**
     * @return array{locked: bool, reason: ?string}
     */
    public function isHardLocked(TimeEntry $entry): array {
        if ($entry->exported) {
            return ['locked' => true, 'reason' => self::REASON_EXPORTED];
        }

        // **Der freigegebene Monat sperrt auch Projektzeiten** (Sicherheitsscan
        // 2026-08-23, S-32). Die harte Sperre kannte nur `exported` und den
        // Stundenzettel; der Monatsabschluss wurde bis dahin allein von
        // Importen, Korrekturanträgen und Plugins beachtet. Ein Mitarbeiter
        // konnte deshalb nach der Freigabe seines Juni noch Zeiten mit
        // `date=2026-06-15` anlegen oder ändern — Gleitzeitsaldo und die
        // Lohnzeilen (Reisezeit, Bereitschaft, Überstundenzuschlag) werden zur
        // Exportzeit gerechnet und stiegen mit.
        $owner = $entry->user;
        if ($owner instanceof User && $entry->date !== null
            && app(MonthClosureService::class)->isPeriodLockedForUser($owner, CarbonImmutable::instance($entry->date))) {
            return ['locked' => true, 'reason' => self::REASON_MONTH_CLOSED];
        }

        $timesheet = $entry->timesheet;
        if ($timesheet !== null) {
            if ($timesheet->isLocked()) {
                return ['locked' => true, 'reason' => self::REASON_TIMESHEET_LOCKED];
            }
            if ($timesheet->isSigned()) {
                // isSigned() is true for both SIGNED and LOCKED — LOCKED is
                // already handled above, so this branch covers pure SIGNED.
                return ['locked' => true, 'reason' => self::REASON_TIMESHEET_SIGNED];
            }
        }

        return ['locked' => false, 'reason' => null];
    }

    public function canSelfEdit(TimeEntry $entry, ?CarbonInterface $now = null): bool {
        if ($this->isHardLocked($entry)['locked']) {
            return false;
        }

        return $this->isWithinWindow($entry, $now);
    }

    /**
     * Combined diagnostic for UI rendering. Returns the first reason that
     * blocks self-editing, or null if the entry is editable.
     */
    public function blockReason(TimeEntry $entry, ?CarbonInterface $now = null): ?string {
        $hard = $this->isHardLocked($entry);
        if ($hard['locked']) {
            return $hard['reason'];
        }

        if (! $this->isWithinWindow($entry, $now)) {
            return self::REASON_WINDOW;
        }

        return null;
    }

    public function reasonLabel(?string $reason): ?string {
        $label = match ($reason) {
            self::REASON_WINDOW => __('Korrekturfenster abgelaufen'),
            self::REASON_TIMESHEET_LOCKED => __('Stundenzettel gesperrt'),
            self::REASON_TIMESHEET_SIGNED => __('Stundenzettel signiert'),
            self::REASON_EXPORTED => __('Eintrag bereits exportiert'),
            self::REASON_MONTH_CLOSED => __('Monat bereits freigegeben'),
            default => null,
        };

        return is_string($label) ? $label : null;
    }
}
