<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubPortalSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\ClubGuardianPermission;
use App\Models\Club\{ClubGuardian, ClubMember};

/** Mitglied, für das ein angemeldeter Nutzer in „Mein Verein“ handelt: selbst oder als Vertretung (MVP-845). */
final class ClubPortalSubject {
    public function __construct(
        public readonly ClubMember $member,
        public readonly ?ClubGuardian $guardian,
    ) {}

    public function isSelf(): bool {
        return $this->guardian === null;
    }

    /** Eigenes Mitglied darf alles; Vertretung nur, was ihr ausdrücklich erlaubt ist. */
    public function allows(ClubGuardianPermission $permission): bool {
        return $this->guardian === null || $this->guardian->allows($permission);
    }
}
