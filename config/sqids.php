<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sqids.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Sqids Global Salt
    |--------------------------------------------------------------------------
    |
    | Geheimer Salt-String, der pro Modell mit dem Klassennamen kombiniert
    | wird, um eine eindeutige Sqid-Alphabet-Permutation zu erzeugen. Muss
    | über Deploys hinweg konstant bleiben — anderenfalls werden alle bereits
    | ausgegebenen URLs ungültig. In Produktion MUSS dieser Wert gesetzt sein.
    |
    */
    'salt' => env('SQIDS_SALT', ''),

    /*
    |--------------------------------------------------------------------------
    | Minimale Sqid-Länge
    |--------------------------------------------------------------------------
    |
    | Polstert kurze IDs auf eine Mindestlänge auf, sodass auch kleine PKs
    | (1, 2, …) nicht in 1–2-Zeichen-Sqids resultieren und sich Sqids
    | unterschiedlicher Modelle auf den ersten Blick nicht unterscheiden
    | lassen.
    |
    */
    'min_length' => (int) env('SQIDS_MIN_LENGTH', 10),

    /*
    |--------------------------------------------------------------------------
    | Alphabet
    |--------------------------------------------------------------------------
    |
    | Standard-Alphabet (Sqids-Default). Wird pro Modell aus `salt + class`
    | deterministisch permutiert.
    |
    */
    'alphabet' => env('SQIDS_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),

    /*
    |--------------------------------------------------------------------------
    | Blocklist
    |--------------------------------------------------------------------------
    |
    | Wörter, die nicht in generierten Sqids vorkommen dürfen.
    |
    */
    'blocklist' => [],
];
