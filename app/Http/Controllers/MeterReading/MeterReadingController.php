<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\MeterReading;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMeterReadingRequest;
use App\Models\{Asset, MeterReading, User};
use App\Services\MeterReading\MeterReadingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class MeterReadingController extends Controller {
    public function __construct(private readonly MeterReadingService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', MeterReading::class);

        $assetFilter = $request->integer('asset');

        $query = MeterReading::query()
            ->with(['asset:id,name,asset_no', 'readBy:id,name'])
            ->latest('read_at');

        if ($assetFilter > 0) {
            $query->where('asset_id', $assetFilter);
        }

        $readings = $query->paginate(25)->withQueryString();

        return view('meter-readings.index', [
            'readings' => $readings,
            'filters' => [
                'asset' => $assetFilter,
            ],
            'canCreate' => Gate::allows('create', MeterReading::class),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', MeterReading::class);

        $assetId = $request->integer('asset');

        return view('meter-readings.create', [
            'presetAssetId' => $assetId > 0 ? $assetId : null,
        ]);
    }

    public function store(SaveMeterReadingRequest $request): RedirectResponse {
        Gate::authorize('create', MeterReading::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $data = $request->validated();
        $asset = Asset::query()->findOrFail((int) $data['asset_id']);

        try {
            $this->service->record($asset, $user, $data);
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['value' => __($e->getMessage())]);
        }

        return redirect()
            ->route('meter-readings.index', ['asset' => $asset->sqid])
            ->with('success', __('Zählerstand erfasst.'));
    }
}
