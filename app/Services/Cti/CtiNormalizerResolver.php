<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiNormalizerResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

/**
 * Wählt den passenden {@see CtiEventNormalizer} je Provider (Feature 056,
 * MVP-118). sipgate ist Referenz; unbekannte Provider laufen über das neutrale
 * WorkDiary-Format ({@see GenericNormalizer}).
 */
class CtiNormalizerResolver {
    public function for(string $provider): CtiEventNormalizer {
        return match ($provider) {
            'sipgate' => new SipgateNormalizer(),
            default => new GenericNormalizer(),
        };
    }
}
