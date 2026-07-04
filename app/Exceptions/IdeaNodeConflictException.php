<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\IdeaNode;
use RuntimeException;

/**
 * Optimistischer Sperr-Konflikt eines Ideen-Knotens (Feature 054, MVP-108):
 * die gesendete `lock_version` ist veraltet. Trägt den aktuellen Serverstand,
 * damit der Editor einen sichtbaren Konfliktdialog anbieten kann (HTTP 409) —
 * nie stilles Last-write-wins.
 */
class IdeaNodeConflictException extends RuntimeException {
    public function __construct(public readonly IdeaNode $currentNode) {
        parent::__construct((string) __('ideas.error.conflict'));
    }
}
