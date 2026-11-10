<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryQualificationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Diary;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use App\Services\Diary\Contracts\AssignmentQualificationGuard;
use App\Support\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};

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

        // Der schon Zuständige muss die neue Anforderung erfüllen wie an den Zuweisungswegen (MVP-1528);
        // die Sperre wirft und rollt die Änderung zurück.
        $assignee = $diary->assigned_user_id !== null ? (int) $diary->assigned_user_id : null;
        $guard = app(AssignmentQualificationGuard::class);
        $hint = DB::transaction(function () use ($diary, $ids, $assignee, $guard): ?string {
            $diary->requiredQualifications()->sync($ids);
            if ($assignee === null) {
                return null;
            }
            $guard->assertEntryAssignable($diary, $assignee, null, 'qualifications');

            return $guard->entryHint($diary, $assignee);
        });

        $response = back()->with('status', __('Qualifikations-Anforderungen aktualisiert.'));

        return $hint !== null ? $response->with('warning', $hint) : $response;
    }
}
