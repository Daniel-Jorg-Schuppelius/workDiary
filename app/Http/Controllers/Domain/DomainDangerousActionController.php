<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDangerousActionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Domain;

use App\Http\Controllers\Controller;
use App\Models\Domain\{DomainProjection, DomainProviderCommand};
use App\Services\Domain\{DomainActionException, DomainCommandService, DomainDangerousActionService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Hochrisikoaktionen (Feature 083, MVP-390): DeleteDomain/PushDomain/
 * TradeDomain/AssignObject/Transfer-Out. JEDE verlangt die erneute Eingabe des
 * Domainnamens und legt einen Entwurf an, der eine Vier-Augen-Freigabe von
 * einer ANDEREN Person benötigt. Keine Einzelklick-/Scheduler-Ausführung.
 */
class DomainDangerousActionController extends Controller {
    public function requestAction(Request $request, DomainProjection $domain, DomainDangerousActionService $service): RedirectResponse {
        Gate::authorize('approveDangerous', $domain);

        $data = $request->validate([
            'action' => ['required', 'string', 'in:delete,push,trade,transfer_out,assign'],
            'confirmation' => ['required', 'string', 'max:253'],
            'target_user' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $command = match ($data['action']) {
                'delete' => $service->requestDelete($domain, $data['confirmation'], ($request->user() ?? abort(401))),
                'push' => $service->requestPush($domain, (string) ($data['target_user'] ?? ''), $data['confirmation'], ($request->user() ?? abort(401))),
                'trade' => $service->requestTrade($domain, $data['confirmation'], ($request->user() ?? abort(401))),
                'transfer_out' => $service->requestTransferOut($domain, $data['confirmation'], ($request->user() ?? abort(401))),
                'assign' => $service->requestAssign($domain, (string) ($data['target_user'] ?? ''), $data['confirmation'], ($request->user() ?? abort(401))),
                default => throw new RuntimeException('unknown action'),
            };
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('domain.flash.dangerous_requested', ['command' => $command->command]));
    }

    /** Vier-Augen-Freigabe + Ausführung. */
    public function approve(DomainProviderCommand $command, DomainCommandService $service, Request $request): RedirectResponse {
        $this->authorizeCommand($command);

        try {
            $service->approve($command, ($request->user() ?? abort(401)));
            $service->dispatch($command);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('domain.flash.dangerous_approved'));
    }

    public function reject(DomainProviderCommand $command): RedirectResponse {
        $this->authorizeCommand($command);

        $command->forceFill(['status' => \App\Enums\Domain\DomainProviderCommandStatus::Failed, 'last_error' => 'rejected'])->save();

        return back()->with('success', __('domain.flash.dangerous_rejected'));
    }

    private function authorizeCommand(DomainProviderCommand $command): void {
        $domain = DomainProjection::query()
            ->where('connection_id', $command->connection_id)
            ->where('external_domain', $command->target)
            ->first();

        if ($domain !== null) {
            Gate::authorize('approveDangerous', $domain);
        } else {
            Gate::authorize('approveDangerous', DomainProjection::class);
        }
    }
}
