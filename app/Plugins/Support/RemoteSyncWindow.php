<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSyncWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use Carbon\CarbonImmutable;

/**
 * Zusicherung, dass ein Import-Lauf **alle** Einträge des Fensters geliefert hat.
 *
 * Nur damit darf der Import auf fehlende Einträge schließen (drüben gelöscht).
 * Ein Lauf, der gefiltert liest — nur der eigene Benutzer, nur ein Projekt, ein
 * CSV-Ausschnitt —, darf **kein** Fenster übergeben: sonst gälte alles
 * Ungelieferte fälschlich als gelöscht.
 */
final class RemoteSyncWindow {
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /** Fenster nur, wenn der Lauf vollständig war — sonst null. */
    public static function whenComplete(bool $complete, ?CarbonImmutable $from, ?CarbonImmutable $to): ?self {
        if (! $complete || $from === null || $to === null) {
            return null;
        }

        return new self($from, $to);
    }
}
