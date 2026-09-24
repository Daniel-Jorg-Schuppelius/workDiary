<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoundaryRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Enums\Modules\ModuleKind;

/**
 * Abhängigkeitsregel zwischen Modulen (MVP-863), Matrix nach Modulart:
 *
 * | von \ nach | Platform | Core    | Feature                          |
 * | Platform   | direkt   | nie     | nie                              |
 * | Core       | direkt   | direkt  | nie direkt (Contract oder Event) |
 * | Feature    | direkt   | direkt  | nur mit `requires()` / gleicher Lizenz |
 *
 * Modelle sind die gemeinsame Datensprache und ausgenommen; ebenso Contracts
 * (`…\Contracts\…`), Datentypen (`…\Dto\…`, `…\Exceptions\…`) und
 * Domain-Events (`App\Events\…`): Ein Typ ist kein Verhalten. Plugins unter
 * `app/Plugins/<Name>` sind Adapter über mehrere Module und werden nicht
 * gemessen; `app/Plugins/Support` ist Plattform.
 * Die Plattform erreicht Fachcode nur über Erweiterungspunkte, die die
 * Manifeste befüllen ({@see Manifest::extensions()}).
 */
final class BoundaryRules {
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function allows(Manifest $from, Manifest $to): bool {
        if ($from->code() === $to->code()) {
            return true;
        }
        if ($from->licenseCode() !== null && $from->licenseCode() === $to->licenseCode()) {
            return true;
        }

        return match ($to->kind()) {
            ModuleKind::Platform => true,
            ModuleKind::Core => $from->kind() !== ModuleKind::Platform,
            ModuleKind::Feature => $from->kind() === ModuleKind::Feature && $this->requiresTransitively($from, $to),
        };
    }

    /**
     * Setzt $from das Modul $to direkt oder über eine Kette von `requires()`
     * voraus? Ein vorausgesetztes Modul zieht seine Lizenzfamilie mit
     * (B2B-Katalog requires Inventory ⇒ Procurement unter `module.lager` ist erreichbar).
     */
    public function requiresTransitively(Manifest $from, Manifest $to): bool {
        $seen = [];
        $stack = $from->requires();
        while ($stack !== []) {
            $code = array_pop($stack);
            if ($code === $to->code()) {
                return true;
            }
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $required = $this->registry->byCode($code);
            if ($required === null) {
                continue;
            }
            if ($required->licenseCode() !== null && $required->licenseCode() === $to->licenseCode()) {
                return true;
            }
            foreach ($required->requires() as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }

    /** Begründung für eine Meldung — welche Regel greift. */
    public function explain(Manifest $from, Manifest $to): string {
        return sprintf('%s (%s) → %s (%s)', $from->code(), $from->kind()->value, $to->code(), $to->kind()->value);
    }
}
