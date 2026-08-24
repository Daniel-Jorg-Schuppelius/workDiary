<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Integration\WebhookEvent;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Jobs\Integration\WebhookDeliveryJob;
use App\Models\Integration\WebhookEndpoint;
use App\Models\User;
use App\Services\Integration\WebhookDispatchService;
use App\Support\UrlSafety;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Öffentliche REST-Hooks-API (Feature 008 → Rang 61) für n8n/Make/Zapier.
 * Sanctum-Token-geschützt mit Ability `hooks:manage`. Ein Subscribe-Call legt
 * einen {@see WebhookEndpoint} mit genau EINEM Event an (feine Granularität fürs
 * 410-Auto-Unsubscribe); Zustellung/Signatur/Auto-Disable übernimmt die
 * bestehende Webhook-Infrastruktur. Das Secret erzeugt der Server und gibt es
 * genau einmal in der 201-Antwort zurück.
 */
class HookController extends Controller {
    use ResolvesCurrentOrganization;

    /** GET /api/hooks — die Hook-Subscriptions der Organisation. */
    public function index(Request $request): JsonResponse {
        $organizationId = $this->organizationId();

        $hooks = WebhookEndpoint::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (WebhookEndpoint $hook): array => $this->present($hook))
            ->all();

        return response()->json(['data' => $hooks]);
    }

    /** GET /api/hooks/events — Ereignis-Katalog mit Sample-Payload je Event. */
    public function events(Request $request, WebhookDispatchService $dispatch): JsonResponse {
        $organizationId = $this->organizationId();
        $now = Carbon::now();

        $catalog = array_map(static fn (WebhookEvent $event): array => [
            'event' => $event->value,
            'label' => $event->label(),
            // Identisch zur Live-Hülle (buildPayload) — Make/Zapier lernen daran das Schema.
            'sample_payload' => $dispatch->buildPayload($event, $organizationId, $event->sampleData(), $now),
        ], WebhookEvent::cases());

        return response()->json(['data' => $catalog]);
    }

    /** POST /api/hooks {event, target_url} — 201 + id + einmaliges Secret. */
    public function store(Request $request): JsonResponse {
        $organizationId = $this->organizationId();
        $user = $request->user();

        $data = $request->validate([
            'event' => ['required', Rule::enum(WebhookEvent::class)],
            'target_url' => [
                'required', 'url', 'max:2048', 'starts_with:https://,http://',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! UrlSafety::isAcceptableExternalHttpUrl((string) $value)) {
                        $fail((string) __('Die Ziel-URL ist nicht erlaubt (interne/nicht öffentliche Adresse).'));
                    }
                },
            ],
        ]);

        $event = WebhookEvent::from((string) $data['event']);
        $secret = WebhookEndpoint::generateSecret();

        $hook = new WebhookEndpoint([
            'label' => 'REST-Hook: ' . $event->label(),
            'url' => $data['target_url'],
            'secret' => $secret,
            'events' => [$event->value],
            'active' => true,
            'created_by_user_id' => $user instanceof User ? $user->id : null,
        ]);
        $hook->organization_id = $organizationId;
        $hook->save();

        return response()->json($this->present($hook) + [
            // Klartext-Secret genau EINMAL — danach nur noch die Vorschau.
            'secret' => $secret,
            'signature' => [
                'header' => WebhookDeliveryJob::SIGNATURE_HEADER,
                'timestamp_header' => WebhookDeliveryJob::TIMESTAMP_HEADER,
                'algorithm' => 'hmac-sha256',
                'signed_payload' => 'timestamp.body',
            ],
        ], 201);
    }

    /** POST /api/hooks/{hook}/test — Test-Event senden (Struktur-Lernen). */
    public function test(Request $request, WebhookEndpoint $hook, WebhookDispatchService $dispatch): JsonResponse {
        $this->assertOwned($hook);

        $delivery = $dispatch->sendTest($hook);

        return response()->json(['data' => ['delivery_id' => $delivery->id, 'status' => $delivery->status->value]], 202);
    }

    /** DELETE /api/hooks/{hook} — Unsubscribe (Soft-Delete) → 204. */
    public function destroy(Request $request, WebhookEndpoint $hook): JsonResponse {
        $this->assertOwned($hook);

        $hook->delete();

        return response()->json([], 204);
    }

    // --- intern -----------------------------------------------------------

    /** @return array<string, mixed> */
    private function present(WebhookEndpoint $hook): array {
        return [
            'id' => $hook->sqid,
            'label' => $hook->label,
            'target_url' => $hook->url,
            'events' => $hook->events,
            'active' => (bool) $hook->active,
            'secret_preview' => $hook->secretPreview(),
            'created_at' => $hook->created_at?->toIso8601String(),
        ];
    }

    private function organizationId(): int {
        return $this->currentOrganization()->id;
    }

    private function assertOwned(WebhookEndpoint $hook): void {
        abort_unless((int) $hook->organization_id === $this->organizationId(), 404);
    }
}
