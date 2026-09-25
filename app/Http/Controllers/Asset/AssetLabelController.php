<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLabelController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Services\Print\LabelPdfRenderer;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;

/**
 * Objekt-Etikett (MVP-882): der QR führt auf die Objektseite, Login und
 * Policy entscheiden dort über den Zugriff.
 */
class AssetLabelController extends Controller {
    public function __invoke(Request $request, Asset $asset, LabelPdfRenderer $renderer): Response {
        Gate::authorize('view', $asset);

        $bytes = $renderer->render([
            'code' => $asset->asset_no,
            'code_type' => 'asset',
            'title' => $asset->name,
            'subtitle' => trim(($asset->manufacturer ?? '') . ' ' . ($asset->model ?? '')) ?: null,
            'lines' => array_values(array_filter([$asset->inventory_no, $asset->serial_no])),
        ], route('assets.show', $asset), $request->string('template')->toString());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . $asset->asset_no . '.pdf"',
        ]);
    }
}
