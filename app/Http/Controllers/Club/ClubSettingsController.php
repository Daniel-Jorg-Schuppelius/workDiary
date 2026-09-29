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
use App\Services\Club\{ClubDonationService, ClubGradingService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Vereinseinstellungen der Organisation (MVP-846): optionales Graduierungsmodul. */
class ClubSettingsController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubGradingService $grading,
        private readonly ClubDonationService $donations,
    ) {}

    public function edit(): View {
        Gate::authorize('create', ClubMember::class);

        return view('club._settings_dialog', [
            'graduationEnabled' => $this->grading->isEnabled($this->currentOrganization()),
            // Beitragsmitteilung (MVP-850): freier Fußtext, z. B. Hinweis zur Beleg-/Steuerzuordnung.
            'feeNoticeFooter' => (string) data_get($this->currentOrganization()->settings, 'club.fees.notice_footer', ''),
            // Freistellungsdaten für Zuwendungsbestätigungen (MVP-1003).
            'exemption' => $this->donations->exemption($this->currentOrganization()),
            'feesConfirmable' => $this->donations->membershipFeesConfirmable($this->currentOrganization()),
            // Geschwisterstaffel (MVP-1016).
            'siblings' => (array) data_get($this->currentOrganization()->settings, 'club.fees.siblings', []),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('create', ClubMember::class);
        $data = $request->validate([
            'graduation_enabled' => ['nullable', 'boolean'],
            'fee_notice_footer' => ['nullable', 'string', 'max:2000'],
            'donations' => ['nullable', 'array'],
            'donations.tax_office' => ['nullable', 'string', 'max:120'],
            'donations.tax_number' => ['nullable', 'string', 'max:40'],
            'donations.exemption_kind' => ['nullable', Rule::in(ClubDonationService::EXEMPTION_KINDS)],
            'donations.notice_date' => ['nullable', 'date'],
            'donations.assessment_period' => ['nullable', 'string', 'max:40'],
            'donations.purpose' => ['nullable', 'string', 'max:500'],
            'donations.signatory' => ['nullable', 'string', 'max:120'],
            'donations.membership_fees_confirmable' => ['nullable', 'boolean'],
            'siblings' => ['nullable', 'array'],
            'siblings.second_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'siblings.further_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'siblings.max_age' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
        $organization = $this->currentOrganization();
        $this->grading->setEnabled($organization, (bool) ($data['graduation_enabled'] ?? false));
        $settings = (array) ($organization->refresh()->settings ?? []);
        data_set($settings, 'club.fees.notice_footer', trim((string) ($data['fee_notice_footer'] ?? '')));
        foreach (['tax_office', 'tax_number', 'exemption_kind', 'notice_date', 'assessment_period', 'purpose', 'signatory'] as $key) {
            data_set($settings, 'club.donations.' . $key, trim((string) ($data['donations'][$key] ?? '')));
        }
        data_set($settings, 'club.donations.membership_fees_confirmable', $request->boolean('donations.membership_fees_confirmable'));
        foreach (['second_percent', 'further_percent', 'max_age'] as $key) {
            data_set($settings, 'club.fees.siblings.' . $key, trim((string) ($data['siblings'][$key] ?? '')));
        }
        $organization->update(['settings' => $settings]);

        return redirect()->route('club.grading.index')->with('success', __('club.grading.flash.settings_saved'));
    }
}
