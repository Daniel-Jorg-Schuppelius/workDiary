<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{HelpView, User};
use App\Services\Help\HelpTopicResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

class HelpController extends Controller {
    public function show(Request $request, HelpTopicResolver $resolver, string $topic): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $row = $resolver->find($topic, $user);
        if ($row === null) {
            return response()->json([
                'found' => false,
                'topic' => $topic,
                'message' => __('Kein Hilfetext verfügbar.'),
            ], Response::HTTP_NOT_FOUND);
        }

        HelpView::query()->create([
            'organization_id' => $user?->organization_id,
            'topic' => $row->topic,
            'locale' => $row->locale,
            'was_helpful' => null,
            'created_at' => CarbonImmutable::now(),
        ]);

        return response()->json([
            'found' => true,
            'topic' => $row->topic,
            'locale' => $row->locale,
            'title' => $row->title,
            'version' => $row->version,
            'body_html' => $row->body_html,
            'related' => $row->related ?? [],
        ]);
    }

    public function search(Request $request, HelpTopicResolver $resolver): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $query = (string) $request->query('q', '');
        $results = $resolver->search($query, $user);

        return response()->json([
            'query' => $query,
            'count' => $results->count(),
            'items' => $results->map(static fn($row): array => [
                'topic' => $row->topic,
                'locale' => $row->locale,
                'title' => $row->title,
            ])->all(),
        ]);
    }

    public function feedback(Request $request, string $topic): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        $data = $request->validate([
            'helpful' => ['required', 'boolean'],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        HelpView::query()->create([
            'organization_id' => $user?->organization_id,
            'topic' => $topic,
            'locale' => (string) ($data['locale'] ?? app()->getLocale()),
            'was_helpful' => (bool) $data['helpful'],
            'created_at' => CarbonImmutable::now(),
        ]);

        return response()->json(['accepted' => true]);
    }
}
