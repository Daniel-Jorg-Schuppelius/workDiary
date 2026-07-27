<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IpAddressCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\IpAddress;
use Illuminate\Database\Eloquent\Model;

/**
 * IP-Spalte → {@see IpAddress} (v4/v6).
 *
 * Nützlich für Aufbewahrung und Auswertung: `anonymized()` kürzt die Adresse
 * für Fristenlöschung, `isPrivate()`/`isInRange()` trennen internen von
 * externem Zugriff in den Security-Auswertungen.
 *
 * @extends ValueObjectCast<IpAddress>
 */
class IpAddressCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?IpAddress {
        return IpAddress::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var IpAddress $value */
        return $value->getValue();
    }
}
