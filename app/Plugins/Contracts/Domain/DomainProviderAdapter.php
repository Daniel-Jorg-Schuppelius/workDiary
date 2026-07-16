<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Plugins\Support\Domain\{DomainCapabilityMatrix, DomainResponse};

/**
 * Provider-Adapter des DomainReselling-Kontos (Feature 083). Die generische
 * `command + params → properties`-Oberfläche des Providers ist die legitime
 * Naht: der Adapter fügt Capability-Gating, `EOF`-Pflicht, Redaktion und
 * Fehler-Mapping hinzu (keine dünne Fassade). App-Services mappen die
 * `DomainResponse`-Properties in Projektionen/Commands.
 *
 * Alle Aufrufe laufen POST-only; Login/Passwort werden je Request übermittelt
 * und niemals in URLs/Logs sichtbar.
 */
interface DomainProviderAdapter {
    /**
     * Führt einen Provider-Befehl aus. Gehört der Befehl zu einem nicht
     * belegten Fähigkeitsbereich, wird {@see \App\Plugins\Support\Domain\DomainCapabilityBlockedException}
     * geworfen; eine unvollständige Antwort (kein `EOF`) wirft
     * {@see \App\Plugins\Support\Domain\DomainProviderException} mit `incomplete`.
     *
     * @param  array<string, scalar|null>  $params
     * @param  DomainCapabilityArea|null  $area  Fähigkeitsbereich zur Matrix-Prüfung
     */
    public function execute(string $command, array $params = [], ?DomainCapabilityArea $area = null): DomainResponse;

    /** Erkannte/erlaubte Fähigkeiten dieser Verbindung. */
    public function capabilities(): DomainCapabilityMatrix;
}
