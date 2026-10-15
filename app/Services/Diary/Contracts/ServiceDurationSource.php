<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceDurationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Contracts;

/**
 * Erfahrungswert der Servicedauer eines Auftrags (MVP-1184, Contract mit
 * Null-Bindung): definiert vom Auftragsmodul, gebunden von der Planung
 * ({@see \App\Services\Routing\ServiceDurationSuggester}). Ohne Planung
 * gibt es keinen Erfahrungswert.
 */
interface ServiceDurationSource {
    /** @return array{minutes: int, samples: int, scope: 'customer'|'type'}|null */
    public function suggest(?int $entryTypeId, ?int $customerId = null): ?array;
}
