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
}
