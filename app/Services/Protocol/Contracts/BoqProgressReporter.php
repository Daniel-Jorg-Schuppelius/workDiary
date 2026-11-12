<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqProgressReporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Contracts;

use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use Illuminate\Validation\ValidationException;

/**
 * Fortschritt zu einer LV-Position aus einem Protokoll melden (MVP-1582). Das
 * Protokoll kennt keine Leistungsverzeichnisse; ohne das Modul gibt es keine
 * Positionen und der Knopf erscheint nicht ({@see NullBoqProgressReporter}).
 */
interface BoqProgressReporter {
    /**
     * Offene LV-Positionen des Projekts, zu dem das Protokoll gehört — leer
     * ohne Projekt, ohne Leistungsverzeichnis oder ohne Recht.
     *
     * @return array<string, string> Schlüssel der Position → Bezeichnung
     */
    public function positionsFor(Protocol $protocol, User $user): array;

    /**
     * Legt die Meldung mit Quelle „Protokoll“ und Verweis auf das Protokoll an.
     *
     * @throws ValidationException wenn die Position nicht zur Auswahl gehört
     */
    public function report(Protocol $protocol, string $position, string $quantity, ?string $note, User $actor): void;
}
