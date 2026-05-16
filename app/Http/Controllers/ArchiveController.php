<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\User;
use App\Services\Archive\ArchiveService;
use App\Services\Archive\ArchiveSummaryService;
use App\Services\UI\DateRangeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArchiveController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, ArchiveSummaryService $summary): View|RedirectResponse {
        // Backward-Compat: ?from=&to= einmalig in den globalen Context.
        if ($request->filled('from') || $request->filled('to')) {
            app(DateRangeContext::class)->set(
                DateRangeContext::PRESET_CUSTOM,
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );

            return redirect()->route('archive.index', $request->except(['from', 'to']));
        }

        /** @var User $user */
        $user = Auth::user();

        $range = $this->globalDateRange();

        $data = $summary->buildIndexData(
            $request,
            $user,
            $range['from']->toDateString(),
            $range['to']->toDateString(),
        );

        return view('archive.index', $data);
    }

    public function run(ArchiveService $service): RedirectResponse {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        $result = $service->run();

        return back()->with('success', __('Archivierung abgeschlossen: :total Datensätze (Tagebuch :diary, Bereitschaft :shifts, Notdienst :assignments).', [
            'total' => $result['total'],
            'diary' => $result['diary'],
            'shifts' => $result['shifts'],
            'assignments' => $result['assignments'],
        ]));
    }
}
