<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimUserController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Scim;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Services\Scim\{ScimException, ScimResponse, ScimUserService};
use App\Services\SqidEncoder;
use Closure;
use Illuminate\Http\{JsonResponse, Request};
use Throwable;

/**
 * SCIM-2.0-Benutzerendpunkt (Feature 057, MVP-121). Authentifizierung/Org-
 * Bindung/Enterprise-Gating erledigt {@see \App\Http\Middleware\AuthenticateScim}.
 * Es werden ausschließlich interne Konten der Organisation verwaltet
 * (`customer_id IS NULL`); die SCIM-`id` ist die WorkDiary-Sqid.
 */
class ScimUserController extends Controller {
    /** Bulk-Limits (RFC 7644 §3.7) — bewusst klein, Entra/Okta senden ohnehin kein Bulk. */
    public const MAX_OPERATIONS = 100;

    public const MAX_PAYLOAD_SIZE = 1048576; // 1 MiB

    public function __construct(private readonly ScimUserService $service) {}

    /** GET /scim/v2/Users — Liste, optional gefiltert (`userName eq "…"`). */
    public function index(Request $request): JsonResponse {
        return $this->guard(function () use ($request): JsonResponse {
            $org = $this->organization();
            $query = User::query()
                ->where('organization_id', $org->id)
                ->whereNull('customer_id')
                ->orderBy('id');

            $email = $this->parseUserNameFilter((string) $request->query('filter', ''));
            if ($email !== null) {
                $query->where('email', $email);
            }

            $users = $query->limit(200)->get();

            $resources = [];
            foreach ($users as $user) {
                $resources[] = $this->service->toResource($user);
            }

            return ScimResponse::list($resources, count($resources));
        });
    }

    /** POST /scim/v2/Users — anlegen. */
    public function store(Request $request): JsonResponse {
        return $this->guard(function () use ($request): JsonResponse {
            $user = $this->service->create($request->json()->all(), $this->organization());

            return ScimResponse::json($this->service->toResource($user), 201)
                ->header('Location', url('/scim/v2/Users/' . $user->sqid));
        });
    }

    /** GET /scim/v2/Users/{id}. */
    public function show(string $id): JsonResponse {
        return $this->guard(fn (): JsonResponse => ScimResponse::json($this->service->toResource($this->resolve($id))));
    }

    /** PUT /scim/v2/Users/{id} — vollständiges Ersetzen. */
    public function replace(Request $request, string $id): JsonResponse {
        return $this->guard(function () use ($request, $id): JsonResponse {
            $user = $this->service->replace($this->resolve($id), $request->json()->all());

            return ScimResponse::json($this->service->toResource($user));
        });
    }

    /** PATCH /scim/v2/Users/{id} — Teilaktualisierung (u. a. active-Toggle). */
    public function patch(Request $request, string $id): JsonResponse {
        return $this->guard(function () use ($request, $id): JsonResponse {
            $operations = $request->json('Operations');
            if (! is_array($operations)) {
                throw new ScimException(400, 'Operations array is required.', 'invalidValue');
            }
            $user = $this->service->applyPatch($this->resolve($id), array_values($operations));

            return ScimResponse::json($this->service->toResource($user));
        });
    }

    /** DELETE /scim/v2/Users/{id} — Deprovisionierung: deaktivieren (Daten bleiben). */
    public function destroy(string $id): JsonResponse {
        return $this->guard(function () use ($id): JsonResponse {
            $this->service->deactivate($this->resolve($id));

            return ScimResponse::json([], 204);
        });
    }

    /**
     * POST /scim/v2/Bulk — sequenzielle Sammeloperationen (RFC 7644 §3.7).
     * Jede Operation läuft gegen die bestehenden User-Methoden; ein `bulkId`
     * eines POST kann von späteren Operationen per `bulkId:<id>` im Pfad
     * referenziert werden. Antwort ist HTTP 200 mit per-Operation-Status (String);
     * `failOnErrors` bricht nach der angegebenen Fehlerzahl ab.
     */
    public function bulk(Request $request): JsonResponse {
        return $this->guard(function () use ($request): JsonResponse {
            $org = $this->organization();

            if (strlen((string) $request->getContent()) > self::MAX_PAYLOAD_SIZE) {
                throw new ScimException(413, 'Bulk payload too large.', 'tooLarge');
            }

            $operations = $request->json('Operations');
            if (! is_array($operations)) {
                throw new ScimException(400, 'Operations array is required.', 'invalidValue');
            }
            if (count($operations) > self::MAX_OPERATIONS) {
                throw new ScimException(413, 'Too many bulk operations.', 'tooLarge');
            }

            $failOnErrors = $request->json('failOnErrors');
            $failLimit = is_int($failOnErrors) && $failOnErrors > 0 ? $failOnErrors : null;

            /** @var array<string, string> $bulkIds bulkId → Sqid */
            $bulkIds = [];
            $results = [];
            $errors = 0;

            foreach ($operations as $op) {
                if (! is_array($op)) {
                    continue;
                }
                $bulkId = isset($op['bulkId']) && is_scalar($op['bulkId']) ? (string) $op['bulkId'] : null;

                $result = ['method' => strtoupper((string) ($op['method'] ?? ''))];
                if ($bulkId !== null) {
                    $result['bulkId'] = $bulkId;
                }

                try {
                    [$location, $status, $sqid] = $this->runBulkOperation($org, $op, $bulkIds);
                    $result['status'] = (string) $status;
                    if ($location !== null) {
                        $result['location'] = $location;
                    }
                    if ($bulkId !== null && $sqid !== null) {
                        $bulkIds[$bulkId] = $sqid;
                    }
                } catch (ScimException $e) {
                    $result['status'] = (string) $e->status;
                    $result['response'] = $this->scimErrorBody($e);
                    $errors++;
                }

                $results[] = $result;
                if ($failLimit !== null && $errors >= $failLimit) {
                    break;
                }
            }

            return ScimResponse::json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkResponse'],
                'Operations' => $results,
            ]);
        });
    }

    // --- intern -----------------------------------------------------------

    /**
     * Führt eine einzelne Bulk-Operation aus.
     *
     * @param  array<string, mixed>  $op
     * @param  array<string, string>  $bulkIds
     * @return array{0: ?string, 1: int, 2: ?string} [Location, HTTP-Status, Sqid]
     */
    private function runBulkOperation(Organization $org, array $op, array $bulkIds): array {
        $method = strtoupper((string) ($op['method'] ?? ''));
        $path = (string) ($op['path'] ?? '');
        $data = is_array($op['data'] ?? null) ? $op['data'] : [];

        if ($method === 'POST' && $this->isUsersCollectionPath($path)) {
            $user = $this->service->create($data, $org);

            return [$this->userLocation($user), 201, $user->sqid];
        }

        $id = $this->userIdFromPath($path, $bulkIds);
        $user = $this->resolve($id);

        if ($method === 'PUT') {
            $updated = $this->service->replace($user, $data);

            return [$this->userLocation($updated), 200, $updated->sqid];
        }
        if ($method === 'PATCH') {
            $ops = is_array($data['Operations'] ?? null) ? array_values($data['Operations']) : [];
            $this->service->applyPatch($user, $ops);

            return [$this->userLocation($user), 200, $user->sqid];
        }
        if ($method === 'DELETE') {
            $this->service->deactivate($user);

            return [null, 204, $user->sqid];
        }

        throw new ScimException(400, 'Unsupported bulk method/path: ' . $method . ' ' . $path, 'invalidValue');
    }

    private function isUsersCollectionPath(string $path): bool {
        return $this->normalizeScimPath($path) === 'Users';
    }

    /**
     * Extrahiert die User-`id` aus `/Users/{id}`, inkl. `bulkId:`-Auflösung.
     *
     * @param  array<string, string>  $bulkIds
     */
    private function userIdFromPath(string $path, array $bulkIds): string {
        if (preg_match('#^Users/(.+)$#', $this->normalizeScimPath($path), $m) !== 1) {
            throw new ScimException(400, 'Unsupported bulk path: ' . $path, 'invalidValue');
        }

        $id = $m[1];
        if (str_starts_with($id, 'bulkId:')) {
            $ref = substr($id, 7);
            if (! isset($bulkIds[$ref])) {
                throw new ScimException(400, 'Unresolved bulkId reference: ' . $ref, 'invalidValue');
            }

            return $bulkIds[$ref];
        }

        return $id;
    }

    private function normalizeScimPath(string $path): string {
        return preg_replace('#^/?(scim/v2/)?#', '', trim($path)) ?? trim($path);
    }

    private function userLocation(User $user): string {
        return url('/scim/v2/Users/' . $user->sqid);
    }

    /** @return array<string, mixed> */
    private function scimErrorBody(ScimException $e): array {
        $body = [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => (string) $e->status,
            'detail' => $e->getMessage(),
        ];
        if ($e->scimType !== null) {
            $body['scimType'] = $e->scimType;
        }

        return $body;
    }

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

    /** Löst die SCIM-`id` (Sqid) auf ein internes Konto der Organisation auf. */
    private function resolve(string $id): User {
        $org = $this->organization();
        $decoded = app(SqidEncoder::class)->decode(User::class, $id);
        $user = $decoded !== null
            ? User::query()->whereKey($decoded)->where('organization_id', $org->id)->whereNull('customer_id')->first()
            : null;

        if (! $user instanceof User) {
            throw new ScimException(404, 'User not found.');
        }

        return $user;
    }

    /** Parst `userName eq "wert"` (der einzige im MVP unterstützte Filter). */
    private function parseUserNameFilter(string $filter): ?string {
        if ($filter === '' || preg_match('/^userName\s+eq\s+"(.*)"$/i', trim($filter), $m) !== 1) {
            return null;
        }

        return $m[1];
    }
}
