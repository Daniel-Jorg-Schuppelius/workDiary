<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalPinService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Platform\{User, UserTerminalPin};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Terminal-PIN (MVP-803, Entscheid P6-18): Personalnummer + PIN als Ersatz für
 * einen vergessenen Ausweis. Nach `MAX_ATTEMPTS` Fehlversuchen ist die PIN
 * gesperrt — die Sperre eskaliert über die Zyklen hinweg.
 *
 * Sicherheitsaudit 2026-09-17 (kiosk-1): Vorher wurde der Fehlzähler mit jeder
 * Sperre auf 0 gesetzt. Eine vierstellige PIN war damit dauerhaft ratbar
 * (5 Versuche je 15 Minuten, ohne Eskalation und ohne Ende). Jetzt zählt der
 * Zähler weiter, die Sperre wächst (15 min → 1 h → 1 Tag), und neue PINs sind
 * mindestens sechsstellig. Bewusst keine dauerhafte Sperre: die ließe sich von
 * außen als Aussperrfalle benutzen.
 */
class TerminalPinService {
    public const MIN_LENGTH = 6;

    public const MAX_LENGTH = 8;

    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    /** Zweite Stufe: eine Stunde. */
    public const LONG_LOCK_MINUTES = 60;

    /** Dritte Stufe und darüber: ein Tag — Raten wird damit sinnlos. */
    public const MAX_LOCK_MINUTES = 1440;

    private ?string $dummyHash = null;

    public function set(User $user, string $pin, User $actor): UserTerminalPin {
        if (preg_match('/^\d{' . self::MIN_LENGTH . ',' . self::MAX_LENGTH . '}$/', $pin) !== 1) {
            throw ValidationException::withMessages(['pin' => (string) __('terminal.pin.error.format', ['min' => self::MIN_LENGTH, 'max' => self::MAX_LENGTH])]);
        }
        if (blank($user->personnel_number)) {
            throw ValidationException::withMessages(['user' => (string) __('terminal.pin.error.personnel_number')]);
        }

        $record = UserTerminalPin::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'organization_id' => (int) $user->organization_id,
                'pin_hash' => Hash::make($pin),
                'failed_attempts' => 0,
                'locked_until' => null,
                'set_by' => $actor->id,
            ],
        );
        $record->audit('terminal.pin_set', ['user_id' => $user->id, 'by_user_id' => $actor->id]);

        return $record;
    }

    public function remove(UserTerminalPin $record, User $actor): void {
        $record->audit('terminal.pin_removed', ['user_id' => $record->user_id, 'by_user_id' => $actor->id]);
        $record->delete();
    }

    public function unlock(UserTerminalPin $record, User $actor): void {
        $record->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        $record->audit('terminal.pin_unlocked', ['user_id' => $record->user_id, 'by_user_id' => $actor->id]);
    }

    /**
     * Person zu Personalnummer + PIN, oder null. Unbekannte Nummer, falsche und
     * gesperrte PIN sind von außen nicht unterscheidbar; auch die Laufzeit gleicht
     * sich über einen Hash-Vergleich gegen eine Attrappe an.
     */
    public function resolve(int $organizationId, string $personnelNumber, string $pin): ?User {
        $personnelNumber = trim($personnelNumber);
        $user = $personnelNumber === '' ? null : User::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('personnel_number', $personnelNumber)
            ->whereNull('deactivated_at')
            ->first();
        $record = $user instanceof User
            ? UserTerminalPin::query()->withoutGlobalScopes()->where('user_id', $user->id)->first()
            : null;

        if (! $record instanceof UserTerminalPin) {
            Hash::check($pin, $this->dummyHash ??= Hash::make(Str::random(16)));

            return null;
        }
        if ($record->isLocked()) {
            return null;
        }

        if (Hash::check($pin, $record->pin_hash)) {
            if ($record->failed_attempts > 0) {
                $record->forceFill(['failed_attempts' => 0])->save();
            }

            return $user;
        }

        // Der Zähler läuft über Sperrzyklen hinweg weiter — nur ein Treffer
        // setzt ihn zurück (kiosk-1).
        $attempts = $record->failed_attempts + 1;
        $lockedUntil = $record->locked_until;
        $locks = intdiv($attempts, self::MAX_ATTEMPTS);
        if ($attempts % self::MAX_ATTEMPTS === 0) {
            $lockedUntil = match (true) {
                $locks >= 3 => Carbon::now()->addMinutes(self::MAX_LOCK_MINUTES),
                $locks === 2 => Carbon::now()->addMinutes(self::LONG_LOCK_MINUTES),
                default => Carbon::now()->addMinutes(self::LOCK_MINUTES),
            };
        }

        $record->forceFill([
            'failed_attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ])->save();

        if ($attempts % self::MAX_ATTEMPTS === 0) {
            $record->audit('terminal.pin_locked', [
                'user_id' => $record->user_id,
                'failed_attempts' => $attempts,
                'locked_minutes' => (int) Carbon::now()->diffInMinutes($lockedUntil, absolute: true),
            ]);
        }

        return null;
    }
}
