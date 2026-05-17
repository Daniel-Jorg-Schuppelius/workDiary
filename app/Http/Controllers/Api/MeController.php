<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $resource = new UserResource($user);

        return $resource->additional(['meta' => [
            'roles' => $user->getRoleNames(),
        ]]);
    }
}
