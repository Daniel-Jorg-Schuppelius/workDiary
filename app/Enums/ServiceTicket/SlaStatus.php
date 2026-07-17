<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abgeleiteter SLA-Lebenszustand eines Tickets (reine Anzeige):
 *  - none:     keine SLA-Frist hinterlegt
 *  - met:      Frist erfüllt (gelöst/abgenommen innerhalb der Frist)
 *  - onTrack:  offen, ausreichend Restzeit
 *  - atRisk:   offen, Restzeit unter dem Schwellwert
 *  - breached: Frist überschritten (oder als verletzt markiert)
 */
enum SlaStatus: string implements HasLabel {
    use HasOptions;

    case None = 'none';
    case Met = 'met';
    case OnTrack = 'onTrack';
    case AtRisk = 'atRisk';
    case Breached = 'breached';

    public function label(): string {
        return match ($this) {
            self::None => __('enums.sla.status.none'),
            self::Met => __('enums.sla.status.met'),
            self::OnTrack => __('enums.sla.status.onTrack'),
            self::AtRisk => __('enums.sla.status.atRisk'),
            self::Breached => __('enums.sla.status.breached'),
        };
    }

    /** Tone für x-status-badge / Pillen-Färbung. */
    public function tone(): string {
        return match ($this) {
            self::None => 'ghost',
            self::Met => 'success',
            self::OnTrack => 'success',
            self::AtRisk => 'warning',
            self::Breached => 'error',
        };
    }

    /** Tailwind-Textklasse für die Detail-/Listen-Pille. */
    public function textClass(): string {
        return match ($this) {
            self::None => 'text-base-content/60',
            self::Met, self::OnTrack => 'text-success',
            self::AtRisk => 'text-warning',
            self::Breached => 'text-error',
        };
    }
}
