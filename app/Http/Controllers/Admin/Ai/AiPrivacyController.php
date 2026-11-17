<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiPrivacyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiProviderConnection;
use App\Services\Ai\AiConnectionPrivacy;
use App\Services\Attachments\FileAttacher;
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Datenschutz der KI-Dienste (Feature 025, MVP-1671/1672): Freigabe je
 * Verbindung (AVV mit Dokument, Verarbeitung in der EU, keine
 * Trainingsnutzung, geprüft am/von) und die Einstellungen der Organisation —
 * nur freigegebene Verbindungen, Frist der Freigabe, Maskierung auch vor
 * lokalen Verbindungen. Alles unter `ai.approve`; Freigabe, Widerruf und
 * Einstellungen sind auditiert (Modell bzw. Organisation).
 */
class AiPrivacyController extends Controller {
    use ResolvesCurrentOrganization;

    public function edit(AiConnectionPrivacy $privacy): View {
        Gate::authorize('approve', AiProviderConnection::class);
        $organization = $this->currentOrganization();

        return view('admin.ai._privacy_dialog', [
            'requireApproval' => $privacy->requiresApproval($organization),
            'approvalMonths' => $privacy->approvalMonths($organization),
            'maskLocal' => $privacy->masksLocal($organization),
        ]);
    }

    public function update(Request $request, SettingsRegistry $registry): RedirectResponse {
        Gate::authorize('approve', AiProviderConnection::class);

        $data = $request->validate([
            'require_approval' => ['nullable', 'boolean'],
            'approval_months' => ['required', 'integer', 'min:1', 'max:60'],
            'mask_local' => ['nullable', 'boolean'],
        ]);

        $organization = $this->currentOrganization();
        $userId = $this->authUser()->id;
        $registry->set(AiConnectionPrivacy::SETTING_REQUIRE_APPROVAL, (bool) ($data['require_approval'] ?? false), SettingScope::Organization, $organization, $userId);
        $registry->set(AiConnectionPrivacy::SETTING_APPROVAL_MONTHS, (int) $data['approval_months'], SettingScope::Organization, $organization, $userId);
        $registry->set(AiConnectionPrivacy::SETTING_MASK_LOCAL, (bool) ($data['mask_local'] ?? false), SettingScope::Organization, $organization, $userId);

        return redirect()->route('admin.ai.index')->with('success', __('ai.privacy.flash.settings_saved'));
    }

    /** Freigabe-Dialog einer Verbindung (modal-first). */
    public function approval(AiProviderConnection $connection, AiConnectionPrivacy $privacy): View {
        Gate::authorize('approve', $connection);
        $organization = $this->currentOrganization();

        return view('admin.ai._approval_dialog', [
            'connection' => $connection,
            'state' => $privacy->state($connection, $organization),
            'expiresAt' => $privacy->expiresAt($connection, $organization),
            'document' => $connection->dpaDocument(),
            'reviewer' => $connection->privacyReviewer,
        ]);
    }

    /**
     * Erteilt (oder erneuert) die Freigabe. Bei Cloud-Verbindungen gehört der AVV
     * als Dokument dazu — hochgeladen jetzt oder schon an der Verbindung.
     */
    public function approve(Request $request, AiProviderConnection $connection, AiConnectionPrivacy $privacy, FileAttacher $attacher): RedirectResponse {
        Gate::authorize('approve', $connection);

        $data = $request->validate([
            'has_dpa' => ['nullable', 'boolean'],
            'is_eu_processing' => ['accepted'],
            'is_training_excluded' => ['accepted'],
            'dpa_document' => ['nullable', ...FileAttacher::rule()],
        ]);
        $hasDpa = (bool) ($data['has_dpa'] ?? false);
        $file = $request->file('dpa_document');
        $file = $file instanceof UploadedFile ? $file : null;

        if ($connection->isCloud() && ! $hasDpa) {
            return back()->withInput()->withErrors(['has_dpa' => __('ai.privacy.error.dpa_required')]);
        }
        if ($connection->isCloud() && $file === null && $connection->dpaDocument() === null) {
            return back()->withInput()->withErrors(['dpa_document' => __('ai.privacy.error.dpa_document_required')]);
        }

        // Ein neues Dokument kommt dazu; frühere bleiben als Stand früherer Prüfungen am Anhang.
        if ($file !== null) {
            $attacher->store($connection, $file, $this->authUser()->id, [
                'organization_id' => $connection->organization_id,
                'meta_type' => AiProviderConnection::DPA_META,
            ]);
        }

        $privacy->approve($connection, $this->authUser(), $hasDpa, true, true);

        return redirect()->route('admin.ai.index')->with('success', __('ai.privacy.flash.approved', ['name' => $connection->name]));
    }

    public function revoke(AiProviderConnection $connection, AiConnectionPrivacy $privacy): RedirectResponse {
        Gate::authorize('approve', $connection);

        $privacy->revoke($connection);

        return redirect()->route('admin.ai.index')->with('success', __('ai.privacy.flash.revoked', ['name' => $connection->name]));
    }
}
