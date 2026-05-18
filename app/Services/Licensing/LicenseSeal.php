<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseSeal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Diese Datei wird durch `php artisan license:seal` regeneriert.
 * Manuelle Änderungen gehen beim nächsten Sealing verloren.
 *
 * - PUBLIC_KEY: hartkodierter Ed25519 Public Key (base64url). Hat Vorrang vor
 *   der env-Konfiguration, damit ein Patch der .env nicht reicht, um eigene
 *   Lizenzen einzuschleusen.
 * - FILES: sha256-Hashes der lizenzrelevanten Dateien. Beim Boot wird verifi-
 *   ziert, dass keine davon manipuliert wurde.
 * - SEALED_AT: Zeitstempel der Versiegelung (nur informativ).
 *
 * Im unversiegelten Zustand (alle Werte leer) verhält sich die App wie zuvor
 * und fällt auf die env-Konfiguration zurück.
 */

namespace App\Services\Licensing;

final class LicenseSeal
{
    public const PUBLIC_KEY = '';

    /**
     * @var array<string, string> relativer Pfad => sha256-hex
     */
    public const FILES = [];

    public const SEALED_AT = '';

    public static function isSealed(): bool
    {
        return self::PUBLIC_KEY !== '';
    }
}
