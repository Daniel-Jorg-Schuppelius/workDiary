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

/**
 * Fristart im Fristenkalender (MVP-273). HU/UVV/Eichung bleiben in der
 * Prüfpflichtenverwaltung führend und werden dort terminiert (Feature 075).
 */
enum AssetFinanceDeadlineKind: string {
    case Termination = 'termination';
    case Extension = 'extension';
    case PurchaseOption = 'purchase_option';
    case Return = 'return';
    case FinalInspection = 'final_inspection';
    case Insurance = 'insurance';
    case Service = 'service';
    case DocumentExpiry = 'document_expiry';

    public function label(): string {
        return match ($this) {
            self::Termination => (string) __('Kündigungsfrist'),
            self::Extension => (string) __('Verlängerungsoption'),
            self::PurchaseOption => (string) __('Kaufoption'),
            self::Return => (string) __('Rückgabe'),
            self::FinalInspection => (string) __('Endprüfung'),
            self::Insurance => (string) __('Versicherung'),
            self::Service => (string) __('Service/Wartung'),
            self::DocumentExpiry => (string) __('Dokumentablauf'),
        };
    }
}
