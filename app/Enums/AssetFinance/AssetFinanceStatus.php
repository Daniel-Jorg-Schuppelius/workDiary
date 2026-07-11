<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

/**
 * Statusmodell der Leasing-/Finanzierungsakte (MVP-270). Ending markiert
 * die Endphase (Rückgabe-/Kauf-/Verlängerungsentscheidung offen).
 */
enum AssetFinanceStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Ending = 'ending';
    case Extended = 'extended';
    case Returned = 'returned';
    case Purchased = 'purchased';
    case Terminated = 'terminated';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('Entwurf'),
            self::Active => (string) __('Aktiv'),
            self::Ending => (string) __('Endphase'),
            self::Extended => (string) __('Verlängert'),
            self::Returned => (string) __('Zurückgegeben'),
            self::Purchased => (string) __('Übernommen (Kauf)'),
            self::Terminated => (string) __('Gekündigt'),
            self::Closed => (string) __('Abgeschlossen'),
            self::Cancelled => (string) __('Storniert'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Ending, self::Extended, self::Terminated],
            self::Ending => [self::Returned, self::Purchased, self::Extended, self::Terminated],
            self::Extended => [self::Ending, self::Terminated],
            self::Returned, self::Purchased, self::Terminated => [self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Draft, self::Active, self::Ending, self::Extended], true);
    }
}
