<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardsScimRequests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Scim\Concerns;

use App\Models\Platform\Organization;
use App\Services\Scim\{ScimException, ScimResponse};
use Closure;
use Illuminate\Http\JsonResponse;
use Throwable;

/** Fehlerrahmen und Organisationskontext der SCIM-Endpunkte. */
trait GuardsScimRequests {
    private function guard(Closure $fn): JsonResponse {
        try {
            return $fn();
        } catch (ScimException $e) {
            return ScimResponse::error($e->status, $e->getMessage(), $e->scimType);
        } catch (Throwable $e) {
            return ScimResponse::error(500, class_basename($e));
        }
    }

    private function organization(): Organization {
        $org = app('currentOrganization');
        if (! $org instanceof Organization) {
            throw new ScimException(401, 'No organization context.');
        }

        return $org;
    }
}
