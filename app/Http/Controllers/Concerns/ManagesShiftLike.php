<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesShiftLike.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\Platform\User;
use App\Support\{LookupCache, UrlSafety};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
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
     * @return Collection<int, User>
     */
    private function assignableUsers(): Collection {
        /** @var User $auth */
        $auth = Auth::user();

        if ($auth->canCreateEntriesForOthers()) {
            return LookupCache::userDropdown();
        }

        return collect([$auth]);
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
        $back = $this->safeBackUrl($request->input('_back'), $fallbackRoute, $request->getHost());

        return redirect($back)->with('success', $message);
    }

    /**
     * Gibt eine sichere Rücksprung-URL zurück.
     * Externe URLs (anderer Host) werden auf $fallback zurückgesetzt,
     * um Open-Redirect-Angriffe zu verhindern. Die frühere Hostprüfung ließ
     * `/\fremd.example` (Browser lesen das als `//fremd.example`) und Schemata
     * ohne Host durch; die gemeinsame Prüfung schließt beides.
     */
    private function safeBackUrl(mixed $candidate, string $fallback, string $appHost): string {
        if (! is_string($candidate) || ! UrlSafety::isSameOriginOrRelative($candidate, $appHost)) {
            return $fallback;
        }

        return $candidate;
    }
}
