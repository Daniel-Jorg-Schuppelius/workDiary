<?php

namespace App\Http\Controllers;

use App\Services\Archive\ArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller {
    public function run(ArchiveService $service): RedirectResponse {
        /** @var \App\Models\User|null $user */
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
