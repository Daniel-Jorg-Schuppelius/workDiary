<?php
/*
 * Created on   : Fri May 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Sqid.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Services\SqidEncoder;

/**
 * Lenient-statischer Zugriff auf den {@see SqidEncoder}.
 *
 * Abgrenzung zum Service: {@see SqidEncoder} ist der *strenge* Container-Dienst
 * (wirft bei id <= 0, prüft Roundtrips) und wird per DI in Controller/Requests
 * injiziert. Diese Klasse ergänzt ausschließlich die *toleranten* Guards für
 * Views/Filter — null/leere/0-Werte ergeben '' bzw. null statt einer Exception.
 *
 * Verwendung in Blade-Templates und Filter-Parsing, z. B.:
 *   <option value="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $id) }}">
 *   $customerId = \App\Support\Sqid::decode(\App\Models\Customer::class, $request->query('customer'));
 */
final class Sqid {
    /**
     * Kodiert einen numerischen Primärschlüssel in eine modell-spezifische Sqid.
     *
     * Gibt einen leeren String zurück, wenn die ID null/0 ist.
     *
     * @param  class-string  $modelClass
     */
    public static function encode(string $modelClass, int|string|null $id): string {
        if ($id === null || $id === '' || (int) $id <= 0) {
            return '';
        }

        return app(SqidEncoder::class)->encode($modelClass, (int) $id);
    }

    /**
     * Wie {@see encode()}, gibt aber für null/0-Werte null statt eines leeren
     * Strings zurück. Gedacht für JSON-Resources, in denen nullable Foreign-Keys
     * als `null` (statt '') serialisiert werden sollen.
     *
     * @param  class-string  $modelClass
     */
    public static function encodeOrNull(string $modelClass, int|string|null $id): ?string {
        if ($id === null || $id === '' || (int) $id <= 0) {
            return null;
        }

        return app(SqidEncoder::class)->encode($modelClass, (int) $id);
    }

    /**
     * Dekodiert eine modell-spezifische Sqid zurück zum numerischen Primärschlüssel.
     *
     * Gibt null für leere/fehlerhafte Eingaben zurück.
     *
     * @param  class-string  $modelClass
     */
    public static function decode(string $modelClass, int|string|null $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        return app(SqidEncoder::class)->decode($modelClass, (string) $value);
    }

    /**
     * Dekodiert eine Sqid und akzeptiert als Backward-Compat-Fallback auch
     * rohe numerische IDs (alte Bookmarks/Links/API-Clients vor der Sqid-
     * Umstellung). Reihenfolge: erst Sqid-Decoding, dann numerischer Fallback.
     *
     * Nicht-positive oder ungültige Eingaben ergeben `$default` (Standard null).
     * Aufrufer mit Sentinel-Semantik übergeben `$default = 0`, Aufrufer mit
     * "fällt auf den eigenen Account zurück" die jeweilige User-ID.
     *
     * Hinweis: Der numerische Fallback hält Filter-Parameter (nicht das harte
     * Route-Binding via {@see \App\Models\Concerns\HasSqid}) bewusst
     * enumerierbar — Zugriffsschutz erfolgt separat über die jeweilige Query
     * bzw. Berechtigungsprüfung, nicht über die Opazität der ID.
     *
     * @param  class-string  $modelClass
     */
    public static function decodeOrNumeric(string $modelClass, int|string|null $value, ?int $default = null): ?int {
        $raw = $value === null ? '' : (string) $value;

        $id = self::decode($modelClass, $raw);
        if ($id === null && is_numeric($raw)) {
            $id = (int) $raw;
        }

        if ($id === null || $id <= 0) {
            return $default;
        }

        return $id;
    }
}
