<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceDashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\Asset\AssetBlockReason;
use App\Http\Controllers\Controller;
use App\Models\{Asset, AssetBlock, AssetBlockException};
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Asset\AssetBlockService;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Prüfmittel-Dashboard (MVP-288/289): fällige, überfällige, gesperrte und
 * eingeschränkt freigegebene Assets; Sperren und befristete
 * Ausnahmefreigaben laufen über das gemeinsame Modell (D12).
 */
class AssetComplianceDashboardController extends Controller {
    public function __construct(
        private readonly AssetComplianceService $service,
        private readonly AssetBlockService $blocks,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $assignments = AssetComplianceAssignment::query()
            ->active()
            ->with(['asset', 'profile', 'responsible'])
            ->orderBy('next_due_on')
            ->get();

        $activeBlocks = AssetBlock::query()
            ->active()
            ->with(['asset', 'exceptions'])
            ->latest('blocked_from')
            ->get();

        return view('asset-compliance.index', [
            'dueSoon' => $assignments->filter(fn ($a) => $a->isDueSoon())->values(),
            'overdue' => $assignments->filter(fn ($a) => $a->isOverdue())->values(),
            'assignments' => $assignments,
            'activeBlocks' => $activeBlocks,
            'statusByAsset' => $assignments->pluck('asset')->filter()->unique('id')
                ->mapWithKeys(fn (Asset $asset) => [$asset->id => $this->service->statusFor($asset)]),
        ]);
    }

    /** Manuelle Sperre (D12) — Grund + optionale Befristung. */
    public function block(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        abort_unless($request->user()?->can(\App\Enums\User\Permission::AssetBlockManage->value) || $request->user()?->isGlobalAdmin(), 403);

        $request->merge(['asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))]);
        $data = $request->validate([
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'reason' => ['required', Rule::enum(AssetBlockReason::class)],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
            'blocked_until' => ['nullable', 'date'],
        ]);

        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();
        $this->blocks->block(
            $asset,
            AssetBlockReason::from($data['reason']),
            $request->user(),
            $data['note'],
            null,
            isset($data['blocked_until']) ? \Illuminate\Support\Carbon::parse($data['blocked_until']) : null,
        );

        return back()->with('status', __('Asset gesperrt.'));
    }

    public function release(Request $request, AssetBlock $block): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        abort_unless($request->user()?->can(\App\Enums\User\Permission::AssetBlockManage->value) || $request->user()?->isGlobalAdmin(), 403);

        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->blocks->release($block, $request->user(), $data['note']);

        return back()->with('status', __('Sperre aufgehoben.'));
    }

    /**
     * Befristete Ausnahmefreigabe (D12): Kontext, Pflichtbegründung,
     * Frist — auditiert.
     */
    public function grantException(Request $request, AssetBlock $block): RedirectResponse {
        Gate::authorize('release', AssetComplianceProfile::class);

        $data = $request->validate([
            'context' => ['required', Rule::in(['rental', 'dispatch', 'usage'])],
            'reason_text' => ['required', 'string', 'min:20', 'max:2000'],
            'valid_until' => ['required', 'date', 'after:today'],
        ]);

        try {
            $this->blocks->grantException(
                $block,
                $request->user() ?? abort(401),
                $data['context'],
                $data['reason_text'],
                \Illuminate\Support\Carbon::parse($data['valid_until']),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason_text' => $e->getMessage()]);
        }

        return back()->with('status', __('Befristete Ausnahmefreigabe erteilt (auditiert).'));
    }

    public function revokeException(Request $request, AssetBlockException $exception): RedirectResponse {
        Gate::authorize('release', AssetComplianceProfile::class);

        $this->blocks->revokeException($exception, $request->user() ?? abort(401));

        return back()->with('status', __('Ausnahmefreigabe widerrufen.'));
    }
}
