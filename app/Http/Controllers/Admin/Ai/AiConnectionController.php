<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Enums\Ai\{AiConnectionStatus, AiFamily, AiProviderType};
use App\Http\Controllers\Controller;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection, AiUsagePeriod};
use App\Services\Ai\{AiCapabilityRegistry, AiConnectionTester};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Admin-Seite „KI-Dienste" (Feature 025, MVP-400): Provider-Verbindungen
 * anlegen, testen, sperren und rotieren; dazu Capability-Matrix und
 * Monatsverbrauch. Alle Aktionen sind auditiert (Auditable am Model),
 * der API-Schlüssel verlässt den Server nie.
 */
class AiConnectionController extends Controller {
    public function index(AiCapabilityRegistry $registry): View {
        Gate::authorize('viewAny', AiProviderConnection::class);

        $connections = AiProviderConnection::query()->orderBy('name')->get();
        $settings = AiCapabilitySetting::query()->get()->keyBy('capability');
        $usage = AiUsagePeriod::query()
            ->where('period', Carbon::now()->format('Y-m'))
            ->get()
            ->keyBy(static fn (AiUsagePeriod $u): string => $u->family->value);

        return view('admin.ai.index', [
            'connections' => $connections,
            'capabilities' => $registry->all(),
            'settings' => $settings,
            'usage' => $usage,
            'canManage' => Gate::allows('create', AiProviderConnection::class),
        ]);
    }

    /** Anlege-Dialog (modal-first, wird in den Modal-Host geladen). */
    public function create(): View {
        Gate::authorize('create', AiProviderConnection::class);

        return view('admin.ai._connect_dialog');
    }

    public function store(Request $request, AiConnectionTester $tester): RedirectResponse {
        Gate::authorize('create', AiProviderConnection::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'family' => ['required', 'string', 'in:' . implode(',', array_column(AiFamily::cases(), 'value'))],
            'provider' => ['required', 'string', 'in:' . implode(',', array_column(AiProviderType::cases(), 'value'))],
            'base_url' => ['nullable', 'string', 'max:500', 'url'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:120'],
            'is_local' => ['nullable', 'boolean'],
        ]);

        $provider = AiProviderType::from($data['provider']);
        $family = AiFamily::from($data['family']);

        // Familien-Zuordnung des Typs ist verbindlich (Fake darf beides).
        if ($provider->family() !== null && $provider->family() !== $family) {
            return back()->withInput()->with('error', __('ai.flash.family_mismatch'));
        }

        // Ohne Modell scheitert schon der Prüflauf — lieber hier sagen als
        // hinterher als Verbindungsfehler.
        if ($provider->requiresModel() && trim((string) ($data['model'] ?? '')) === '') {
            return back()->withInput()->withErrors(['model' => __('ai.error.model_required')]);
        }

        $connection = AiProviderConnection::create([
            'name' => $data['name'],
            'family' => $family,
            'provider' => $provider,
            'base_url' => $data['base_url'] ?? null,
            'api_key' => $data['api_key'] ?? null,
            'model' => $data['model'] ?? null,
            'is_local' => (bool) ($data['is_local'] ?? false),
            'status' => AiConnectionStatus::Draft,
            'created_by_user_id' => Auth::id(),
        ]);

        // Vollaudit 2026-07 (M35): ausgelieferte Default-Gedächtnisregeln
        // (Kundennamen-Verbot, Nominalstil) beim Einrichten der KI säen —
        // idempotent, editierbar wie manuelle Einträge.
        app(\App\Services\Ai\AiMemoryService::class)->seedDefaults(
            app('currentOrganization'),
            Auth::id() !== null ? (int) Auth::id() : null,
        );

        $ok = $tester->test($connection);

        return redirect()->route('admin.ai.index')->with(
            $ok ? 'success' : 'error',
            $ok ? __('ai.flash.connected') : __('ai.flash.preflight_failed', ['error' => (string) $connection->fresh()?->last_error]),
        );
    }

    /** Bearbeiten-Dialog: Name, Modell, Basis-URL — Zugangsdaten laufen über rotate(). */
    public function edit(AiProviderConnection $connection): View {
        Gate::authorize('update', $connection);

        return view('admin.ai._connect_dialog', ['connection' => $connection]);
    }

    /**
     * Aktualisiert die Stammdaten einer Verbindung. Ein hinterlegter
     * Verbindungsfehler wird zurückgesetzt: er beschreibt den alten Stand und
     * würde die Verbindung sonst dauerhaft aus der Routing-Auswahl halten
     * ({@see \App\Models\Concerns\HasConnectionHealth::isConnectionFailing()}).
     */
    public function update(Request $request, AiProviderConnection $connection, AiConnectionTester $tester): RedirectResponse {
        Gate::authorize('update', $connection);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:500', 'url'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        if ($connection->provider->requiresModel() && trim((string) ($data['model'] ?? '')) === '') {
            return back()->withInput()->withErrors(['model' => __('ai.error.model_required')]);
        }

        $connection->forceFill([
            'name' => $data['name'],
            'base_url' => $data['base_url'] ?? null,
            'model' => $data['model'] ?? null,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
        ])->save();

        $ok = $tester->test($connection);

        return redirect()->route('admin.ai.index')->with(
            $ok ? 'success' : 'error',
            $ok ? __('ai.flash.connection_updated') : __('ai.flash.preflight_failed', ['error' => (string) $connection->fresh()?->last_error]),
        );
    }

    public function test(AiProviderConnection $connection, AiConnectionTester $tester): RedirectResponse {
        Gate::authorize('update', $connection);

        $ok = $tester->test($connection);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? __('ai.flash.preflight_ok') : __('ai.flash.preflight_failed', ['error' => (string) $connection->fresh()?->last_error]),
        );
    }

    /** Bewusste Datenschutz-/Betriebssperre — wird nie automatisch aufgehoben. */
    public function block(AiProviderConnection $connection): RedirectResponse {
        Gate::authorize('update', $connection);

        $connection->forceFill(['status' => AiConnectionStatus::Blocked])->save();

        return back()->with('success', __('ai.flash.blocked'));
    }

    /** Entsperren führt zurück auf Entwurf — aktiv erst nach neuem Preflight. */
    public function unblock(AiProviderConnection $connection): RedirectResponse {
        Gate::authorize('update', $connection);

        $connection->forceFill(['status' => AiConnectionStatus::Draft])->save();

        return back()->with('success', __('ai.flash.unblocked'));
    }

    /** Schlüsselrotation: neuer Schlüssel + sofortiger Preflight. */
    public function rotate(Request $request, AiProviderConnection $connection, AiConnectionTester $tester): RedirectResponse {
        Gate::authorize('update', $connection);

        $data = $request->validate([
            'api_key' => ['required', 'string', 'max:2000'],
        ]);

        $connection->forceFill(['api_key' => $data['api_key']])->save();
        $ok = $tester->test($connection);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? __('ai.flash.rotated') : __('ai.flash.preflight_failed', ['error' => (string) $connection->fresh()?->last_error]),
        );
    }

    public function destroy(AiProviderConnection $connection): RedirectResponse {
        Gate::authorize('delete', $connection);

        $connection->delete();

        return redirect()->route('admin.ai.index')->with('success', __('ai.flash.deleted'));
    }
}
