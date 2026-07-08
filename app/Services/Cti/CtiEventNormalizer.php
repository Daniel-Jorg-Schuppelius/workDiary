<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiEventNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

/**
 * Providerspezifischer Adapter, der ein CTI-Webhook-Payload in ein neutrales
 * {@see CtiCall} übersetzt (Feature 056, MVP-118). `null` = kein zu
 * protokollierendes (terminales) Ereignis — z. B. Zwischenzustände wie
 * „klingelt"/„angenommen"; protokolliert wird der abgeschlossene Anruf.
 */
interface CtiEventNormalizer {
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): ?CtiCall;
}
