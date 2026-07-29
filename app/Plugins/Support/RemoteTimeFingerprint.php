<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteTimeFingerprint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Fingerabdruck eines importierten Zeiteintrags im Fremdsystem.
 *
 * Wird beim Import in der {@see \App\Models\ExternalReference} hinterlegt und
 * vor dem Zurückschreiben erneut über den dann aktuellen Fremdstand gebildet.
 * Weichen die beiden ab, hat jemand drüben nachgearbeitet — dann wird nicht
 * überschrieben, sondern ein Konflikt in die Inbox gestellt.
 *
 * Bewusst nur die Felder, die auch zurückgeschrieben werden: sonst meldet eine
 * fremde Änderung an einem hier gar nicht gespiegelten Feld einen Konflikt.
 */
final class RemoteTimeFingerprint {
    public static function of(ImportedTimeEntry $entry): string {
        return self::fromParts(
            $entry->startedAt,
            $entry->endedAt,
            $entry->description,
            $entry->projectId,
            $entry->billable,
        );
    }

    /**
     * Gleiche Bildung aus Einzelteilen — für den frisch geholten Fremdstand,
     * der noch nicht durch die Import-Normalisierung gelaufen ist.
     */
    public static function fromParts(
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        ?string $description,
        ?int $projectId,
        bool $billable,
    ): string {
        return (string) CryptoHelper::hash(implode('|', [
            $startedAt->utc()->format('Y-m-d\TH:i:s'),
            $endedAt->utc()->format('Y-m-d\TH:i:s'),
            trim((string) $description),
            (string) ($projectId ?? ''),
            $billable ? '1' : '0',
        ]));
    }

    /**
     * Variante für Systeme ohne Start-/Stoppzeiten (OpenProject: Buchungsdatum
     * + Dauer).
     *
     * Bewusst **ohne** Kommentar und Abrechenbarkeit: lokal trägt die
     * Beschreibung zusätzlich den Work-Package-Titel, und die Abrechenbarkeit
     * stammt aus der Plugin-Konfiguration statt aus dem Fremdsystem — beides
     * würde bei jedem Lauf einen Scheinkonflikt erzeugen.
     */
    public static function fromDuration(CarbonImmutable $date, int $minutes): string {
        return (string) CryptoHelper::hash(implode('|', [
            $date->format('Y-m-d'),
            (string) $minutes,
        ]));
    }
}
