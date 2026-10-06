<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalInboxEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval\Dto;

/** Gegenstand einer Zeile im Genehmigungs-Eingang; ohne `$type` gilt die Bezeichnung des Modells. */
final readonly class ApprovalInboxEntry {
    public function __construct(
        public string $title,
        public ?string $url = null,
        public ?string $reference = null,
        public ?string $type = null,
    ) {}
}
