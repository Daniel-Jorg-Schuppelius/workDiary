<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchDocumentData.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing;

use App\Enums\Search\SearchSourceType;
use Carbon\CarbonInterface;

/**
 * Inhalt eines Suchdokuments, wie ihn eine Quelle liefert. `primaryTexts`
 * (Titel, Kerntext) zählen für die Relevanz doppelt.
 */
final class SearchDocumentData {
    /**
     * @param  list<string|null>  $primaryTexts
     * @param  list<string|null>  $texts
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly SearchSourceType $type,
        public readonly int $sourceId,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly array $primaryTexts,
        public readonly array $texts,
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly bool $dateOnly = false,
        public readonly ?int $userId = null,
        public readonly ?int $assignedUserId = null,
        public readonly ?int $customerId = null,
        public readonly ?int $foreignCustomerId = null,
        public readonly ?int $projectId = null,
        public readonly ?int $minutes = null,
        public readonly bool $restricted = false,
        public readonly ?CarbonInterface $sourceUpdatedAt = null,
    ) {}
}
