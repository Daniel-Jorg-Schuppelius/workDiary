<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoIndustry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Demo;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Musterbranchen für Demo-Mandanten (Feature 040).
 *
 * Jede Branche bestimmt:
 *  - das zu installierende Branchenprofil (BranchProfileInstaller),
 *  - die generische Beispiel-Firma (Org-Name-Suffix),
 *  - branchenspezifische Demo-Kunden, -Projekte, Hauptauftrag, Material und Asset.
 *
 * Bewusst generisch gehalten (keine echten Personen/Firmen).
 */
enum DemoIndustry: string implements HasLabel {
    use HasOptions;

    case ItService = 'it-service';
    case Elektro = 'elektro';
    case Facility = 'facility';
    case WartungService = 'wartung-service';
    // Musterbranchen 5–8 (MVP-710, Vollscan G5).
    case Sicherheitsdienst = 'sicherheitsdienst';
    case BauAusbau = 'bau-ausbau';
    case Spedition = 'spedition';
    case Partyservice = 'partyservice';

    /** Branchenprofil-Code für den BranchProfileInstaller (database/data/branchprofiles/*.php). */
    public function branchProfileCode(): string {
        return match ($this) {
            self::ItService => 'it',
            self::Elektro => 'elektro',
            self::Facility => 'facility',
            self::WartungService => 'anlagenwartung',
            self::Sicherheitsdienst => 'sicherheitsdienst',
            self::BauAusbau => 'bau-ausbau',
            self::Spedition => 'spedition',
            self::Partyservice => 'partyservice',
        };
    }

    /** Anzeigename der Musterbranche. */
    public function label(): string {
        return match ($this) {
            self::ItService => 'IT-Service',
            self::Elektro => 'Elektro',
            self::Facility => 'Facility Management',
            self::WartungService => 'Wartung & Service',
            self::Sicherheitsdienst => 'Sicherheitsdienst & Objektschutz',
            self::BauAusbau => 'Bau & Ausbau',
            self::Spedition => 'Spedition & Logistik',
            self::Partyservice => 'Partyservice & Catering',
        };
    }

    /** Name der Demo-Beispielfirma (generisch). */
    public function companyName(): string {
        return match ($this) {
            self::ItService => 'Muster IT-Service GmbH',
            self::Elektro => 'Elektro Muster GmbH',
            self::Facility => 'Muster Facility Service GmbH',
            self::WartungService => 'Muster Wartung & Service GmbH',
            self::Sicherheitsdienst => 'Muster Sicherheitsdienst GmbH',
            self::BauAusbau => 'Muster Ausbau GmbH',
            self::Spedition => 'Muster Spedition GmbH',
            self::Partyservice => 'Muster Partyservice GmbH',
        };
    }

    public static function default(): self {
        return self::ItService;
    }

    public static function fromKey(?string $key): self {
        if ($key === null || $key === '') {
            return self::default();
        }

        return self::tryFrom($key) ?? self::default();
    }

    /** @return array<int, self> */
    public static function all(): array {
        return self::cases();
    }
}
