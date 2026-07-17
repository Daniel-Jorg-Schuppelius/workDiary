<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessGrantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Organization, SupportAccessGrant, User};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Temporäre Supportfreigabe (Rang 64, Soll-Konzept §5): Der Kundenadmin
 * erteilt eine zeitlich begrenzte Freigabe (read_only/full) und kann sie
 * jederzeit widerrufen. Erteilen und Widerruf werden als `support.access.*`
 * auditiert und erscheinen damit automatisch in der Supportzugriffe-Sicht
 * (MVP-004) und der Datenschutz-Selbstauskunft.
 */
class SupportAccessGrantController extends Controller {
    public function index(): View {
        Gate::authorize(Permission::SupportGrantManage->value);

        $organization = $this->currentOrganization();

        $grants = SupportAccessGrant::query()
            ->where('organization_id', $organization->id)
            ->with(['grantedBy', 'grantedTo'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Kandidaten für eine gezielte Freigabe: Accounts mit Support-Rolle
        // der eigenen Organisation (offen = jeder mit user.impersonate).
        $supportUsers = User::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deactivated_at')
            ->role(\App\Enums\User\UserRole::Support->value, 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.support.grants.index', [
            'organization' => $organization,
            'grants' => $grants,
            'supportUsers' => $supportUsers,
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::SupportGrantManage->value);

        $organization = $this->currentOrganization();

        $supportUsers = User::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deactivated_at')
            ->role(\App\Enums\User\UserRole::Support->value, 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.support.grants._form_dialog', [
            'supportUsers' => $supportUsers,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::SupportGrantManage->value);

        $organization = $this->currentOrganization();

        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('granted_to_user_id')) {
            $request->merge(['granted_to_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('granted_to_user_id'))]);
        }

        $data = $request->validate([
            'scope' => ['required', 'in:' . SupportAccessGrant::SCOPE_READ_ONLY . ',' . SupportAccessGrant::SCOPE_FULL],
            'purpose' => ['required', 'string', 'max:300'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:' . (SupportAccessGrant::MAX_DURATION_DAYS * 24)],
            'granted_to_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $grant = SupportAccessGrant::query()->create([
            'organization_id' => $organization->id,
            'granted_by_user_id' => $admin->id,
            'granted_to_user_id' => $data['granted_to_user_id'] ?? null,
            'scope' => $data['scope'],
            'purpose' => $data['purpose'],
            'expires_at' => now()->addHours((int) $data['duration_hours']),
        ]);

        $grant->audit('support.access.granted', [
            'granted_by' => $admin->id,
            'granted_to' => $grant->granted_to_user_id,
            'scope' => $grant->scope,
            'expires_at' => $grant->expires_at->toIso8601String(),
            'ticket_ref' => $grant->purpose,
        ]);

        return redirect()->route('admin.support.grants.index')
            ->with('success', __('Supportfreigabe wurde erteilt (gültig bis :until).', [
                'until' => $grant->expires_at->translatedFormat('d.m.Y H:i'),
            ]));
    }

    public function revoke(Request $request, SupportAccessGrant $grant): RedirectResponse {
        Gate::authorize(Permission::SupportGrantManage->value);

        $organization = $this->currentOrganization();
        abort_unless($grant->organization_id === $organization->id, 404);

        if (! $grant->isActive()) {
            return redirect()->route('admin.support.grants.index')
                ->with('error', __('Diese Freigabe ist bereits abgelaufen oder widerrufen.'));
        }

        $grant->update([
            'revoked_at' => now(),
            'revoked_reason' => 'manual',
        ]);

        $grant->audit('support.access.revoked', [
            'revoked_by' => $request->user()?->id,
            'reason' => 'manual',
        ]);

        return redirect()->route('admin.support.grants.index')
            ->with('success', __('Supportfreigabe wurde widerrufen. Laufende Support-Sitzungen werden beim nächsten Aufruf beendet.'));
    }

    private function currentOrganization(): Organization {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        abort_unless($org instanceof Organization, 404);

        return $org;
    }
}
