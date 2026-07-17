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
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class KeyHandoverController extends Controller {
    private const ALLOWED_SORTS = ['occurred_at', 'direction', 'person_name', 'expected_return_at'];

    public function __construct(private readonly KeyHandoverService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', KeyHandover::class);

        $q = trim($request->string('q')->toString());
        $rawAsset = (string) $request->query('asset', '');
        $assetFilter = Sqid::decodeOrNumeric(Asset::class, $rawAsset, 0);
        $directionFilter = $request->string('direction')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'occurred_at';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = KeyHandover::query()
            ->with(['asset:id,name,asset_no', 'handedBy:id,name', 'returnedTo:id,name', 'customer:id,name'])
            ->orderBy($sort, $dir);

        if ($q !== '') {
            $query->search($q);
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
                'asset' => $assetFilter > 0 ? Sqid::encode(Asset::class, $assetFilter) : null,
                'direction' => $directionFilter,
            ],
            'canCreate' => Gate::allows('create', KeyHandover::class),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', KeyHandover::class);

        $rawAsset = (string) $request->query('asset', '');
        $assetId = Sqid::decodeOrNumeric(Asset::class, $rawAsset, 0);

        return view('key-handovers._form_dialog', [
            'isDialog' => true,
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
            ->route('key-handovers.index', ['asset' => $asset->sqid])
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
