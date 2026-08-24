<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractCtiNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gemeinsame Basis der CTI-Normalizer (Vollscan 2026-08-23, B17):
 * fehlertolerantes Zeitstempel-Parsing — ein kaputter Timestamp darf keinen
 * Anruf verwerfen, der Anruf zählt dann „jetzt".
 */
abstract class AbstractCtiNormalizer implements CtiEventNormalizer {
    protected function parseDate(mixed $value): Carbon {
        if (! is_string($value) || $value === '') {
            return Carbon::now();
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
