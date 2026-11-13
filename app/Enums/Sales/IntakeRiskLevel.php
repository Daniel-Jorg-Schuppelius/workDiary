<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeRiskLevel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Risikostufe des Prüfpakets GwG (Feature 200, MVP-1642): gering
 * (vereinfachte Sorgfaltspflichten, § 14 GwG), normal (allgemeine, § 10 GwG),
 * hoch (verstärkte, § 15 GwG). Gering und hoch verlangen eine Begründung
 * (§ 8 Abs. 1 Satz 1 Nr. 2 GwG: Informationen über die Risikobewertung).
 */
enum IntakeRiskLevel: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string {
        return (string) __('enums.sales.intake_risk_level.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Low => 'success',
            self::Normal => 'info',
            self::High => 'error',
        };
    }
}
