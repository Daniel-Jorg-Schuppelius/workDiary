<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Archive\ArchiveService;
use App\Services\Archive\ArchiveSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArchiveController extends Controller {
    public function index(Request $request, ArchiveSummaryService $summary): View {
        /** @var User $user */
        $user = Auth::user();

        return view('archive.index', $summary->buildIndexData($request, $user));
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
