<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5LrsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Learning\AuthenticateCmi5Session;
use App\Models\Learning\{LearningCmi5Session, LearningXapiDocument};
use App\Services\Learning\{Cmi5LrsRejection, LearningCmi5DocumentStore, LearningCmi5RecordStore, LearningCmi5Runtime};
use Carbon\CarbonImmutable;
use Closure;
use ELearningToolkit\Cmi5\Cmi5;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Learning Record Store für cmi5-AUs (Feature 149, xAPI 1.0.3).
 *
 * Nimmt nur an, was zur Sitzung hinter dem Token gehört. Kein allgemeines LRS:
 * Abfragen liefern allein die Statements der eigenen Einschreibung.
 */
final class Cmi5LrsController extends Controller {
    private const MAX_BODY_BYTES = 1048576;

    public function __construct(
        private readonly LearningCmi5RecordStore $records,
        private readonly LearningCmi5DocumentStore $documents,
    ) {}

    public function preflight(): Response {
        return response()->noContent();
    }

    public function about(): JsonResponse {
        return response()->json(['version' => ['1.0.3']]);
    }

    /** Fetch-URL (cmi5 8.2.3): immer HTTP 200 mit JSON, auch im Fehlerfall. */
    public function fetch(string $token): JsonResponse {
        return response()->json($this->records->fetch($token));
    }

    public function statements(Request $request): Response {
        return $this->handle($request, function (LearningCmi5Session $session) use ($request): Response {
            if ($request->isMethod('PUT')) {
                $id = $this->queryString($request, 'statementId') ?? throw new Cmi5LrsRejection(400, 'statement_id_required');
                $statement = $this->body($request);

                if ($statement === [] || array_is_list($statement)) {
                    throw new Cmi5LrsRejection(400, 'single_statement_required');
                }

                if (isset($statement['id']) && (! is_string($statement['id']) || strtolower($statement['id']) !== strtolower($id))) {
                    throw new Cmi5LrsRejection(400, 'statement_id_mismatch');
                }

                $statement['id'] = strtolower($id);
                $this->records->store($session, [$statement]);

                return response()->noContent();
            }

            if ($request->isMethod('POST')) {
                $body = $this->body($request);
                $batch = [];

                foreach (array_is_list($body) ? $body : [$body] as $statement) {
                    if (! is_array($statement) || $statement === []) {
                        throw new Cmi5LrsRejection(400, 'invalid_statement');
                    }

                    $batch[] = $statement;
                }

                if ($batch === []) {
                    throw new Cmi5LrsRejection(400, 'invalid_statement');
                }

                return response()->json($this->records->store($session, $batch));
            }

            $headers = ['X-Experience-API-Consistent-Through' => CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.v\Z')];
            $id = $this->queryString($request, 'statementId');

            if ($id !== null) {
                $statement = $this->records->find($session, $id);

                return $statement === null ? $this->error(404, 'not_found') : response()->json($statement, 200, $headers);
            }

            if ($this->queryString($request, 'voidedStatementId') !== null) {
                return $this->error(404, 'not_found');
            }

            $limit = $request->integer('limit');

            return response()->json([
                'statements' => $this->records->recent($session, $limit > 0 ? min($limit, 100) : 100),
                'more' => '',
            ], 200, $headers);
        });
    }

    public function state(Request $request): Response {
        return $this->handle($request, function (LearningCmi5Session $session) use ($request): Response {
            $activityId = $this->queryString($request, 'activityId') ?? throw new Cmi5LrsRejection(400, 'activity_id_required');
            $agentHash = $this->agentHash($request, $session);
            $registration = $this->registration($request, $session);
            $stateId = $this->queryString($request, 'stateId', 255);
            $organizationId = $session->organization_id;
            $kind = LearningXapiDocument::KIND_STATE;

            if ($request->isMethod('GET')) {
                return $stateId === null
                    ? response()->json($this->documents->ids($organizationId, $kind, $activityId, $agentHash, $registration, $this->since($request)))
                    : $this->document($this->documents->find($organizationId, $kind, $activityId, $agentHash, $registration, $stateId));
            }

            // LMS.LaunchData schreibt allein das LMS (cmi5 10.0).
            if ($stateId === Cmi5::STATE_LAUNCH_DATA) {
                return $this->error(403, 'launch_data_read_only');
            }

            if ($request->isMethod('DELETE')) {
                if ($stateId === null) {
                    $this->documents->deleteAll($organizationId, $kind, $activityId, $agentHash, $registration, [Cmi5::STATE_LAUNCH_DATA]);
                } else {
                    $this->documents->delete($organizationId, $kind, $activityId, $agentHash, $registration, $stateId);
                }

                return response()->noContent();
            }

            if ($stateId === null) {
                throw new Cmi5LrsRejection(400, 'state_id_required');
            }

            $content = $this->content($request);
            $type = $request->headers->get('Content-Type') ?? 'application/octet-stream';

            if ($request->isMethod('POST')) {
                $this->documents->merge($organizationId, $kind, $activityId, $agentHash, $registration, $stateId, $content, $type);
            } else {
                $this->documents->put($organizationId, $kind, $activityId, $agentHash, $registration, $stateId, $content, $type);
            }

            return response()->noContent();
        });
    }

    public function agentProfile(Request $request): Response {
        return $this->handle($request, function (LearningCmi5Session $session) use ($request): Response {
            $agentHash = $this->agentHash($request, $session);
            $profileId = $this->queryString($request, 'profileId', 255);
            $organizationId = $session->organization_id;
            $kind = LearningXapiDocument::KIND_AGENT_PROFILE;

            if ($request->isMethod('GET')) {
                return $profileId === null
                    ? response()->json($this->documents->ids($organizationId, $kind, null, $agentHash, null, $this->since($request)))
                    : $this->document($this->documents->find($organizationId, $kind, null, $agentHash, null, $profileId));
            }

            if ($profileId === null) {
                throw new Cmi5LrsRejection(400, 'profile_id_required');
            }

            $this->checkPreconditions($request, $this->documents->find($organizationId, $kind, null, $agentHash, null, $profileId));

            if ($request->isMethod('DELETE')) {
                $this->documents->delete($organizationId, $kind, null, $agentHash, null, $profileId);

                return response()->noContent();
            }

            $content = $this->content($request);
            $type = $request->headers->get('Content-Type') ?? 'application/octet-stream';

            if ($request->isMethod('POST')) {
                $this->documents->merge($organizationId, $kind, null, $agentHash, null, $profileId, $content, $type);
            } else {
                $this->documents->put($organizationId, $kind, null, $agentHash, null, $profileId, $content, $type);
            }

            return response()->noContent();
        });
    }

    public function activity(Request $request): Response {
        return $this->handle($request, function () use ($request): Response {
            $activityId = $this->queryString($request, 'activityId') ?? throw new Cmi5LrsRejection(400, 'activity_id_required');

            return response()->json(['objectType' => 'Activity', 'id' => $activityId]);
        });
    }

    /** @param  Closure(LearningCmi5Session): Response  $action */
    private function handle(Request $request, Closure $action): Response {
        $session = $request->attributes->get(AuthenticateCmi5Session::ATTRIBUTE);

        if (! $session instanceof LearningCmi5Session) {
            return $this->error(401, 'unauthorized');
        }

        if (preg_match('/^1\.0(\.\d+)?$/D', (string) $request->headers->get('X-Experience-API-Version', '')) !== 1) {
            return $this->error(400, 'unsupported_version');
        }

        try {
            return $action($session);
        } catch (Cmi5LrsRejection $e) {
            return $this->error($e->status, $e->reason);
        }
    }

    /** Nebenläufigkeit der Profil-Dokumente (xAPI 1.0.3, Concurrency). */
    private function checkPreconditions(Request $request, ?LearningXapiDocument $existing): void {
        $ifMatch = $request->headers->get('If-Match');
        $ifNoneMatch = $request->headers->get('If-None-Match');
        $etag = $existing !== null ? '"' . $existing->etag . '"' : null;

        if ($ifMatch !== null && ($etag === null || ! in_array(trim($ifMatch), [$etag, '*'], true))) {
            throw new Cmi5LrsRejection(412, 'precondition_failed');
        }

        if ($ifNoneMatch !== null && $existing !== null && trim($ifNoneMatch) === '*') {
            throw new Cmi5LrsRejection(412, 'precondition_failed');
        }

        if ($request->isMethod('PUT') && $existing !== null && $ifMatch === null && $ifNoneMatch === null) {
            throw new Cmi5LrsRejection(409, 'concurrency_header_required');
        }
    }

    /** Dokumente gehören dem Actor der Sitzung — fremde Agents sieht die AU nicht. */
    private function agentHash(Request $request, LearningCmi5Session $session): string {
        $raw = $this->queryString($request, 'agent', 2000) ?? throw new Cmi5LrsRejection(400, 'agent_required');
        $agent = json_decode($raw, true);
        $hash = is_array($agent) ? LearningCmi5Runtime::agentHash($agent) : null;

        if ($hash === null) {
            throw new Cmi5LrsRejection(400, 'invalid_agent');
        }

        $actor = LearningCmi5Runtime::actorForSession($session);

        if ($actor === null || LearningCmi5Runtime::agentHash($actor) !== $hash) {
            throw new Cmi5LrsRejection(403, 'agent_mismatch');
        }

        return $hash;
    }

    private function registration(Request $request, LearningCmi5Session $session): ?string {
        $registration = $this->queryString($request, 'registration', 36);

        if ($registration === null) {
            return null;
        }

        if (strtolower($registration) !== $session->registration?->registration) {
            throw new Cmi5LrsRejection(403, 'registration_mismatch');
        }

        return strtolower($registration);
    }

    private function since(Request $request): ?CarbonImmutable {
        $since = $this->queryString($request, 'since', 40);

        if ($since === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($since);
        } catch (Throwable) {
            throw new Cmi5LrsRejection(400, 'invalid_since');
        }
    }

    private function queryString(Request $request, string $name, int $maxLength = 500): ?string {
        $value = $request->query($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > $maxLength) {
            throw new Cmi5LrsRejection(400, 'invalid_' . Str::snake($name));
        }

        return $value;
    }

    /** @return array<mixed> */
    private function body(Request $request): array {
        try {
            $data = json_decode($this->content($request), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Cmi5LrsRejection(400, 'invalid_json');
        }

        if (! is_array($data)) {
            throw new Cmi5LrsRejection(400, 'invalid_json');
        }

        return $data;
    }

    private function content(Request $request): string {
        $content = $request->getContent();

        if (strlen($content) > self::MAX_BODY_BYTES) {
            throw new Cmi5LrsRejection(413, 'too_large');
        }

        return $content;
    }

    private function document(?LearningXapiDocument $document): Response {
        if ($document === null) {
            return $this->error(404, 'not_found');
        }

        return response($document->content, 200, [
            'Content-Type' => $document->content_type,
            'ETag' => '"' . $document->etag . '"',
            'Last-Modified' => $document->updated_at?->toRfc7231String() ?? '',
        ]);
    }

    private function error(int $status, string $reason): JsonResponse {
        return response()->json(['error' => $reason], $status);
    }
}
