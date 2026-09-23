<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Manifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use Illuminate\Support\Str;

/**
 * Modulvertrag (MVP-861): eine Quelle je Modul für Lizenzcode, Ordner,
 * Tabellen, Routen, Rechtegruppen, Navigation, Plugins und Abhängigkeiten.
 * Das {@see ModuleRegistry} liest alle Manifeste unter `app/Modules/Manifests`
 * und speist daraus das Routen-Gate, die Navigation und den Modulkatalog;
 * `modules:check` prüft die Vollständigkeit.
 */
abstract class Manifest {
    /** Modulcode, z. B. `club`. Eindeutig über alle Manifeste. */
    abstract public function code(): string;

    abstract public function kind(): ModuleKind;

    /** Lesbarer Name (Modulkatalog, Gate-Meldung). */
    abstract public function label(): string;

    /** Lizenzcode `module.*`, wenn das Modul lizenziert wird; sonst null (Kern/Plattform). */
    public function licenseCode(): ?string {
        return null;
    }

    /**
     * Trägt dieses Manifest Label und Beschreibung des Lizenzcodes? Mehrere
     * Manifeste dürfen einen Lizenzcode teilen (Fertigung und Beschaffung
     * unter `module.lager`); genau eines ist der Eigentümer.
     */
    public function ownsLicense(): bool {
        return true;
    }

    public function description(): string {
        return '';
    }

    /**
     * Domänenordner je Schicht (`app/Models`, `app/Services`,
     * `app/Http/Controllers`, `app/Policies`, …). Standard: StudlyCase des Codes.
     *
     * @return list<string>
     */
    public function folders(): array {
        return [Str::studly($this->code())];
    }

    /** @return list<string> Ordner unter `resources/views` */
    public function viewFolders(): array {
        return [];
    }

    /** @return list<string> Tabellen des Moduls — jede Tabelle des Schemas gehört genau einem Manifest. */
    public function tables(): array {
        return [];
    }

    /**
     * Routennamen-Muster (`Str::is`), die das Modul-Gate für den Lizenzcode
     * sperrt. Kern- und Plattformmodule listen ihre Muster nur zur Doku.
     *
     * @return list<string>
     */
    public function routePatterns(): array {
        return [];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [];
    }

    /**
     * Sidebar-Schlüssel, die am Lizenzcode hängen: Sektionen, Einträge (Routen)
     * und Gruppen.
     *
     * @return array{sections: list<string>, items: list<string>, groups: list<string>}
     */
    public function navigation(): array {
        return ['sections' => [], 'items' => [], 'groups' => []];
    }

    /** @return list<string> Modulcodes, die dieses Modul voraussetzt (direkte Nutzung erlaubt, Lizenz zieht mit). */
    public function requires(): array {
        return [];
    }

    /** @return list<string> Plugin-Kennungen, die fachlich zu diesem Modul gehören. */
    public function plugins(): array {
        return [];
    }

    /**
     * Erweiterungspunkte der Plattform, die dieses Modul befüllt (MVP-863):
     * Interface → Implementierungen.
     *
     * @return array<class-string, list<class-string>>
     */
    public function extensions(): array {
        return [];
    }

    /**
     * Contracts, die dieses Modul bindet (MVP-863): Interface → Implementierung.
     *
     * @return array<class-string, class-string>
     */
    public function bindings(): array {
        return [];
    }

    /**
     * Domain-Events, auf die dieses Modul reagiert (MVP-863): Event → Listener.
     *
     * @return array<class-string, list<class-string>>
     */
    public function listeners(): array {
        return [];
    }
}
