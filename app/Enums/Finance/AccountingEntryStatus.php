<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEntryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer Buchung (Feature 125, MVP-672).
 *
 * `draft` und `ready` sind Arbeitsstände und änderbar. Mit `posted` ist die
 * Buchung fachlich unveränderlich: Korrekturen laufen ausschließlich über eine
 * Gegenbuchung, die den Zustand auf `reversed` fortschreibt.
 */
enum AccountingEntryStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Ready = 'ready';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string {
        return (string) __('enums.finance.accounting-entry-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Ready => 'info',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }

    /** Arbeitsstand — Zeilen und Kopfdaten dürfen geändert werden. */
    public function isMutable(): bool {
        return $this === self::Draft || $this === self::Ready;
    }

    /** Festgeschrieben (posted oder bereits storniert)? */
    public function isPosted(): bool {
        return $this === self::Posted || $this === self::Reversed;
    }
}
