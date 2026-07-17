<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Statusmodell der Verleihakte (MVP-258/261). Verlängerung bleibt im Status
 * handed_over (nur ends_at wandert, auditiert); overdue wird vom
 * Fristen-Scanner gesetzt, sobald ends_at ohne Rückgabe verstrichen ist.
 */
enum RentalCaseStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Reserved = 'reserved';
    case HandedOver = 'handed_over';
    case Overdue = 'overdue';
    case Returned = 'returned';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('Entwurf'),
            self::Reserved => (string) __('Reserviert'),
            self::HandedOver => (string) __('Übergeben'),
            self::Overdue => (string) __('Überfällig'),
            self::Returned => (string) __('Zurückgegeben'),
            self::Closed => (string) __('Abgeschlossen'),
            self::Cancelled => (string) __('Storniert'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Reserved, self::Cancelled],
            self::Reserved => [self::HandedOver, self::Cancelled],
            self::HandedOver => [self::Overdue, self::Returned],
            self::Overdue => [self::Returned],
            self::Returned => [self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Draft, self::Reserved, self::HandedOver, self::Overdue], true);
    }

    public function blocksAvailability(): bool {
        return in_array($this, [self::Reserved, self::HandedOver, self::Overdue], true);
    }
}
