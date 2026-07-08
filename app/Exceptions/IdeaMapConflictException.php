<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\IdeaMap;
use RuntimeException;

/**
 * Optimistischer Sperr-Konflikt beim Whole-Map-Sync des Canvas (Feature 054,
 * MVP-136): die gesendete karten-weite `lock_version` ist veraltet — seit dem
 * Laden hat eine fremde Änderung (Canvas oder Gliederung) die Karte fortge-
 * schrieben. Trägt die aktuelle Karte, damit der Editor neu laden bzw. einen
 * sichtbaren Konflikt anbieten kann (HTTP 409) — nie stilles Last-write-wins.
 */
class IdeaMapConflictException extends RuntimeException {
    public function __construct(public readonly IdeaMap $currentMap) {
        parent::__construct((string) __('ideas.error.conflict'));
    }
}
