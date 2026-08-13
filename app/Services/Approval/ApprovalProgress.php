<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

/**
 * MVP-531: Stand eines Antrags im Genehmigungsverfahren.
 */
final readonly class ApprovalProgress {
    public function __construct(
        public int $approved,
        public int $required,
    ) {}

    public function isFinal(): bool {
        return $this->approved >= $this->required;
    }
}
