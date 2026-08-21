<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasPhoneSearchKeys.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\PhoneSearchKey;

/**
 * Normalisierter Rufnummern-Suchschlüssel (Folgepunkt aus Audit-Welle 2.4).
 *
 * `phone`/`mobile` bleiben, wie der Mensch sie eingegeben hat — sie stehen auf
 * Belegen und in Exporten. Gesucht wird über `phone_e164`/`mobile_e164`, die
 * hier bei jedem Speichern aus dem Anzeigewert abgeleitet werden. Ohne diesen
 * Schlüssel musste der Abgleich über einen LIKE auf die letzten sieben Ziffern
 * gehen, und der scheiterte an Trennzeichen mitten in der Nummer.
 *
 * Nicht deutbare Eingaben ergeben `null` statt einer geratenen Nummer: Ein
 * falscher Schlüssel fände den falschen Kunden, und das fällt niemandem auf.
 */
trait HasPhoneSearchKeys {
    public static function bootHasPhoneSearchKeys(): void {
        static::saving(function (self $model): void {
            $model->setAttribute('phone_e164', PhoneSearchKey::of((string) $model->getAttribute('phone')));
            $model->setAttribute('mobile_e164', PhoneSearchKey::of((string) $model->getAttribute('mobile')));
        });
    }
}
