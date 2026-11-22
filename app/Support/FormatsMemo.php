<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormatsMemo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Aufgelöste Anzeigeformate je Request bzw. Job (scoped gebunden). Jede
 * Datumszelle fragt {@see Formats}; ohne Merker liest jede Zelle Nutzer-,
 * Organisations- und Systemeinstellung neu.
 */
final class FormatsMemo {
    /** @var array<string, string> Zustandsschlüssel → Format */
    public array $formats = [];

    /** @var array<string, array{0: list<mixed>, 1: string}> Formatart → Zustand und Ergebnis des letzten Aufrufs */
    public array $last = [];
}
