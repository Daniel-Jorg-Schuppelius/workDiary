<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\KeyHandover;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveKeyHandoverRequest;
use App\Models\{Asset, KeyHandover, User};
use App\Services\KeyHandover\KeyHandoverService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class KeyHandoverController extends Controller {
    public function __construct(private readonly KeyHandoverService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', KeyHandover::class);

        $q = trim($request->string('q')->toString());
        $assetFilter = $request->integer('asset');
        $directionFilter = $request->string('direction')->toString();

        $query = KeyHandover::query()
            ->with(['asset:id,name,asset_no', 'handedBy:id,name', 'returnedTo:id,name', 'customer:id,name'])
            ->latest('occurred_at');

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('person_name', 'like', "%{$q}%")
                    ->orWhere('person_reference', 'like', "%{$q}%");
            });
        }
        if ($assetFilter > 0) {
            $query->where('asset_id', $assetFilter);
        }
        if ($directionFilter !== '' && KeyHandoverDirection::tryFrom($directionFilter) !== null) {
            $query->where('direction', $directionFilter);
        }

        $handovers = $query->paginate(25)->withQueryString();

        return view('key-handovers.index', [
            'handovers' => $handovers,
            'directionOptions' => $this->directionOptions(),
            'filters' => [
                'q' => $q,
                'asset' => $assetFilter,
                'direction' => $directionFilter,
            ],
            'canCreate' => Gate::allows('create', KeyHandover::class),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', KeyHandover::class);

        $assetId = $request->integer('asset');

        return view('key-handovers.create', [
            'directionOptions' => $this->directionOptions(),
            'presetAssetId' => $assetId > 0 ? $assetId : null,
        ]);
    }

    public function store(SaveKeyHandoverRequest $request): RedirectResponse {
        Gate::authorize('create', KeyHandover::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $data = $request->validated();
        $asset = Asset::query()->findOrFail((int) $data['asset_id']);

        $this->service->record($asset, $user, $data);

        return redirect()
            ->route('key-handovers.index', ['asset' => $asset->id])
            ->with('success', __('Schlüsselvorgang erfasst.'));
    }

    /** @return array<string, string> */
    private function directionOptions(): array {
        $opts = [];
        foreach (KeyHandoverDirection::cases() as $case) {
            $opts[$case->value] = $case->label();
        }

        return $opts;
    }
}
