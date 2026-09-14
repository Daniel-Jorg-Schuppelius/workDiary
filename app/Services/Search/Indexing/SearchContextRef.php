<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchContextRef.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing;

/** Aufgelöster Kontext eines Dokuments: Bezüge und ihre suchbaren Texte. */
final class SearchContextRef {
    /** @param  list<string|null>  $texts */
    public function __construct(
        public readonly ?int $customerId = null,
        public readonly ?int $foreignCustomerId = null,
        public readonly ?int $projectId = null,
        public readonly array $texts = [],
    ) {}
}
