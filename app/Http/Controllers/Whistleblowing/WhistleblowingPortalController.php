<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Whistleblowing;

use App\Http\Controllers\Controller;
use App\Models\Whistleblowing\Portal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung des oeffentlichen Meldeportals einer Organisation (eine Instanz pro
 * Org). Erfordert die Permission whistleblowing.settings.manage. Der
 * `public_slug` ist bewusst nicht aus dem Org-Namen ableitbar und kann unabhaengig
 * rotiert/deaktiviert werden (Abschnitt 9.1).
 */
class WhistleblowingPortalController extends Controller {
    public function edit(): View {
        Gate::authorize('whistleblowing.settings.manage');

        return view('whistleblowing.internal.portal', ['portal' => $this->portal()]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('whistleblowing.settings.manage');

        $data = $request->validate([
            'intro_text' => ['nullable', 'string', 'max:5000'],
            'default_locale' => ['nullable', 'string', 'max:10'],
            'retention_months' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        $portal = $this->portal();
        if (! $portal->exists) {
            $portal->public_slug = $this->freshSlug();
        }

        $portal->fill([
            'is_enabled' => $request->boolean('is_enabled'),
            'allow_anonymous' => $request->boolean('allow_anonymous'),
            'allow_confidential' => $request->boolean('allow_confidential'),
            'intro_text' => $data['intro_text'] ?? null,
            'default_locale' => $data['default_locale'] ?? null,
            'retention_months' => (int) $data['retention_months'],
        ]);
        $portal->save();

        return redirect()->route('whistleblowing.portal.edit')->with('success', __('Meldeportal gespeichert.'));
    }

    public function rotateSlug(): RedirectResponse {
        Gate::authorize('whistleblowing.settings.manage');

        $portal = Portal::query()->firstOrFail();
        $portal->forceFill(['public_slug' => $this->freshSlug()])->save();

        return redirect()->route('whistleblowing.portal.edit')
            ->with('success', __('Portal-Link wurde rotiert. Bereits verteilte Links sind jetzt ungueltig.'));
    }

    private function portal(): Portal {
        return Portal::query()->first() ?? new Portal([
            'allow_anonymous' => true,
            'allow_confidential' => true,
            'retention_months' => (int) config('whistleblowing.retention_months', 36),
        ]);
    }

    private function freshSlug(): string {
        do {
            $slug = 'wb-' . Str::lower(Str::random(12));
        } while (Portal::query()->withoutGlobalScopes()->where('public_slug', $slug)->exists());

        return $slug;
    }
}
