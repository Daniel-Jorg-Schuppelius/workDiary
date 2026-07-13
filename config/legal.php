<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : legal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Öffentliche Rechtstexte der Installation (MVP-326). Die Inhalte sind
 * betreiberspezifisch und werden über die Settings-Registry
 * (legal.imprint / legal.privacy, System-Scope) gepflegt; diese Datei
 * liefert nur die env-überschreibbaren Defaults (typisch: leer).
 */

return [
    'imprint' => env('LEGAL_IMPRINT'),
    'privacy' => env('LEGAL_PRIVACY'),
];
