<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginPolicyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Auth\LoginPolicyMode;
use App\Enums\Security\SecurityEventType;
use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateLoginPolicyRequest;
use App\Jobs\Security\ResolveLoginNetworksJob;
use App\Models\Auth\{OrganizationLoginPolicy, SecurityEvent, UserKnownDevice};
use App\Models\Platform\{Organization, User};
use App\Services\Auth\LoginPolicy\{DeviceApprovalService, LoginHours, LoginNetworks, LoginPolicyGate, OrganizationLockdownService};
use App\Support\Auth\RecentAuthentication;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Hash};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anmelderichtlinie der Organisation (Feature 205, MVP-1654–1657):
 * Zeitfenster, Netze, Passkey-Pflicht, Gerätefreigaben und Notaus.
 *
 * Alles gilt der gebundenen Organisation. Den Notaus löst nur aus, wer ihr
 * angehört (eine Entscheidung der Organisation, nicht des Plattformbetriebs)
 * und sich erneut ausweist; der Plattformbetrieb hebt über die
 * Organisationsverwaltung auf ({@see liftForOrganization()}).
 */
class LoginPolicyController extends Controller {
    use RequiresPlatformOperator;
    use ResolvesCurrentOrganization;

    /** Protokoll „nur protokollieren“: eine Woche. */
    private const int LOG_DAYS = 7;

    private const int LOG_LIMIT = 200;

    public function __construct(
        private readonly DeviceApprovalService $devices,
        private readonly LoginNetworks $networks,
        private readonly OrganizationLockdownService $lockdown,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::any([Permission::AuthPolicyManage->value, Permission::AuthLockdown->value]), Response::HTTP_FORBIDDEN);
        $organization = $this->currentOrganization();
        $policy = $this->policy($organization);
        $canManage = Gate::allows(Permission::AuthPolicyManage->value);

        $devices = $canManage
            ? $this->devices->devicesOf($organization->id)->with(['user:id,name,email', 'approver:id,name', 'rejecter:id,name'])->orderByDesc('approval_requested_at')->get()
            : collect();

        $events = $canManage
            ? SecurityEvent::query()
                ->where('organization_id', $organization->id)
                ->whereIn('event', [SecurityEventType::LoginPolicyLogged->value, SecurityEventType::LoginPolicyDenied->value])
                ->where('occurred_at', '>=', now()->subDays(self::LOG_DAYS))
                ->with('user:id,name,email')
                ->orderByDesc('occurred_at')
                ->limit(self::LOG_LIMIT)
                ->get()
            : collect();

        $clientIp = LoginNetworks::clientIp($request->ip());

        return view('admin.login-policy.index', [
            'organization' => $organization,
            'policy' => $policy,
            'canManage' => $canManage,
            'canLockdown' => Gate::allows(Permission::AuthLockdown->value),
            'pendingDevices' => $devices->whereNull('approved_at')->whereNull('rejected_at')->values(),
            'approvedDevices' => $devices->whereNotNull('approved_at')->values(),
            'rejectedDevices' => $devices->whereNotNull('rejected_at')->values(),
            'events' => $events,
            'resolvedHosts' => $this->networks->displayHosts($organization->id),
            'resolvedAt' => $this->networks->resolvedAt($organization->id),
            'clientIp' => $clientIp,
            // Eigene Adresse privat/Loopback: der Proxy reicht die Client-Adresse nicht durch (N8).
            'clientIpInternal' => $clientIp !== null && ! LoginNetworks::isPublic($clientIp),
            'clientInNetworks' => $policy !== null && $this->networks->contains($organization->id, $policy->networkEntries(), $clientIp),
            'roleLabels' => $this->roleLabels(),
        ]);
    }

    /** Dialog: Zeiten, Netze und Passkey-Pflicht bearbeiten. */
    public function edit(): View {
        Gate::authorize(Permission::AuthPolicyManage->value);
        $policy = $this->policy($this->currentOrganization());

        return view('admin.login-policy._form_dialog', [
            'policy' => $policy ?? new OrganizationLoginPolicy,
            'hourRoleOptions' => array_intersect_key($this->roleLabels(), array_flip(UpdateLoginPolicyRequest::hourRoleOptions())),
            'passkeyRoleOptions' => array_intersect_key($this->roleLabels(), array_flip(UpdateLoginPolicyRequest::passkeyRoleOptions())),
        ]);
    }

    public function update(UpdateLoginPolicyRequest $request): RedirectResponse {
        $organization = $this->currentOrganization();
        $actor = $this->authUser();

        $policy = $this->policy($organization) ?? new OrganizationLoginPolicy;
        if (! $policy->exists) {
            $policy->forceFill(['organization_id' => $organization->id, 'created_by' => $actor->id]);
        }

        $networks = $request->networkEntries();
        $policy->forceFill([
            'hours_mode' => $request->enum('hours_mode', LoginPolicyMode::class) ?? LoginPolicyMode::Off,
            'hours' => LoginHours::normalize((array) $request->input('hours', [])),
            'hours_roles' => array_values(array_unique(array_map('strval', (array) $request->input('hours_roles', [])))),
            'network_mode' => $request->enum('network_mode', LoginPolicyMode::class) ?? LoginPolicyMode::Off,
            'networks' => $networks,
            'passkey_roles' => array_values(array_unique(array_map('strval', (array) $request->input('passkey_roles', [])))),
            'updated_by' => $actor->id,
        ])->save();

        // DNS-Namen nach der Antwort auflösen (nie in dieser Anfrage), danach übernimmt der Scheduler (M3).
        ResolveLoginNetworksJob::dispatch((int) $organization->id)->afterResponse();

        return redirect()->route('admin.login-policy.index')->with('success', __('login_policy.flash.saved'));
    }

    public function approveDevice(string $deviceSqid): RedirectResponse {
        Gate::authorize(Permission::AuthPolicyManage->value);
        $device = $this->device($deviceSqid);
        $actor = $this->authUser();

        // Vier Augen: das eigene Gerät gibt jemand anderes frei.
        if ((int) $device->user_id === (int) $actor->id) {
            return redirect()->route('admin.login-policy.index')->withErrors(['device' => __('login_policy.error.own_device')]);
        }

        $this->devices->approve($device, $actor);

        return redirect()->route('admin.login-policy.index')->with('success', __('login_policy.flash.device_approved'));
    }

    /** Anfrage ablehnen bzw. Freigabe entziehen: das Gerät bleibt als abgelehnt stehen und fragt nicht erneut. */
    public function rejectDevice(string $deviceSqid): RedirectResponse {
        Gate::authorize(Permission::AuthPolicyManage->value);
        $device = $this->device($deviceSqid);
        $wasApproved = $device->approved_at !== null;
        $this->devices->reject($device, $this->authUser());

        return redirect()->route('admin.login-policy.index')
            ->with('success', __($wasApproved ? 'login_policy.flash.device_revoked' : 'login_policy.flash.device_rejected'));
    }

    /** Abgelehntes Gerät vergessen: die nächste Anmeldung von dort darf wieder anfragen. */
    public function forgetDevice(string $deviceSqid): RedirectResponse {
        Gate::authorize(Permission::AuthPolicyManage->value);
        $device = $this->device($deviceSqid);
        if ($device->rejected_at === null) {
            return redirect()->route('admin.login-policy.index')->withErrors(['device' => __('login_policy.error.forget_only_rejected')]);
        }
        $this->devices->forget($device);

        return redirect()->route('admin.login-policy.index')->with('success', __('login_policy.flash.device_forgotten'));
    }

    /** Dialog: Notaus mit Grund und erneuter Bestätigung. */
    public function lockdownDialog(): View {
        Gate::authorize(Permission::AuthLockdown->value);
        $this->assertMemberOf($this->currentOrganization());

        return view('admin.login-policy._lockdown_dialog', [
            'needsPassword' => ! RecentAuthentication::isRecent(request()),
        ]);
    }

    public function engageLockdown(Request $request): RedirectResponse {
        Gate::authorize(Permission::AuthLockdown->value);
        $organization = $this->currentOrganization();
        $actor = $this->authUser();
        $this->assertMemberOf($organization);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:' . OrganizationLockdownService::REASON_MAX],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);
        $this->confirmIdentity($request, $actor);

        if ($this->policy($organization)?->isLockedDown() === true) {
            return redirect()->route('admin.login-policy.index')->withErrors(['reason' => __('login_policy.error.already_locked')]);
        }

        $counts = $this->lockdown->engage($organization, $actor, (string) $data['reason'], $request->session()->getId());
        // Die auslösende Sitzung läuft weiter: Anmeldestempel nach dem Notaus.
        LoginPolicyGate::stampSession($request);

        return redirect()->route('admin.login-policy.index')->with('success', __('login_policy.flash.lockdown_engaged', $counts));
    }

    public function liftLockdown(Request $request): RedirectResponse {
        Gate::authorize(Permission::AuthLockdown->value);
        $organization = $this->currentOrganization();
        $request->validate(['current_password' => ['nullable', 'string', 'max:255']]);
        $this->confirmIdentity($request, $this->authUser());

        $this->lockdown->lift($organization, $this->authUser());

        return redirect()->route('admin.login-policy.index')->with('success', __('login_policy.flash.lockdown_lifted'));
    }

    /** Plattformbetrieb hebt den Notaus einer Organisation auf (Organisationsverwaltung, mit `reauth`). */
    public function liftForOrganization(Organization $organization): RedirectResponse {
        $this->assertPlatformOperator();

        $this->lockdown->lift($organization, $this->authUser());

        return redirect()->toList('admin.organizations.index')->with('success', __('login_policy.flash.lockdown_lifted_platform', ['name' => $organization->name]));
    }

    private function policy(Organization $organization): ?OrganizationLoginPolicy {
        $policy = OrganizationLoginPolicy::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->first();

        return $policy instanceof OrganizationLoginPolicy ? $policy : null;
    }

    /** Gerät eines Mitglieds der gebundenen Organisation — fremde Geräte gibt es hier nicht (404). */
    private function device(string $deviceSqid): UserKnownDevice {
        $id = Sqid::decodeOrAbort(UserKnownDevice::class, $deviceSqid);
        $device = $this->devices->devicesOf($this->currentOrganization()->id)->whereKey($id)->first();
        abort_unless($device instanceof UserKnownDevice, Response::HTTP_NOT_FOUND);

        return $device;
    }

    /** Den Notaus löst nur aus, wer der Organisation angehört. */
    private function assertMemberOf(Organization $organization): void {
        abort_unless((int) $this->authUser()->organization_id === (int) $organization->id, Response::HTTP_FORBIDDEN);
    }

    /**
     * Erneute Anmeldung: frische Anmeldung bzw. Passwortbestätigung in der
     * Sitzung oder das Passwort im Formular. Ohne nutzbares Passwort (SSO)
     * bleibt nur die frische Anmeldung.
     */
    private function confirmIdentity(Request $request, User $user): void {
        if (RecentAuthentication::isRecent($request)) {
            return;
        }

        $password = (string) $request->input('current_password', '');
        if ($password !== '' && $user->is_new_system && Hash::check($password, (string) $user->getAuthPassword())) {
            RecentAuthentication::confirm($request);

            return;
        }

        throw ValidationException::withMessages(['current_password' => __('login_policy.error.reauth')]);
    }

    /** @return array<string, string> */
    private function roleLabels(): array {
        $labels = [];
        foreach (UserRole::cases() as $role) {
            if ($role !== UserRole::Kunde) {
                $labels[$role->value] = $role->label();
            }
        }

        return $labels;
    }
}
