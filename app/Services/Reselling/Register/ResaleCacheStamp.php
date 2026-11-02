<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleCacheStamp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use Illuminate\Support\Facades\Cache;

/**
 * Org-Stempel der Reselling-Caches (Leistungsanalyse 2026-11-02, perfc-01/05/07):
 * Überblick, Abgleich-Übersicht, Plan-Befunde und Belegtexte liegen 60 s im
 * Cache unter einem Schlüssel, der den Stempel trägt. Jede Änderung am Register
 * (Bezug, Entscheidung, Import, Lauf, Vorlagen-Sync, Spiegel-Sync) zählt ihn
 * hoch — die alten Einträge laufen über ihre TTL aus. Nur Zahlen im Cache.
 */
final class ResaleCacheStamp {
    public static function current(int $organizationId): int {
        return (int) Cache::get(self::stampKey($organizationId), 0);
    }

    public static function bump(int $organizationId): void {
        Cache::forever(self::stampKey($organizationId), self::current($organizationId) + 1);
    }

    /**
     * Schlüssel eines gestempelten Eintrags: `resale:<name>:<version>:<org>:<stempel>[:<teile>]`.
     * Die Version wechselt, wenn sich die Form des Werts ändert (Cache-Konventionen).
     */
    public static function key(string $name, int $version, int $organizationId, string ...$parts): string {
        return implode(':', ['resale', $name, $version, $organizationId, self::current($organizationId), ...$parts]);
    }

    private static function stampKey(int $organizationId): string {
        return 'resale:stamp:' . $organizationId;
    }
}
