<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSettingsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Club\ClubMember;
use App\Services\Club\ClubGradingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Vereinseinstellungen der Organisation (MVP-846): optionales Graduierungsmodul. */
class ClubSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubGradingService $grading,
    ) {}

    public function edit(): View {
        Gate::authorize('create', ClubMember::class);

        return view('club._settings_dialog', [
            'graduationEnabled' => $this->grading->isEnabled($this->currentOrganization()),
            // Beitragsmitteilung (MVP-850): freier Fußtext, z. B. Hinweis zur Beleg-/Steuerzuordnung.
            'feeNoticeFooter' => (string) data_get($this->currentOrganization()->settings, 'club.fees.notice_footer', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('create', ClubMember::class);
        $data = $request->validate(['graduation_enabled' => ['nullable', 'boolean'], 'fee_notice_footer' => ['nullable', 'string', 'max:2000']]);
        $organization = $this->currentOrganization();
        $this->grading->setEnabled($organization, (bool) ($data['graduation_enabled'] ?? false));
        $settings = (array) ($organization->refresh()->settings ?? []);
        data_set($settings, 'club.fees.notice_footer', trim((string) ($data['fee_notice_footer'] ?? '')));
        $organization->update(['settings' => $settings]);

        return redirect()->route('club.grading.index')->with('success', __('club.grading.flash.settings_saved'));
    }
}
