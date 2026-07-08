<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimResponse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Scim;

use Illuminate\Http\JsonResponse;

/**
 * Kleine Helfer für SCIM-2.0-konforme HTTP-Antworten (RFC 7644): Content-Type
 * `application/scim+json` und das standardisierte Fehler-Schema.
 */
final class ScimResponse {
    public const CONTENT_TYPE = 'application/scim+json';

    /**
     * @param  array<string, mixed>  $body
     */
    public static function json(array $body, int $status = 200): JsonResponse {
        return response()->json($body, $status)->header('Content-Type', self::CONTENT_TYPE);
    }

    /**
     * SCIM-Fehlerobjekt (RFC 7644 §3.12). `scimType` präzisiert 4xx-Fehler
     * (z. B. `uniqueness`, `invalidValue`).
     */
    public static function error(int $status, string $detail, ?string $scimType = null): JsonResponse {
        $body = [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => (string) $status,
            'detail' => $detail,
        ];
        if ($scimType !== null) {
            $body['scimType'] = $scimType;
        }

        return self::json($body, $status);
    }

    /**
     * SCIM-ListResponse-Hülle (RFC 7644 §3.4.2).
     *
     * @param  list<array<string, mixed>>  $resources
     */
    public static function list(array $resources, int $total, int $startIndex = 1): JsonResponse {
        return self::json([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $total,
            'startIndex' => $startIndex,
            'itemsPerPage' => count($resources),
            'Resources' => $resources,
        ]);
    }
}
