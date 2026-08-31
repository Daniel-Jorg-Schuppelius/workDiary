<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SessionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AttendanceTerminal, AuditLog, LocationDeviceToken, User};
use App\Services\Auth\UserSessionInvalidator;
use App\Services\Security\SessionManagementService;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-Ansicht „Angemeldete Nutzer" (Feature 085): zeigt je Nutzer die
 * aktiven Web-/App-Sitzungen und API-Tokens der eigenen Organisation und
 * erlaubt das Fernabmelden.
 *
 * Alles ist org-gescopt (Aufrufer-Organisation); Cross-Tenant ist technisch
 * ausgeschlossen (harte Org-Prüfung vor jedem Widerruf). Widerrufe laufen über
 * {@see UserSessionInvalidator} (Session-Löschung + remember_token-Rotation),
 * nie über einen rohen DB-Delete allein — sonst käme ein „angemeldet bleiben"-
 * Nutzer zurück.
 */
class SessionController extends Controller {
    public function index(Request $request, SessionManagementService $service): View {
        Gate::authorize(Permission::SecuritySessionsView->value);

        $organization = $this->organization($request);

        return view('admin.sessions.index', [
            'overview' => $service->forOrganization($organization, $request->session()->getId()),
        ]);
    }

    /**
     * Leichtgewichtiges JSON für den Live-Refresh (nur Kennzahlen, keine
     * personenbezogenen Detaildaten) — von einem Polling-Skript in der View
     * abgefragt.
     */
    public function data(Request $request, SessionManagementService $service): JsonResponse {
        Gate::authorize(Permission::SecuritySessionsView->value);

        $organization = $this->organization($request);
        $overview = $service->forOrganization($organization, $request->session()->getId());

        return response()->json([
            'totals' => $overview['totals'],
            'available' => $overview['available'],
        ]);
    }

    /** Einzelne Web-/App-Sitzung fernabmelden. */
    /**
     * `$handle` ist ein HMAC über die Session-ID, nicht die ID selbst
     * (Sicherheitsscan 2026-08-23, S-54): sonst stünde der Session-Identifier
     * im HTML und in jeder Ziel-URL. Die Auflösung sucht ausschließlich in den
     * Sitzungen der eigenen Organisation — die Mandantengrenze liegt damit im
     * Nachschlagen und nicht in einer nachgelagerten Prüfung.
     */
    public function destroySession(Request $request, string $handle): RedirectResponse {
        Gate::authorize(Permission::SecuritySessionsRevoke->value);

        $organization = $this->organization($request);
        /** @var User $actor */
        $actor = $request->user();

        // Selbst-Aussperr-Schutz: die eigene aktuelle Sitzung wird hier nicht
        // beendet (normaler Logout dafür nutzen).
        if (hash_equals(SessionManagementService::handleFor($request->session()->getId()), $handle)) {
            return back()->withErrors(['session' => __('sessions.error.own_current_session')]);
        }

        /** @var list<int> $memberIds */
        $memberIds = array_values(array_map('intval', User::query()
            ->where('organization_id', $organization->id)
            ->pluck('id')->all()));

        $id = SessionManagementService::resolveHandle($handle, $memberIds);
        if ($id === null) {
            return back()->withErrors(['session' => __('sessions.error.session_gone')]);
        }

        $row = DB::table('sessions')->where('id', $id)->first(['id', 'user_id']);
        if ($row === null) {
            return back()->withErrors(['session' => __('sessions.error.session_gone')]);
        }

        abort_unless($this->belongsToOrg((int) $row->user_id, $organization->id), Response::HTTP_NOT_FOUND);

        DB::table('sessions')->where('id', $id)->delete();

        $this->audit($organization->id, $actor->id, 'session.revoked', (int) $row->user_id, [
            'revoked_user_id' => (int) $row->user_id,
            'by_user_id' => $actor->id,
        ]);

        return back()->with('success', __('sessions.flash.session_revoked'));
    }

    /** Alle Geräte eines Nutzers abmelden (Sitzungen + remember_token). */
    public function destroyAllForUser(Request $request, string $userSqid, UserSessionInvalidator $invalidator): RedirectResponse {
        Gate::authorize(Permission::SecuritySessionsRevoke->value);

        $organization = $this->organization($request);
        /** @var User $actor */
        $actor = $request->user();

        $userId = Sqid::decodeOrAbort(User::class, $userSqid);
        abort_unless($this->belongsToOrg($userId, $organization->id), Response::HTTP_NOT_FOUND);

        /** @var User $target */
        $target = User::query()->findOrFail($userId);

        if ($userId === (int) $actor->id) {
            // Eigene Geräte: aktuelle Sitzung behalten, alle anderen entwerten.
            $invalidator->invalidateOthers($target, $request->session()->getId());
        } else {
            $invalidator->invalidateAll($target);
        }

        $this->audit($organization->id, $actor->id, 'user.sessions.revoked_all', $userId, [
            'revoked_user_id' => $userId,
            'by_user_id' => $actor->id,
            'self' => $userId === (int) $actor->id,
        ]);

        return back()->with('success', __('sessions.flash.all_revoked', ['name' => $target->name]));
    }

    /** API-Token (Sanctum) widerrufen. */
    public function destroyToken(Request $request, string $tokenSqid): RedirectResponse {
        Gate::authorize(Permission::SecuritySessionsRevoke->value);

        $organization = $this->organization($request);
        /** @var User $actor */
        $actor = $request->user();

        $tokenId = Sqid::decodeOrAbort(PersonalAccessToken::class, $tokenSqid);

        $row = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->where('tokenable_type', User::class)
            ->first(['id', 'tokenable_id', 'name']);
        if ($row === null) {
            return back()->withErrors(['token' => __('sessions.error.token_gone')]);
        }

        abort_unless($this->belongsToOrg((int) $row->tokenable_id, $organization->id), Response::HTTP_NOT_FOUND);

        DB::table('personal_access_tokens')->where('id', $tokenId)->delete();

        $this->audit($organization->id, $actor->id, 'token.revoked', (int) $row->tokenable_id, [
            'revoked_token_id' => (int) $row->id,
            'revoked_user_id' => (int) $row->tokenable_id,
            'token_name' => (string) $row->name,
            'by_user_id' => $actor->id,
        ]);

        return back()->with('success', __('sessions.flash.token_revoked'));
    }

    /** Standort-Erfassungsgerät trennen (revoked_at setzen). */
    public function destroyLocationDevice(Request $request, string $deviceSqid): RedirectResponse {
        Gate::authorize(Permission::SecuritySessionsRevoke->value);

        $organization = $this->organization($request);
        /** @var User $actor */
        $actor = $request->user();

        $id = Sqid::decodeOrAbort(LocationDeviceToken::class, $deviceSqid);
        $row = DB::table('location_device_tokens')
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->first(['id', 'user_id', 'organization_id', 'label']);
        if ($row === null) {
            return back()->withErrors(['device' => __('sessions.error.device_gone')]);
        }

        abort_unless((int) $row->organization_id === $organization->id, Response::HTTP_NOT_FOUND);

        DB::table('location_device_tokens')->where('id', $id)->update(['revoked_at' => now()]);

        $this->audit($organization->id, $actor->id, 'device.revoked', (int) $row->user_id, [
            'revoked_device_id' => (int) $row->id,
            'revoked_user_id' => (int) $row->user_id,
            'label' => (string) $row->label,
            'by_user_id' => $actor->id,
        ]);

        return back()->with('success', __('sessions.flash.device_revoked'));
    }

    /** Stempelterminal deaktivieren (Geräteaktion, kein Nutzer-Logout). */
    public function deactivateTerminal(Request $request, string $terminalSqid): RedirectResponse {
        Gate::authorize(Permission::SecuritySessionsRevoke->value);

        $organization = $this->organization($request);
        /** @var User $actor */
        $actor = $request->user();

        $id = Sqid::decodeOrAbort(AttendanceTerminal::class, $terminalSqid);
        /** @var AttendanceTerminal|null $terminal */
        $terminal = AttendanceTerminal::query()
            ->withoutGlobalScopes()
            ->where('id', $id)
            ->where('organization_id', $organization->id)
            ->first();
        abort_if($terminal === null, Response::HTTP_NOT_FOUND);

        $terminal->update(['active' => false]);

        $this->audit($organization->id, $actor->id, 'terminal.deactivated', (int) $actor->id, [
            'terminal_id' => (int) $terminal->id,
            'terminal_name' => (string) $terminal->name,
            'by_user_id' => $actor->id,
        ]);

        return back()->with('success', __('sessions.flash.terminal_deactivated'));
    }

    private function organization(Request $request): \App\Models\Organization {
        /** @var User $actor */
        $actor = $request->user();
        $organization = $actor->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        return $organization;
    }

    private function belongsToOrg(int $userId, int $organizationId): bool {
        return User::query()
            ->where('id', $userId)
            ->where('organization_id', $organizationId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function audit(int $organizationId, int $actorId, string $event, int $subjectUserId, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $actorId,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $subjectUserId,
            'changes' => $changes,
        ]);
    }
}
