<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceIndexController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Enums\Contract\PriceIndexStatus;
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\Contract\{Contract, PriceIndexValue};
use App\Services\Contract\PriceIndexService;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Verbraucherpreisindex (MVP-952): lesen alle mit Vertragsrecht, freigeben,
 * verwerfen und nachtragen nur der Plattformbetreiber — die Werte gelten
 * installationsweit.
 */
class PriceIndexController extends Controller {
    use RequiresPlatformOperator;

    public function index(): View {
        Gate::authorize('viewAny', Contract::class);

        return view('contracts.price-index', [
            'values' => PriceIndexValue::query()->where('series', PriceIndexValue::SERIES_VPI)->with('approver:id,name')->orderByDesc('period_on')->paginate(24),
            'pending' => PriceIndexValue::query()->where('status', PriceIndexStatus::Pending->value)->count(),
            'canApprove' => $this->isPlatformOperator(),
        ]);
    }

    public function approve(PriceIndexValue $value, PriceIndexService $index): RedirectResponse {
        $this->assertPlatformOperator();
        try {
            $index->approve($value, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('contract.price_index.flash.approved'));
    }

    public function reject(PriceIndexValue $value, PriceIndexService $index): RedirectResponse {
        $this->assertPlatformOperator();
        try {
            $index->reject($value, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('contract.price_index.flash.rejected'));
    }

    /** Wert von Hand nachtragen (z. B. aus der Destatis-Pressemitteilung); gilt sofort als freigegeben. */
    public function store(Request $request, PriceIndexService $index): RedirectResponse {
        $this->assertPlatformOperator();
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'value' => ['required', 'numeric', 'min:1', 'max:9999'],
        ]);
        $periodOn = $data['period'] . '-01';
        ($index->find(PriceIndexValue::SERIES_VPI, $periodOn) ?? new PriceIndexValue(['series' => PriceIndexValue::SERIES_VPI, 'period_on' => $periodOn]))
            ->fill(['value' => (string) $data['value'], 'source' => 'manual', 'status' => PriceIndexStatus::Approved, 'approver_user_id' => $this->authUser()->id, 'approved_at' => now()])
            ->save();

        return back()->with('success', __('contract.price_index.flash.saved'));
    }
}
