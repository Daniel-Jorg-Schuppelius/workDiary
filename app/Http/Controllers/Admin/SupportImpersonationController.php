<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportImpersonationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{Organization, SupportAccessGrant, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Support-Impersonation (Rang 64, Soll-Konzept §5.3): Ein Support-Account
 * mit `user.impersonate` darf nur bei aktiver Kundenfreigabe
 * ({@see SupportAccessGrant}) in einen User-Account eintreten. Start/Stop
 * werden zwingend auditiert; die laufende Sitzung wird durch die Middleware
 * {@see \App\Http\Middleware\EnforceSupportImpersonation} überwacht (Banner,
 * Sperrliste, Ablauf/Widerruf beendet die Sitzung sofort).
 */
class SupportImpersonationController extends Controller {
    public const SESSION_KEY = 'support_impersonation';

    public function store(Request $request, User $user): RedirectResponse {
        Gate::authorize(Permission::UserImpersonate->value);

        /** @var User $support */
        $support = $request->user();

        // Verschachtelte Impersonation ist ausgeschlossen.
        abort_if($request->session()->has(self::SESSION_KEY), 403, __('Es läuft bereits eine Support-Sitzung.'));

        // Ziel immer org-gebunden prüfen (User hat keinen globalen OrgScope).
        $organization = $this->currentOrganization();
        abort_unless($user->organization_id === $organization->id, 404);
        abort_if($user->is($support), 403, __('Impersonation des eigenen Kontos ist nicht möglich.'));
        abort_if($user->isDeactivated(), 403, __('Dieses Konto ist deaktiviert.'));
        // Eskalationsschutz: nie in Admin-Konten eintreten.
        abort_if($user->isAdmin(), 403, __('Impersonation von Administratorkonten ist nicht zulässig.'));

        $grant = SupportAccessGrant::activeFor((int) $organization->id, (int) $support->id);
        abort_unless($grant instanceof SupportAccessGrant, 403, __('Keine aktive Supportfreigabe für diese Organisation.'));

        // Audit VOR dem Kontextwechsel — Akteur ist der Support-Account.
        $grant->audit('support.impersonation.start', [
            'target_user_id' => $user->id,
            'scope' => $grant->scope,
            'ticket_ref' => $grant->purpose,
        ]);

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'impersonator_id' => $support->id,
            'grant_id' => $grant->id,
            'started_at' => now()->getTimestamp(),
        ]);

        Auth::guard('web')->login($user);

        return redirect()->route('today.show')
            ->with('success', __('Support-Sitzung gestartet: Sie handeln jetzt als :name.', ['name' => $user->name]));
    }

    public function destroy(Request $request): RedirectResponse {
        $state = $request->session()->get(self::SESSION_KEY);
        if (! is_array($state)) {
            return redirect()->route('today.show');
        }

        $impersonator = User::query()->withoutGlobalScopes()
            ->whereKey((int) ($state['impersonator_id'] ?? 0))->first();
        $grant = SupportAccessGrant::query()->withoutGlobalScopes()
            ->whereKey((int) ($state['grant_id'] ?? 0))->first();
        $target = $request->user();

        $request->session()->forget(self::SESSION_KEY);

        if ($impersonator instanceof User && ! $impersonator->isDeactivated()) {
            Auth::guard('web')->login($impersonator);
        } else {
            Auth::guard('web')->logout();
        }
        $request->session()->regenerate();

        if ($grant instanceof SupportAccessGrant) {
            $grant->audit('support.impersonation.stop', [
                'target_user_id' => $target?->id,
                'duration_seconds' => max(0, now()->getTimestamp() - (int) ($state['started_at'] ?? now()->getTimestamp())),
                'reason' => 'manual',
            ]);
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('today.show')
            ->with('success', __('Support-Sitzung beendet.'));
    }

    private function currentOrganization(): Organization {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        abort_unless($org instanceof Organization, 404);

        return $org;
    }
}
