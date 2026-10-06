<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisplaysPartyMatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles\Concerns;

/** Titel und Unterzeile eines Kunden- oder Lieferantentreffers in der Abgleichsliste. */
trait DisplaysPartyMatch {
    public function display(array $mapped): array {
        $title = (string) ($mapped['name'] ?? $mapped['company'] ?? '');
        $subtitleParts = array_filter([
            (string) ($mapped['company'] ?? ''),
            (string) ($mapped['email'] ?? ''),
            (string) ($mapped['vat_id'] ?? ''),
        ], static fn(string $v): bool => $v !== '' && $v !== $title);

        return [
            'title' => $title !== '' ? $title : (string) __('(ohne Namen)'),
            'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
        ];
    }
}
