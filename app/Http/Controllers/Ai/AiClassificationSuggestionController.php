<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiClassificationSuggestionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SuggestTagsRequest;
use App\Models\{Classification, Customer, Tag};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\ClassificationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * JSON-Endpunkt der KI-Tag-/Katalogvorschläge (Feature 143, MVP-711):
 * liefert NUR bestehende Tags (Sqid) und Katalogwerte — die Übernahme bleibt
 * ein Klick im regulären Tag-Input, nie Auto-Apply. KI-Fehler (Capability
 * aus, Budget erschöpft, Provider gestört) enden als 422 mit Meldung.
 */
class AiClassificationSuggestionController extends Controller {
    public function __construct(private readonly ClassificationSuggestionService $suggestions) {}

    public function tags(SuggestTagsRequest $request): JsonResponse {
        abort_unless(Gate::allows(Permission::AiUse->value), 403);

        $data = $request->validated();
        $organization = app('currentOrganization');

        $customer = isset($data['customer_id']) ? Customer::query()->find((int) $data['customer_id']) : null;
        $domain = isset($data['domain']) ? ClassificationDomain::from((string) $data['domain']) : null;

        try {
            $tags = $this->suggestions->suggestTags($organization, (string) $data['text'], $customer);
            $values = $domain !== null
                ? $this->suggestions->suggestCatalogValues($organization, $domain, (string) $data['text'])
                : collect();
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'tags' => $tags->map(static fn (Tag $t): array => [
                'id' => $t->sqid,
                'name' => $t->name,
                'color' => $t->color,
            ])->values(),
            'values' => $values->map(static fn (Classification $c): array => [
                'code' => $c->code,
                'label' => $c->label,
            ])->values(),
        ]);
    }
}
