<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering\Contracts;

use App\Enums\Numbering\NumberScope;
use App\Services\Numbering\Dto\IssuedDocument;
use Illuminate\Database\Eloquent\Model;

/**
 * Buchhaltungsprogramm, das Belege eines Kreises beim Übertragen nummeriert
 * (Phase 145, F1: workDiary schreibt, das Programm vergibt die Nummer).
 * Plugins tragen sich über `ModuleRegistry::contribute()` ein; die Seite
 * „Nummernkreise“ bietet das Programm dann für diese Kreise an.
 */
interface DocumentIssuer {
    /** Plugin-ID des Programms (Wert in `number_formats.source`). */
    public function pluginId(): string;

    /** @return list<NumberScope> Kreise, deren Belege das Programm anlegt und nummeriert. */
    public function scopes(): array;

    /**
     * Legt den Beleg im Programm festgeschrieben an, merkt die Verknüpfung
     * (`ExternalReference`) und liefert die vergebene Nummer. Ein schon
     * übertragener Beleg wird nicht erneut angelegt.
     *
     * @throws \RuntimeException wenn das Programm ablehnt oder keine Nummer liefert
     */
    public function issue(Model $document, NumberScope $scope): IssuedDocument;
}
