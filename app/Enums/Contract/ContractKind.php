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
    // Mietbedingungen des Geräteverleihs (Feature 073, MVP-895): Fassung je Kunde, unterschrieben.
    case RentalTerms = 'rental_terms';
    // Arbeitsvertrag (MVP-939): Gegenpartei ist die Person, nur mit Personalakten-Recht sichtbar.
    case Employment = 'employment';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Rent => (string) __('enums.contract.contract_kind.rent'),
            self::Maintenance => (string) __('enums.contract.contract_kind.maintenance'),
            self::License => (string) __('enums.contract.contract_kind.license'),
            self::Service => (string) __('enums.contract.contract_kind.service'),
            self::Insurance => (string) __('enums.contract.contract_kind.insurance'),
            self::Supply => (string) __('enums.contract.contract_kind.supply'),
            self::Framework => (string) __('enums.contract.contract_kind.framework'),
            self::Membership => (string) __('enums.contract.contract_kind.membership'),
            self::DataProcessing => (string) __('enums.contract.contract_kind.data_processing'),
            self::NonDisclosure => (string) __('enums.contract.contract_kind.non_disclosure'),
            self::RentalTerms => (string) __('enums.contract.contract_kind.rental_terms'),
            self::Employment => (string) __('enums.contract.contract_kind.employment'),
            self::Other => (string) __('enums.contract.contract_kind.other'),
        };
    }

    /** Vertragsart mit Unterzeichnungsschicht (Fassungen, Links, Nachweise). */
    public function requiresSigning(): bool {
        return $this === self::Employment || in_array($this, self::signingKinds(), true);
    }

    /** @return list<self> */
    public static function signingKinds(): array {
        return [self::DataProcessing, self::NonDisclosure, self::RentalTerms];
    }
}
