<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class OrganizationPolicy
{
    use HasAdminBypass;

    /**
     * manage-members darf den Admin-Bypass nicht auslösen:
     * Ein Admin ohne Organisation-Kontext soll keinen Zugriff haben.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'manage-members') {
            return null;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false; // admin-only via before-hook in HasAdminBypass
    }

    public function view(User $user, Organization $organization): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Organization $organization): bool
    {
        return false;
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }

    /**
     * Org-Admins dürfen Mitglieder der eigenen Organisation verwalten.
     */
    public function manageMembers(User $user): bool
    {
        return $user->isAdmin() && $user->organization_id !== null;
    }

    /**
     * Branding (Logos, Farben, Kontakt, PDF-Header) darf ausschließlich
     * ein Admin DER betreffenden Organisation bearbeiten. Cross-Org
     * Admins werden über den `before()`-Hook bewusst durchgelassen.
     */
    public function manageBranding(User $user, Organization $organization): bool
    {
        return $user->isAdmin() && $user->organization_id === $organization->id;
    }
}
