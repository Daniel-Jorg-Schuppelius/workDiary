<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\LookupCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ManagesShiftLike {
    private function authorizeManage(): void {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($auth->canCreateEntriesForOthers(), 403);
    }

    /**
     * Gibt alle zuweisbaren Benutzer zurück.
     * Wenn der eingeloggte Nutzer keine Rechte hat, wird nur er selbst zurückgegeben.
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function assignableUsers(): \Illuminate\Support\Collection {
        /** @var User $auth */
        $auth = Auth::user();

        if ($auth->canCreateEntriesForOthers()) {
            return LookupCache::userDropdown();
        }

        return collect([$auth->only(['id', 'name'])]);
    }

    private function parseDateTime(?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return null;
        }
    }

    private function redirectAfter(Request $request, string $message, string $fallbackRoute): RedirectResponse {
        $back = $request->input('_back') ?: $fallbackRoute;

        return redirect($back)->with('success', $message);
    }
}
