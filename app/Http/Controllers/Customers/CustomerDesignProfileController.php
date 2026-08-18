<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDesignProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Enums\DocumentDesign\RenderProfileStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DocumentDesign\DocumentRenderProfile;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Kunden-Sonderdesign (MVP-651, vormals `customers.invoice_template_id`):
 * weist der Kundenakte ein Dokumentdesign-Profil zu bzw. entfernt die
 * Zuordnung. Das referenzierte aktive Profil gewinnt bei der
 * Profilauflösung vor der org-weiten Kette.
 */
class CustomerDesignProfileController extends Controller {
    public function __invoke(Request $request, Customer $customer): RedirectResponse {
        $user = Auth::user();
        abort_unless($user !== null && ($user->isAdmin() || $user->can(Permission::DocumentDesignAssign->value) || $user->can(Permission::DocumentDesignManage->value)), 403);

        $data = $request->validate([
            'profile' => ['nullable', 'string', 'max:64'],
        ]);

        $profileId = null;
        $sqid = trim((string) ($data['profile'] ?? ''));
        if ($sqid !== '') {
            $decoded = app(SqidEncoder::class)->decode(DocumentRenderProfile::class, $sqid);
            $profile = $decoded === null ? null : DocumentRenderProfile::query()
                ->where('organization_id', $customer->organization_id)
                ->where('status', '!=', RenderProfileStatus::Archived)
                ->whereKey($decoded)
                ->first();
            abort_unless($profile !== null, 404);
            $profileId = (int) $profile->id;
        }

        $previous = $customer->document_render_profile_id !== null ? (int) $customer->document_render_profile_id : null;
        $customer->forceFill(['document_render_profile_id' => $profileId])->save();
        $customer->audit('customer.design_profile_assigned', [
            'from_profile_id' => $previous,
            'to_profile_id' => $profileId,
        ]);

        return back()->with('status', $profileId !== null
            ? __('Kunden-Sonderdesign zugewiesen.')
            : __('Kunden-Sonderdesign entfernt — es gilt wieder das org-weite Design.'));
    }
}
