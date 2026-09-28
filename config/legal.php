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
 * Ist eine *_url gesetzt, leitet die Seite dorthin weiter (vorhandenes
 * Impressum bzw. vorhandene Datenschutzerklärung des Betreibers).
 */

return [
    'imprint' => env('LEGAL_IMPRINT'),
    'privacy' => env('LEGAL_PRIVACY'),
    'imprint_url' => env('LEGAL_IMPRINT_URL'),
    'privacy_url' => env('LEGAL_PRIVACY_URL'),
];
