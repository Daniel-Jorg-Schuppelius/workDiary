<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLineKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zeilenart einer Belegposition (MVP-1054). Titel und Text gliedern den Beleg
 * und tragen keinen Betrag; eine Alternative (Wahlposition) hat einen Preis,
 * zählt aber erst, wenn der Kunde sie wählt — nur im Angebot.
 */
enum DocumentLineKind: string implements HasLabel {
    use HasOptions;

    case Item = 'item';
    case Title = 'title';
    case Text = 'text';
    case Alternative = 'alternative';

    public function label(): string {
        return match ($this) {
            self::Item => (string) __('invoicing.line_kind.item'),
            self::Title => (string) __('invoicing.line_kind.title'),
            self::Text => (string) __('invoicing.line_kind.text'),
            self::Alternative => (string) __('invoicing.line_kind.alternative'),
        };
    }

    /** Trägt Menge, Einzelpreis und Betrag. */
    public function isPriced(): bool {
        return $this === self::Item || $this === self::Alternative;
    }

    /**
     * Zeilenarten, die ein Beleg ohne Kundenwahl führt (Rechnung, Rechnungsplan).
     *
     * @return list<self>
     */
    public static function forInvoices(): array {
        return [self::Item, self::Title, self::Text];
    }
}
