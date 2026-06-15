<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpointController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Integration\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Models\Integration\WebhookEndpoint;
use App\Services\Integration\WebhookDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin-UI für ausgehende Webhook-Endpunkte (Feature 008): Listenseite mit
 * Zustellprotokoll + Modal-CRUD. Der Signing-Key (secret) wird verschlüsselt
 * abgelegt und NUR einmalig bei Anlage/Rotation im Klartext angezeigt (Flash);
 * danach nie wieder. Pflege durch Admin (webhook.manage).
 */
class WebhookEndpointController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        $endpoints = WebhookEndpoint::query()
            ->with(['deliveries' => fn($q) => $q->latest()->limit(5)])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.webhooks.index', [
            'endpoints' => $endpoints,
            'events' => WebhookEvent::cases(),
            'canManage' => Gate::allows('create', WebhookEndpoint::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', WebhookEndpoint::class);

        return view('admin.webhooks._form_dialog', [
            'endpoint' => new WebhookEndpoint(['active' => true, 'events' => []]),
            'events' => WebhookEvent::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', WebhookEndpoint::class);

        $data = $this->validated($request);
        $secret = WebhookEndpoint::generateSecret();

        $endpoint = new WebhookEndpoint($data);
        $endpoint->secret = $secret;
        $endpoint->created_by_user_id = $this->authUser()->id;
        $endpoint->save();

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('integration.webhook.flash.created'))
            // Einmalige Klartext-Anzeige des Signing-Keys (nur in dieser Session).
            ->with('webhook_secret', $secret);
    }

    public function edit(WebhookEndpoint $webhook): View {
        Gate::authorize('update', $webhook);

        return view('admin.webhooks._form_dialog', [
            'endpoint' => $webhook,
            'events' => WebhookEvent::cases(),
        ]);
    }

    public function update(Request $request, WebhookEndpoint $webhook): RedirectResponse {
        Gate::authorize('update', $webhook);

        $data = $this->validated($request);

        // Reaktivierung eines auto-deaktivierten Endpunkts: Fehlerzähler reset.
        if (($data['active'] ?? false) && $webhook->disabled_at !== null) {
            $webhook->disabled_at = null;
            $webhook->consecutive_failures = 0;
        }

        $webhook->fill($data)->save();

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('integration.webhook.flash.updated'));
    }

    public function rotateSecret(WebhookEndpoint $webhook): RedirectResponse {
        Gate::authorize('update', $webhook);

        $secret = WebhookEndpoint::generateSecret();
        $webhook->secret = $secret;
        $webhook->save();

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('integration.webhook.flash.secret_rotated'))
            ->with('webhook_secret', $secret);
    }

    public function test(WebhookEndpoint $webhook, WebhookDispatchService $service): RedirectResponse {
        Gate::authorize('update', $webhook);

        $service->sendTest($webhook);

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('integration.webhook.flash.test_sent'));
    }

    public function destroy(WebhookEndpoint $webhook): RedirectResponse {
        Gate::authorize('delete', $webhook);

        $webhook->delete();

        return redirect()->route('admin.webhooks.index')
            ->with('success', __('integration.webhook.flash.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048', 'starts_with:https://,http://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::enum(WebhookEvent::class)],
            'active' => ['required', 'boolean'],
        ]);

        $data['events'] = array_values(array_unique((array) $data['events']));
        $data['active'] = (bool) $data['active'];

        return $data;
    }
}
