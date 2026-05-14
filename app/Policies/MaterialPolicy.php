<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class MaterialPolicy
{
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Material $material): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Material $material): bool
    {
        return false;
    }

    public function delete(User $user, Material $material): bool
    {
        return false;
    }
}
