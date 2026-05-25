<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * MVP-005: Datenschutzseite für Org-Admins. Aggregiert Status,
 * Datenkategorien, aktive Sessions, API-Tokens, Mandantenexporte und
 * Supportzugriffe auf einer Seite und bietet Widerruf-Aktionen mit
 * Audit-Spur.
 *
 * Bewusst out-of-scope dieser Iteration: PDF-Bericht (separate Route
 * vorbereitet, aber Renderer folgt) und Integrationen-Detailansicht.
 */
class PrivacyController extends Controller {
    public function index(Request $request): View {
        Gate::authorize(Permission::PrivacyView->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $memberIds = User::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $sessions = collect();
        if ($user->can(Permission::PrivacySessionsView->value) && Schema::hasTable('sessions')) {
            $sessions = DB::table('sessions')
                ->whereIn('user_id', $memberIds)
                ->orderByDesc('last_activity')
                ->limit(50)
                ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
        }

        $tokens = collect();
        if ($user->can(Permission::PrivacyTokensView->value) && Schema::hasTable('personal_access_tokens')) {
            $tokens = DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $memberIds)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);
        }

        // §3.6 Mandantenexporte: AuditLog-Events mit Präfix tenant.export.*
        $exports = collect();
        if ($user->can(Permission::PrivacyExportsView->value)) {
            $exports = AuditLog::query()
                ->where('organization_id', $organization->id)
                ->where('event', 'like', 'tenant.export.%')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'user_id', 'event', 'changes', 'created_at']);
        }

        // §3.7 Supportzugriffe: AuditLog-Events mit Präfix support.*
        // (z. B. support.access.granted/revoked, support.reportGenerated).
        $supportAccesses = collect();
        if ($user->can(Permission::PrivacySupportView->value)) {
            $supportAccesses = AuditLog::query()
                ->where('organization_id', $organization->id)
                ->where('event', 'like', 'support.%')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'user_id', 'event', 'changes', 'created_at']);
        }

        $auditActorIds = $exports->pluck('user_id')
            ->merge($supportAccesses->pluck('user_id'))
            ->filter()
            ->unique();

        return view('admin.privacy.index', [
            'organization' => $organization,
            'memberCount' => $memberIds->count(),
            'sessions' => $sessions,
            'tokens' => $tokens,
            'sessionUsers' => User::query()->whereIn('id', $sessions->pluck('user_id')->filter()->unique())->get()->keyBy('id'),
            'tokenUsers' => User::query()->whereIn('id', $tokens->pluck('tokenable_id')->filter()->unique())->get()->keyBy('id'),
            'exports' => $exports,
            'supportAccesses' => $supportAccesses,
            'auditActors' => User::query()->whereIn('id', $auditActorIds)->get()->keyBy('id'),
            'categories' => (array) config('privacy.categories', []),
            'operatingMode' => (string) config('privacy.operating_mode', 'on_premise'),
            'dpaUrl' => config('privacy.dpa_document_url'),
            'canRevokeSessions' => $user->can(Permission::PrivacySessionsRevoke->value),
            'canRevokeTokens' => $user->can(Permission::PrivacyTokensRevoke->value),
            'canViewExports' => $user->can(Permission::PrivacyExportsView->value),
            'canViewSupport' => $user->can(Permission::PrivacySupportView->value),
        ]);
    }

    public function destroySession(Request $request, string $id): RedirectResponse {
        Gate::authorize(Permission::PrivacySessionsRevoke->value);

        /** @var User $actor */
        $actor = $request->user();
        $organization = $actor->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $row = DB::table('sessions')->where('id', $id)->first(['id', 'user_id']);
        if ($row === null) {
            return back()->withErrors(['session' => __('Session existiert nicht (mehr).')]);
        }

        $belongsToOrg = User::query()
            ->where('id', $row->user_id)
            ->where('organization_id', $organization->id)
            ->exists();
        abort_unless($belongsToOrg, Response::HTTP_NOT_FOUND);

        DB::table('sessions')->where('id', $id)->delete();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'session.revoked',
            'auditable_type' => User::class,
            'auditable_id' => (int) $row->user_id,
            'changes' => [
                'revoked_user_id' => (int) $row->user_id,
                'by_user_id' => $actor->id,
            ],
        ]);

        return back()->with('success', __('Session widerrufen.'));
    }

    public function destroyToken(Request $request, int $id): RedirectResponse {
        Gate::authorize(Permission::PrivacyTokensRevoke->value);

        /** @var User $actor */
        $actor = $request->user();
        $organization = $actor->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $row = DB::table('personal_access_tokens')
            ->where('id', $id)
            ->where('tokenable_type', User::class)
            ->first(['id', 'tokenable_id', 'name']);
        if ($row === null) {
            return back()->withErrors(['token' => __('Token existiert nicht (mehr).')]);
        }

        $belongsToOrg = User::query()
            ->where('id', $row->tokenable_id)
            ->where('organization_id', $organization->id)
            ->exists();
        abort_unless($belongsToOrg, Response::HTTP_NOT_FOUND);

        DB::table('personal_access_tokens')->where('id', $id)->delete();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'token.revoked',
            'auditable_type' => User::class,
            'auditable_id' => (int) $row->tokenable_id,
            'changes' => [
                'revoked_token_id' => (int) $row->id,
                'revoked_user_id' => (int) $row->tokenable_id,
                'token_name' => (string) $row->name,
                'by_user_id' => $actor->id,
            ],
        ]);

        return back()->with('success', __('API-Token widerrufen.'));
    }
}
