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

    /** Branchenprofil-Code für den BranchProfileInstaller (database/data/branchprofiles/*.php). */
    public function branchProfileCode(): string {
        return match ($this) {
            self::ItService => 'it',
            self::Elektro => 'elektro',
            self::Facility => 'facility',
            self::WartungService => 'anlagenwartung',
        };
    }

    /** Anzeigename der Musterbranche. */
    public function label(): string {
        return match ($this) {
            self::ItService => 'IT-Service',
            self::Elektro => 'Elektro',
            self::Facility => 'Facility Management',
            self::WartungService => 'Wartung & Service',
        };
    }

    /** Name der Demo-Beispielfirma (generisch). */
    public function companyName(): string {
        return match ($this) {
            self::ItService => 'Muster IT-Service GmbH',
            self::Elektro => 'Elektro Muster GmbH',
            self::Facility => 'Muster Facility Service GmbH',
            self::WartungService => 'Muster Wartung & Service GmbH',
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
