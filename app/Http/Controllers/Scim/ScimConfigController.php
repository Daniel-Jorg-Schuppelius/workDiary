<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimConfigController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Scim;

use App\Http\Controllers\Controller;
use App\Services\Scim\ScimResponse;
use Illuminate\Http\JsonResponse;

/**
 * SCIM-Discovery-Endpunkte (Feature 057, MVP-121): ServiceProviderConfig und
 * ResourceTypes, damit Identitätsanbieter die unterstützten Fähigkeiten
 * ermitteln können. Unterstützt werden Users und Groups (PATCH + Filter) sowie
 * schlanke Sammeloperationen (Bulk, klein limitiert).
 */
class ScimConfigController extends Controller {
    public function serviceProviderConfig(): JsonResponse {
        return ScimResponse::json([
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'documentationUri' => url('/scim/v2'),
            'patch' => ['supported' => true],
            'bulk' => [
                'supported' => true,
                'maxOperations' => \App\Http\Controllers\Scim\ScimUserController::MAX_OPERATIONS,
                'maxPayloadSize' => \App\Http\Controllers\Scim\ScimUserController::MAX_PAYLOAD_SIZE,
            ],
            'filter' => ['supported' => true, 'maxResults' => 200],
            'changePassword' => ['supported' => false],
            'sort' => ['supported' => false],
            'etag' => ['supported' => false],
            'authenticationSchemes' => [[
                'type' => 'oauthbearertoken',
                'name' => 'OAuth Bearer Token',
                'description' => 'Authentication via the SCIM bearer token issued per organization.',
                'primary' => true,
            ]],
        ]);
    }

    public function resourceTypes(): JsonResponse {
        return ScimResponse::list([
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                'id' => 'User',
                'name' => 'User',
                'endpoint' => '/Users',
                'schema' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                'meta' => ['resourceType' => 'ResourceType', 'location' => url('/scim/v2/ResourceTypes/User')],
            ],
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                'id' => 'Group',
                'name' => 'Group',
                'endpoint' => '/Groups',
                'schema' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
                'meta' => ['resourceType' => 'ResourceType', 'location' => url('/scim/v2/ResourceTypes/Group')],
            ],
        ], 2);
    }
}
