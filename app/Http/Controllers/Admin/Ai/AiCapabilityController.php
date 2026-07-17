<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapabilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Http\Controllers\Controller;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Organization;
use App\Services\Ai\{AiCapabilityRegistry, AiMemoryService, AiRoutingResolver};
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Capability-Routing je Organisation (Feature 025, MVP-400): Opt-in,
 * erlaubte Verbindungen (Reihenfolge = Fallback-Kette), Default und
 * Nutzerwahl — plus Datenfluss-/Prompt-Vorschau je Capability. Die
 * Registry (config/ai.php) ist die Allowlist; hier wird nur der
 * Org-Zustand gepflegt, nie die Registrierung selbst.
 */
class AiCapabilityController extends Controller {
    /** Routing-Dialog je Capability (modal-first). */
    public function edit(string $capability, AiCapabilityRegistry $registry): View {
        Gate::authorize('viewAny', AiProviderConnection::class);

        $definition = $registry->get($capability);
        $setting = AiCapabilitySetting::query()->where('capability', $capability)->first();
        $connections = AiProviderConnection::query()->orderBy('name')->get()
            ->filter(static fn (AiProviderConnection $c): bool => $c->supportsVerb($definition->verb))
            ->values();

        return view('admin.ai._capability_dialog', [
            'definition' => $definition,
            'setting' => $setting,
            'connections' => $connections,
        ]);
    }

    public function update(Request $request, string $capability, AiCapabilityRegistry $registry): RedirectResponse {
        Gate::authorize('create', AiProviderConnection::class);

        $definition = $registry->get($capability); // wirft bei unbekanntem Key

        // Sqid-Inputs dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('default_connection_id')) {
            $request->merge(['default_connection_id' => Sqid::decodeOrNumeric(AiProviderConnection::class, $request->input('default_connection_id'))]);
        }
        $allowedIds = $request->input('allowed_connection_ids');
        if (is_array($allowedIds)) {
            $request->merge(['allowed_connection_ids' => array_values(array_filter(array_map(
                static fn($v) => Sqid::decodeOrNumeric(AiProviderConnection::class, $v),
                $allowedIds,
            )))]);
        }

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allow_user_choice' => ['nullable', 'boolean'],
            'default_connection_id' => ['nullable', 'integer'],
            'allowed_connection_ids' => ['nullable', 'array'],
            'allowed_connection_ids.*' => ['integer'],
        ]);

        // Nur eigene, zum Verb passende Verbindungen sind zulässig.
        $valid = AiProviderConnection::query()
            ->whereIn('id', array_map('intval', (array) ($data['allowed_connection_ids'] ?? [])))
            ->get()
            ->filter(static fn (AiProviderConnection $c): bool => $c->supportsVerb($definition->verb))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $default = isset($data['default_connection_id']) ? (int) $data['default_connection_id'] : null;
        if ($default !== null && ! in_array($default, $valid, true)) {
            $default = null;
        }

        AiCapabilitySetting::query()->updateOrCreate(
            ['capability' => $capability],
            [
                'enabled' => (bool) ($data['enabled'] ?? false),
                'allow_user_choice' => (bool) ($data['allow_user_choice'] ?? false),
                'allowed_connection_ids' => $valid,
                'default_connection_id' => $default,
            ],
        );

        return redirect()->route('admin.ai.index')->with('success', __('ai.flash.capability_saved'));
    }

    /**
     * Datenfluss-/Prompt-Vorschau (Feature 025, Leitprinzip 3): zeigt,
     * welche Datenklassen und Gedächtnis-Scopes an welche Verbindung
     * gehen würden — ohne einen Provider aufzurufen.
     */
    public function preview(
        string $capability,
        AiCapabilityRegistry $registry,
        AiRoutingResolver $resolver,
        AiMemoryService $memory,
    ): View {
        Gate::authorize('viewAny', AiProviderConnection::class);

        $definition = $registry->get($capability);
        /** @var Organization $organization */
        $organization = app('currentOrganization');

        $candidates = [];
        $unavailableReason = null;
        try {
            $candidates = $resolver->resolveCandidates($organization, $capability);
        } catch (AiUnavailableException $e) {
            $unavailableReason = $e->reason;
        }

        return view('admin.ai._preview_dialog', [
            'definition' => $definition,
            'candidates' => $candidates,
            'unavailableReason' => $unavailableReason,
            'cloudAllowed' => $resolver->cloudAllowed($organization, $definition),
            'memoryCounts' => [
                'glossary' => count($memory->glossaryFor($organization, $capability)),
                'style_rules' => count($memory->styleRulesFor($organization, $capability)),
                'examples' => count($memory->examplesFor($organization, $capability)),
            ],
        ]);
    }
}
