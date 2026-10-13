<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use App\Enums\Billing\DocumentRelationKind;

/** Verbundener Beleg und was er für den betrachteten ist (MVP-1157). */
final readonly class DocumentRelation {
    public function __construct(
        public DocumentNode $node,
        public DocumentRelationKind $kind,
    ) {}
}
