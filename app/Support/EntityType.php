<?php
/*
 * Created on   : Mon Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Lesbares, lokalisiertes Label für einen (Morph-)Entity-Typ. Schlüssel ist
 * der unqualifizierte Klassenname im Namespace `entity-types` (siehe
 * lang/<locale>/entity-types.php). Fehlt eine Übersetzung, wird der
 * Klassenname als Fallback zurückgegeben — identisch zur Logik in
 * {@see \App\Models\AuditLog::auditableTypeLabel()}.
 */
final class EntityType {
    public static function label(?string $type): string {
        $type = (string) $type;
        if ($type === '') {
            return '';
        }

        $short = class_basename($type);
        $key = 'entity-types.' . $short;
        $label = __($key);

        return $label === $key ? $short : (string) $label;
    }
}
