<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarPortalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\DsarPortal;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung des oeffentlichen Betroffenenportals (G11, MVP-728) — eine
 * Instanz je Organisation, Permission `dataprotection.portal.manage`. Der
 * `public_slug` ist nicht aus dem Organisationsnamen ableitbar und laesst sich
 * unabhaengig rotieren; Default ist AUS.
 */
class DsarPortalController extends Controller {
    public function edit(): View {
        Gate::authorize('dataprotection.portal.manage');

        return view('privacy.portal.edit', ['portal' => $this->portal()]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('dataprotection.portal.manage');

        $data = $request->validate([
            'intro_text' => ['nullable', 'string', 'max:5000'],
            'default_locale' => ['nullable', 'string', 'max:10'],
        ]);

        $portal = $this->portal();
        if (! $portal->exists) {
            $portal->organization_id = (int) ($request->user()->organization_id ?? 0);
            abort_if($portal->organization_id === 0, 403);
            $portal->public_slug = $this->freshSlug();
        }

        $portal->fill([
            'is_enabled' => $request->boolean('is_enabled'),
            'allow_attachments' => $request->boolean('allow_attachments'),
            'intro_text' => $data['intro_text'] ?? null,
            'default_locale' => $data['default_locale'] ?? null,
        ]);
        $portal->save();

        return redirect()->route('dataprotection.portal.edit')->with('success', __('dsar.admin.saved'));
    }

    public function rotateSlug(): RedirectResponse {
        Gate::authorize('dataprotection.portal.manage');

        $portal = DsarPortal::query()->firstOrFail();
        $portal->forceFill(['public_slug' => $this->freshSlug()])->save();

        return redirect()->route('dataprotection.portal.edit')->with('success', __('dsar.admin.rotated'));
    }

    private function portal(): DsarPortal {
        return DsarPortal::query()->first() ?? new DsarPortal(['allow_attachments' => true]);
    }

    private function freshSlug(): string {
        do {
            $slug = 'ds-' . Str::lower(Str::random(12));
        } while (DsarPortal::query()->withoutGlobalScopes()->where('public_slug', $slug)->exists());

        return $slug;
    }
}
