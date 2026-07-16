<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCapabilityMatrix.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Domain;

use App\Enums\Domain\DomainCapabilityArea;

/**
 * Fähigkeitsmatrix einer DomainReselling-Verbindung (Feature 083). Nur
 * nachgewiesene Bereiche sind verfügbar; nicht belegte Fähigkeiten (v. a.
 * Rechnungen) erscheinen als erklärter Blocked-State statt als scheinbar
 * funktionierender Button.
 *
 * Standard: alle dokumentierten Bereiche verfügbar, `Invoices` gesperrt
 * (im Vertrag nicht belegt — MVP-393). Eine reale Verbindung überschreibt
 * die Matrix nach dem Capability-Pilot.
 */
final class DomainCapabilityMatrix {
    /**
     * @param  array<string, bool>  $available  area-value => verfügbar?
     */
    private function __construct(private readonly array $available) {}

    /** Konservativer Default aus dem dokumentierten Handbuch. */
    public static function default(): self {
        $map = [];
        foreach (DomainCapabilityArea::cases() as $area) {
            $map[$area->value] = $area->isDocumented();
        }

        return new self($map);
    }

    /**
     * Aus gespeicherten Verbindungs-Fähigkeiten (`capabilities`-Spalte); fehlt
     * ein Bereich, gilt der dokumentierte Default.
     *
     * @param  array<string, bool>|null  $stored
     */
    public static function fromStored(?array $stored): self {
        $default = self::default()->available;
        if ($stored === null) {
            return new self($default);
        }

        $map = $default;
        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $default)) {
                $map[$key] = (bool) $value;
            }
        }

        return new self($map);
    }

    public function allows(DomainCapabilityArea $area): bool {
        return $this->available[$area->value] ?? false;
    }

    /** @return array<string, bool> */
    public function toArray(): array {
        return $this->available;
    }
}
