<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainLifecycleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Enums\Domain\{DomainProviderCommandStatus, DomainRenewalMode};
use App\Http\Controllers\Controller;
use App\Models\Domain\{DomainProjection, DomainProviderCommand};
use App\Services\Domain\DomainLifecycleService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Renewal, Transfer und Transferlock (Feature 083, MVP-390) als getrennte,
 * berechtigte Aktionen.
 */
class DomainLifecycleController extends Controller {
    public function renewalMode(Request $request, DomainProjection $domain, DomainLifecycleService $service): RedirectResponse {
        Gate::authorize('manageRenewal', $domain);

        $data = $request->validate(['renewal_mode' => ['required', \Illuminate\Validation\Rule::enum(DomainRenewalMode::class)]]);
        $command = $service->setRenewalMode($domain, DomainRenewalMode::from($data['renewal_mode']), ($request->user() ?? abort(401)));

        return $this->outcome($command, 'domain.flash.renewal_mode_set');
    }

    public function renew(Request $request, DomainProjection $domain, DomainLifecycleService $service): RedirectResponse {
        Gate::authorize('manageRenewal', $domain);

        $data = $request->validate(['period' => ['required', 'integer', 'min:1', 'max:10']]);
        $command = $service->renewNow($domain, (int) $data['period'], ($request->user() ?? abort(401)));

        return back()->with($command->status->isTerminal() && $command->status->value === 'confirmed' ? 'success' : 'info', __('domain.flash.renew_requested'));
    }

    public function transferLock(Request $request, DomainProjection $domain, DomainLifecycleService $service): RedirectResponse {
        Gate::authorize('manageTransfer', $domain);

        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $command = $service->setTransferLock($domain, (bool) $data['locked'], ($request->user() ?? abort(401)));

        return $this->outcome($command, 'domain.flash.transferlock_set');
    }

    /** Erfolgsmeldung nur bei bestätigtem Befehl; ein unklarer Ausgang sagt, dass der Sync-Lauf ihn abgleicht (MVP-1614). */
    private function outcome(DomainProviderCommand $command, string $successKey): RedirectResponse {
        return $command->status === DomainProviderCommandStatus::Confirmed
            ? back()->with('success', __($successKey))
            : back()->with('warning', __('domain.flash.command_' . $command->status->value));
    }

    public function transferIn(Request $request, DomainProjection $domain, DomainLifecycleService $service): RedirectResponse {
        Gate::authorize('manageTransfer', $domain);

        $data = $request->validate(['auth_code' => ['required', 'string', 'max:190']]);
        $service->startTransferIn($domain, $data['auth_code'], ($request->user() ?? abort(401)));

        return back()->with('success', __('domain.flash.transfer_started'));
    }
}
