<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekDay.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

/**
 * Wochentags-Konstanten (kompatibel mit Carbons numerischer Wochentags-API).
 *
 * Werte folgen Carbon (Sonntag = 0 ... Samstag = 6).
 * Dient zur Vermeidung der Intelephense-Warnung PHP6606
 * (Carbon-Konstanten werden über Kind-Klassen referenziert).
 */
final class WeekDay {
    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;
}
