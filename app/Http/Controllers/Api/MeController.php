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
use OpenApi\Attributes as OA;

class MeController extends Controller {
    #[OA\Get(
        path: '/me',
        summary: 'Eigenes Benutzerprofil',
        tags: ['Me'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function __invoke(Request $request): UserResource {
        /** @var User $user */
        $user = $request->user();
        $resource = new UserResource($user);

        return $resource->additional(['meta' => [
            'roles' => $user->getRoleNames(),
        ]]);
    }
}
