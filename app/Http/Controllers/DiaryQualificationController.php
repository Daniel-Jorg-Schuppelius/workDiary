<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryQualificationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{DiaryEntry, Qualification, User};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Qualifikations-Anforderungen je Auftrag pflegen (Feature 028, Rang 53):
 * Grundlage der Auftrags-Qualifikationsmatrix in der Disposition.
 */
class DiaryQualificationController extends Controller {
    public function update(Request $request, DiaryEntry $diary): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can(Permission::DispatchManage->value) || Gate::allows('update', $diary), 403);

        $data = $request->validate([
            'qualifications' => ['nullable', 'array'],
            'qualifications.*' => ['string'],
        ]);

        $encoder = app(SqidEncoder::class);
        $ids = [];
        foreach ((array) ($data['qualifications'] ?? []) as $sqid) {
            $id = $encoder->decode(Qualification::class, (string) $sqid);
            if ($id === null) {
                continue;
            }
            // Org-Scope: nur Qualifikationen der eigenen Organisation.
            if (Qualification::query()->whereKey($id)->exists()) {
                $ids[] = (int) $id;
            }
        }

        $diary->requiredQualifications()->sync($ids);

        return back()->with('status', __('Qualifikations-Anforderungen aktualisiert.'));
    }
}
