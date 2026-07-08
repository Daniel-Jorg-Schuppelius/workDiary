<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimGroupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Scim;

use App\Http\Controllers\Controller;
use App\Models\{Organization, ScimGroup};
use App\Services\Scim\{ScimException, ScimGroupService, ScimResponse};
use App\Services\SqidEncoder;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * SCIM-2.0-Gruppenendpunkt (Feature 057, MVP-121 → Rang 16). Auth/Org-Bindung/
 * Enterprise-Gating erledigt {@see \App\Http\Middleware\AuthenticateScim}. Die
 * SCIM-`id` ist die WorkDiary-Sqid; Team-Mapping ist ein Admin-Schritt und kommt
 * NICHT vom IdP. SCIM vergibt weiterhin keine Rollen.
 */
class ScimGroupController extends Controller {
    public function __construct(private readonly ScimGroupService $service) {}

    /** GET /scim/v2/Groups — Liste, optional `displayName eq "…"`; `excludedAttributes=members` respektiert. */
    public function index(Request $request): JsonResponse {
        return $this->guard(function () use ($request): JsonResponse {
            $org = $this->organization();
            $includeMembers = ! $this->excludesMembers($request);

            $query = ScimGroup::query()
                ->where('organization_id', $org->id)
                ->orderBy('id');

            $displayName = $this->parseDisplayNameFilter((string) $request->query('filter', ''));
            if ($displayName !== null) {
                $query->where('display_name', $displayName);
            }

            $resources = [];
            foreach ($query->limit(200)->get() as $group) {
                $resources[] = $this->service->toResource($group, $includeMembers);
            }

            return ScimResponse::list($resources, count($resources));
        });
    }

    /** POST /scim/v2/Groups — anlegen. */
    public function store(Request $request): JsonResponse {
        return $this->guard(function () use ($request): JsonResponse {
            $group = $this->service->create($request->json()->all(), $this->organization());

            return ScimResponse::json($this->service->toResource($group), 201)
                ->header('Location', url('/scim/v2/Groups/' . $group->sqid));
        });
    }

    /** GET /scim/v2/Groups/{id}. */
    public function show(Request $request, string $id): JsonResponse {
        return $this->guard(fn (): JsonResponse => ScimResponse::json(
            $this->service->toResource($this->resolve($id), ! $this->excludesMembers($request)),
        ));
    }

    /** PUT /scim/v2/Groups/{id} — vollständiges Ersetzen (inkl. voller Mitgliederliste). */
    public function replace(Request $request, string $id): JsonResponse {
        return $this->guard(function () use ($request, $id): JsonResponse {
            $group = $this->service->replace($this->resolve($id), $request->json()->all());

            return ScimResponse::json($this->service->toResource($group));
        });
    }

    /** PATCH /scim/v2/Groups/{id} — Teilaktualisierung. Antwort 204 ohne Body (Entra-konform). */
    public function patch(Request $request, string $id): JsonResponse {
        return $this->guard(function () use ($request, $id): JsonResponse {
            $operations = $request->json('Operations');
            if (! is_array($operations)) {
                throw new ScimException(400, 'Operations array is required.', 'invalidValue');
            }
            $this->service->applyPatch($this->resolve($id), array_values($operations));

            return ScimResponse::json([], 204);
        });
    }

    /** DELETE /scim/v2/Groups/{id} — Gruppe löschen (Mitglieder werden aus dem Team gelöst). */
    public function destroy(string $id): JsonResponse {
        return $this->guard(function () use ($id): JsonResponse {
            $this->service->delete($this->resolve($id));

            return ScimResponse::json([], 204);
        });
    }

    // --- intern -----------------------------------------------------------

    /** Fängt SCIM-Fehler ein und übersetzt sie in SCIM-Fehlerantworten. */
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

    /** Löst die SCIM-`id` (Sqid) auf eine Gruppe der Organisation auf. */
    private function resolve(string $id): ScimGroup {
        $org = $this->organization();
        $decoded = app(SqidEncoder::class)->decode(ScimGroup::class, $id);
        $group = $decoded !== null
            ? ScimGroup::query()->whereKey($decoded)->where('organization_id', $org->id)->first()
            : null;

        if (! $group instanceof ScimGroup) {
            throw new ScimException(404, 'Group not found.');
        }

        return $group;
    }

    private function excludesMembers(Request $request): bool {
        $excluded = strtolower((string) $request->query('excludedAttributes', ''));

        return in_array('members', array_map('trim', explode(',', $excluded)), true);
    }

    /** Parst `displayName eq "wert"`. */
    private function parseDisplayNameFilter(string $filter): ?string {
        if ($filter === '' || preg_match('/^displayName\s+eq\s+"(.*)"$/i', trim($filter), $m) !== 1) {
            return null;
        }

        return $m[1];
    }
}
