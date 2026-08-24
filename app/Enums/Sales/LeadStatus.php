<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Pipeline-Status eines Leads (Feature 091): new → contacted → qualified →
 * converted/discarded. Converted und Discarded sind Endzustände — ein
 * konvertierter Lead lebt als Kunde weiter, ein verworfener läuft in die
 * Anonymisierungsfrist.
 */
enum LeadStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Discarded = 'discarded';

    public function label(): string {
        return (string) __('enums.sales.lead_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'primary',
            self::Qualified => 'warning',
            self::Converted => 'success',
            self::Discarded => 'ghost',
        };
    }

    public function isFinal(): bool {
        return $this === self::Converted || $this === self::Discarded;
    }

    /**
     * Erlaubte Folgezustände — die Pipeline kennt kein Zurück hinter
     * Endzustände, wohl aber Requalifizierung eines verworfenen Leads.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::New => [self::Contacted, self::Qualified, self::Discarded],
            self::Contacted => [self::Qualified, self::Discarded],
            self::Qualified => [self::Contacted, self::Discarded],
            self::Discarded => [self::New],
            self::Converted => [],
        };
    }
}
