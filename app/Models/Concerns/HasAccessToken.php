<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasAccessToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Datensatz, den ein Link mit Klartext-Token erreicht; gespeichert ist nur der
 * Abdruck. Die Auflösung läuft ohne Organisations-Scope, weil erst der Link die
 * Organisation ergibt — jeder Weg baute das bisher selbst
 * (Konsolidierungs-Audit 2026-10, k3-5).
 *
 * Gültigkeit (Status, Ablauf, Widerruf) prüft der Dienst des Weges; die
 * Mandantensperre der Controller über `ChecksTenantPublicSurfaces`.
 */
trait HasAccessToken {
    /** Spalte mit dem Abdruck; abweichende Namen überschreibt das Modell. */
    public static function accessTokenColumn(): string {
        return 'token_hash';
    }

    public static function findByAccessToken(string $token): ?static {
        if ($token === '') {
            return null;
        }

        // TENANT-BYPASS: öffentlicher Link, Auflösung ausschließlich über den Abdruck.
        return static::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where(static::accessTokenColumn(), CryptoHelper::hash($token))
            ->first();
    }
}
