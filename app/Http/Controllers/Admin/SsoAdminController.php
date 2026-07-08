<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Organization, ScimGroup, ScimToken, Team, User};
use App\Services\Scim\ScimGroupService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Verwaltung der SSO-/Verzeichnisdienst-Anbindung (Feature 057, MVP-121):
 * SCIM-Bearer-Token je Organisation ausstellen und widerrufen. Der Klartext wird
 * genau einmal nach der Ausstellung angezeigt (danach nur noch der Hash). Der
 * Endpunkt-Zugang ist Enterprise-gegatet (config/plans.php: `module.sso`).
 */
class SsoAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.sso.index', [
            'tokens' => ScimToken::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('id')
                ->get(),
            'groups' => ScimGroup::query()
                ->where('organization_id', $organization->id)
                ->orderBy('display_name')
                ->get(),
            'teams' => Team::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'scimBaseUrl' => url('/scim/v2'),
            'issuedToken' => session('scim_issued_token'),
        ]);
    }

    /** Stellt ein neues SCIM-Token aus; der Klartext wird einmalig geflasht. */
    public function issueToken(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        [$token, $plain] = ScimToken::issue($organization->id, (string) $data['label'], (int) $admin->id);
        $token->audit('scim.token_issued', ['by_user_id' => (int) $admin->id, 'label' => $token->label]);

        return back()->with('scim_issued_token', $plain)->with('success', __('sso.flash.token_issued'));
    }

    /** Widerruft ein SCIM-Token (sofort ungültig). */
    public function revokeToken(string $token): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(ScimToken::class, $token);
        $model = $decoded !== null
            ? ScimToken::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof ScimToken, 404);

        if ($model->revoked_at === null) {
            $model->forceFill(['revoked_at' => Carbon::now()])->save();
            $model->audit('scim.token_revoked', ['by_user_id' => (int) $admin->id, 'label' => $model->label]);
        }

        return back()->with('success', __('sso.flash.token_revoked'));
    }

    /**
     * Ordnet eine SCIM-Gruppe einem Team zu (oder löst die Zuordnung) — der
     * bewusste Admin-Schritt, der die Mitgliederprojektion nach `team_user`
     * erlaubt. SCIM selbst vergibt weiterhin nie ein Team/Rollen. Sqid statt
     * roher IDs, alle Referenzen strikt org-gescopt.
     */
    public function mapGroupTeam(Request $request, string $group): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decodedGroup = app(SqidEncoder::class)->decode(ScimGroup::class, $group);
        $model = $decodedGroup !== null
            ? ScimGroup::query()->whereKey($decodedGroup)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($model instanceof ScimGroup, 404);

        $teamSqid = trim((string) $request->input('team', ''));
        $team = null;
        if ($teamSqid !== '') {
            $decodedTeam = app(SqidEncoder::class)->decode(Team::class, $teamSqid);
            $team = $decodedTeam !== null
                ? Team::query()->whereKey($decodedTeam)->where('organization_id', $organization->id)->first()
                : null;
            abort_unless($team instanceof Team, 404);
        }

        app(ScimGroupService::class)->mapToTeam($model, $team);
        $model->audit('scim.group_mapped', [
            'by_user_id' => (int) $admin->id,
            'group' => $model->display_name,
            'team_id' => $team?->id,
        ]);

        return back()->with('success', __('sso.flash.group_mapped'));
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
