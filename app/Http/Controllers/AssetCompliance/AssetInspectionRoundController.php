<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionRoundController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionResult;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionRound, AssetInspectionRoundItem};
use App\Models\Customer\Customer;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetCompliance\{AssetComplianceService, InspectionRoundService};
use App\Support\{ErrorText, Sqid};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Prüfmittelrunden (Feature 075, MVP-899): Soll-Liste, Scan, Schnellerfassung. */
class AssetInspectionRoundController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly InspectionRoundService $rounds,
        private readonly AssetComplianceService $compliance,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        return view('asset-compliance.rounds.index', [
            'rounds' => AssetInspectionRound::query()->withCount(['items', 'items as done_count' => fn ($q) => $q->whereNotNull('asset_inspection_event_id')])
                ->orderByDesc('id')->paginate(25),
            'canInspect' => Gate::allows('inspect', AssetComplianceProfile::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('inspect', AssetComplianceProfile::class);

        return view('asset-compliance.rounds._form_dialog', [
            'locations' => Asset::query()->whereNotNull('location_text')->distinct()->orderBy('location_text')->pluck('location_text'),
            'categories' => Asset::query()->whereNotNull('category_code')->distinct()->orderBy('category_code')->pluck('category_code'),
            'profiles' => $this->compliance->effectiveProfiles($this->currentOrganizationId())->sortBy('name')->values(),
            'customers' => Customer::query()->whereHas('assets')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);

        $request->merge([
            'asset_compliance_profile_id' => Sqid::decodeOrNumeric(AssetComplianceProfile::class, $request->input('asset_compliance_profile_id')),
            'customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'due_until' => ['required', 'date'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'category_code' => ['nullable', 'string', 'max:60'],
            // Katalogprofile (ohne Organisation) sind zulässig.
            'asset_compliance_profile_id' => ['nullable', 'integer', Rule::in($this->compliance->effectiveProfiles($this->currentOrganizationId())->pluck('id')->all())],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
        ]);

        $actor = $request->user() ?? abort(401);
        $round = $this->rounds->open($this->currentOrganization(), $actor, $data);

        return redirect()->route('asset-compliance.rounds.show', $round)->with('success', __('inspection_round.opened', ['count' => $round->items()->count()]));
    }

    public function show(Request $request, AssetInspectionRound $round): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $round->load(['items.asset', 'items.assignment.profile', 'items.event', 'profile', 'customer']);
        $matches = collect((array) $request->session()->get('inspection_round.matches', []));

        return view('asset-compliance.rounds.show', [
            'round' => $round,
            'items' => $round->items->sortBy(fn (AssetInspectionRoundItem $i): string => ($i->isDone() ? '1' : '0') . ($i->due_on?->toDateString() ?? '9999') . $i->asset?->name)->values(),
            'matches' => $round->items->whereIn('id', $matches->all())->values(),
            'canInspect' => Gate::allows('inspect', AssetComplianceProfile::class) && $round->status->value === 'open',
        ]);
    }

    public function scan(Request $request, AssetInspectionRound $round): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:255']])['code'];

        try {
            $pending = $this->rounds->pendingForCode($round, $code);
        } catch (RuntimeException $e) {
            return redirect()->route('asset-compliance.rounds.show', $round)->with('error', ErrorText::for($e));
        }
        if ($pending->count() === 1) {
            return redirect()->route('asset-compliance.rounds.capture', [$round, $pending->first()]);
        }
        if ($pending->isEmpty()) {
            return redirect()->route('asset-compliance.rounds.show', $round)->with('warning', __('inspection_round.scan_done'));
        }

        return redirect()->route('asset-compliance.rounds.show', $round)->with('inspection_round.matches', $pending->pluck('id')->all());
    }

    public function captureForm(AssetInspectionRound $round, AssetInspectionRoundItem $item): View {
        Gate::authorize('inspect', AssetComplianceProfile::class);
        abort_unless($item->asset_inspection_round_id === $round->id, 404);

        return view('asset-compliance.rounds.capture', ['round' => $round, 'item' => $item->load(['asset', 'assignment.profile'])]);
    }

    public function capture(Request $request, AssetInspectionRound $round, AssetInspectionRoundItem $item): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);
        abort_unless($item->asset_inspection_round_id === $round->id, 404);

        $data = $request->validate([
            'result' => ['required', Rule::enum(AssetInspectionResult::class)],
            'note' => ['nullable', 'string', 'max:4000'],
            'signature_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->rounds->record($item, $request->user() ?? abort(401), $data);
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return redirect()->route('asset-compliance.rounds.show', $round)->with('success', __('inspection_round.recorded'));
    }

    public function close(Request $request, AssetInspectionRound $round): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);

        try {
            $this->rounds->close($round, $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('asset-compliance.rounds.show', $round)->with('success', __('inspection_round.closed_flash'));
    }
}
