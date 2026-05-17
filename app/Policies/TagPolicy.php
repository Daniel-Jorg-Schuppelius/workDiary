<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class TagPolicy
{
    use HasAdminBypass;

    /**
     * Admin darf alles (Katalog-Pflege).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    /**
     * Jeder eingeloggte User darf ad-hoc neue Tags anlegen.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Bearbeiten/Löschen nur Admin (durch before-Hook abgedeckt).
     */
    public function update(User $user, Tag $tag): bool
    {
        return false;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return false;
    }
}
