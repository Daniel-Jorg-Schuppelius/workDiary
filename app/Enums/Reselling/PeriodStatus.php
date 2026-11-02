<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Zustand einer erwarteten Abrechnungsperiode. Entschiedene Zustände
 * (berechnet, teilweise, verzichtet, strittig) überlebt jede Neuplanung.
 *
 * Übergänge (MVP-872, Sicherheitsaudit 2026-11-02, sec-03): offen, teilweise
 * und berechnet folgen der Deckung und lassen sich verzichten oder strittig
 * stellen; verzichtet und strittig sind Nutzerentscheidungen, aus denen nur
 * „Zurücknehmen“ (→ offen) führt.
 */
enum PeriodStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Billed = 'billed';
    case Partial = 'partial';
    case Waived = 'waived';
    case Disputed = 'disputed';

    public function label(): string {
        return (string) __('resale.period_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'error',
            self::Billed => 'success',
            self::Partial => 'warning',
            self::Waived => 'neutral',
            self::Disputed => 'warning',
        };
    }

    /**
     * Nicht mehr offen (berechnet, teilweise, verzichtet, strittig) — Anzeige-
     * Semantik. Ob die Planung eine Periode anfassen darf, entscheidet
     * `ResalePeriod::isLocked()` (Nutzerentscheidung `decided_at`, Verzicht,
     * strittig); „berechnet" allein durch Vorschläge ist keine Entscheidung.
     */
    public function isDecided(): bool {
        return $this !== self::Open;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Billed, self::Partial, self::Waived, self::Disputed],
            self::Partial => [self::Open, self::Billed, self::Waived, self::Disputed],
            self::Billed => [self::Open, self::Partial, self::Waived, self::Disputed],
            self::Waived, self::Disputed => [self::Open],
        };
    }
}
