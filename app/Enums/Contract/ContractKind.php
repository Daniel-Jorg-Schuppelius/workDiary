<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vertragsart des allgemeinen Vertragslebenszyklus (Welle D, CLM). Bewusst
 * breit für Verträge beliebiger Art — Leasing/Finanzierung bleibt im
 * spezialisierten AssetFinance-Modell (Feature 074) und verweist optional
 * additiv hierher.
 */
enum ContractKind: string implements HasLabel {
    use HasOptions;

    case Rent = 'rent';
    case Maintenance = 'maintenance';
    case License = 'license';
    case Service = 'service';
    case Insurance = 'insurance';
    case Supply = 'supply';
    case Framework = 'framework';
    case Membership = 'membership';
    // Kundenvereinbarungen (Feature 157, MVP-822): zwingend Kunde als Partner,
    // Aktivierung erst nach vollständig unterzeichneter Fassung.
    case DataProcessing = 'data_processing';
    case NonDisclosure = 'non_disclosure';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Rent => (string) __('Miet-/Pachtvertrag'),
            self::Maintenance => (string) __('Wartungsvertrag'),
            self::License => (string) __('Lizenz-/Abovertrag'),
            self::Service => (string) __('Dienstleistungsvertrag'),
            self::Insurance => (string) __('Versicherungsvertrag'),
            self::Supply => (string) __('Liefer-/Bezugsvertrag'),
            self::Framework => (string) __('Rahmenvertrag'),
            self::Membership => (string) __('Mitgliedschaft/Beitrag'),
            self::DataProcessing => (string) __('Auftragsverarbeitungsvertrag (AVV)'),
            self::NonDisclosure => (string) __('Verschwiegenheitsvereinbarung (NDA)'),
            self::Other => (string) __('Sonstiger Vertrag'),
        };
    }

    /** Vertragsart mit Unterzeichnungsschicht (Fassungen, Links, Nachweise). */
    public function requiresSigning(): bool {
        return in_array($this, self::signingKinds(), true);
    }

    /** @return list<self> */
    public static function signingKinds(): array {
        return [self::DataProcessing, self::NonDisclosure];
    }
}
