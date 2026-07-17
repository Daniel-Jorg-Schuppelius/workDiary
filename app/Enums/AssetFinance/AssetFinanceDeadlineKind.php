<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceDeadlineKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fristart im Fristenkalender (MVP-273). HU/UVV/Eichung bleiben in der
 * Prüfpflichtenverwaltung führend und werden dort terminiert (Feature 075).
 */
enum AssetFinanceDeadlineKind: string implements HasLabel {
    use HasOptions;

    case Termination = 'termination';
    case Extension = 'extension';
    case PurchaseOption = 'purchase_option';
    case Return = 'return';
    case FinalInspection = 'final_inspection';
    case Insurance = 'insurance';
    case Service = 'service';
    case DocumentExpiry = 'document_expiry';

    public function label(): string {
        return (string) __($this->labelKey());
    }

    /**
     * Quell-Key des Labels (JSON-Katalog) — für render-time-i18n-Params
     * (NotificationText: ['key' => …]), damit Empfänger das Label in ihrer
     * Sprache sehen statt in der des Erzeugers.
     */
    public function labelKey(): string {
        return match ($this) {
            self::Termination => 'Kündigungsfrist',
            self::Extension => 'Verlängerungsoption',
            self::PurchaseOption => 'Kaufoption',
            self::Return => 'Rückgabe',
            self::FinalInspection => 'Endprüfung',
            self::Insurance => 'Versicherung',
            self::Service => 'Service/Wartung',
            self::DocumentExpiry => 'Dokumentablauf',
        };
    }
}
