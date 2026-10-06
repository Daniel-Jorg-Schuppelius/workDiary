<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesAgreementLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy\Concerns;

use App\Models\Privacy\{JointControllerAgreement, ProcessingActivity, ProcessingAgreement};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Verknüpfte Verarbeitungstätigkeiten und Vertragsdokument von AVV und Vereinbarung nach Art. 26. */
trait ManagesAgreementLinks {
    /**
     * Sqids aus dem Formular (Audit 2026-08, W3.3); verknüpft werden nur
     * Tätigkeiten der Organisation des Vertrags.
     */
    private function syncAgreementActivities(Request $request, JointControllerAgreement|ProcessingAgreement $agreement): RedirectResponse {
        $data = $request->validate(['activity_ids' => ['array'], 'activity_ids.*' => ['string']]);
        $requested = array_filter(array_map(
            static fn(string $v): ?int => Sqid::decodeOrNumeric(ProcessingActivity::class, $v),
            $data['activity_ids'] ?? [],
        ));
        $valid = ProcessingActivity::query()
            ->where('organization_id', $agreement->organization_id)
            ->whereIn('id', $requested)
            ->pluck('id')->all();
        $agreement->activities()->sync($valid);

        return back()->with('status', __('Verknüpfungen gespeichert.'));
    }

    private function downloadAgreementDocument(JointControllerAgreement|ProcessingAgreement $agreement, string $fallbackName): BinaryFileResponse {
        $path = $agreement->document_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), $agreement->document_name ?? $fallbackName);
    }
}
