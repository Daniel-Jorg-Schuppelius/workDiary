<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Zuschlagsberechnung (Feature 005).
 |
 | `stacking` steuert, was bei überlappenden Regeln passiert (z. B. Nachtarbeit
 | am Sonntag):
 |   - 'highest' (Default, Bestandsverhalten): Es gewinnt der höchste
 |     Prozentsatz; bei Gleichstand die höhere priority, dann die ältere Regel.
 |   - 'add': Die Prozentsätze aller zutreffenden Regeln werden addiert. Nach
 |     § 3b EStG ist die Kumulation von Nacht- und Sonntags-/Feiertagszuschlag
 |     zulässig — ob sie im konkreten Fall gewollt ist, entscheidet die
 |     steuerliche Praxis der Organisation.
 |
 | Pro Mandant überschreibbar über Setting `surcharge.stacking`.
 */
return [
    'stacking' => env('SURCHARGE_STACKING', 'highest'),
];
