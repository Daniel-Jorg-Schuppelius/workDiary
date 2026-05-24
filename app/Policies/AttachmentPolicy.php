<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Attachment, Organization, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class AttachmentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /**
     * Defense in Depth zusätzlich zum OrganizationScope: verweigert
     * den Zugriff, sobald das Attachment einer anderen Organisation
     * als der aktuell aktiven gehört. Greift auch in Konsolen-/Queue-
     * Kontexten, in denen der Global Scope nicht aktiv ist.
     */
    public function view(User $user, Attachment $attachment): bool {
        return $this->sharesOrganization($user, $attachment);
    }

    public function create(User $user): bool {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool {
        return $this->sharesOrganization($user, $attachment) && $this->owns($user, $attachment);
    }

    private function sharesOrganization(User $user, Attachment $attachment): bool {
        $attachmentOrgId = $attachment->organization_id;

        // Globale/legacy-Anhänge (kein Org-Bezug, z. B. Logo der
        // Plattform-Organisation) bleiben für jeden eingeloggten
        // Benutzer sichtbar.
        if ($attachmentOrgId === null) {
            return true;
        }

        $activeOrgId = null;
        if (app()->bound('currentOrganization')) {
            $current = app('currentOrganization');
            if ($current instanceof Organization) {
                $activeOrgId = $current->id;
            }
        }

        if ($activeOrgId !== null) {
            return (int) $attachmentOrgId === (int) $activeOrgId;
        }

        return $user->organization_id !== null
            && (int) $attachmentOrgId === (int) $user->organization_id;
    }
}
