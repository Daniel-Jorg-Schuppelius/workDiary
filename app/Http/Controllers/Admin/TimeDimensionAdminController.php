<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeDimensionAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Organization, TimeDimensionType, TimeDimensionValue, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-Pflege der freien Mandanten-Dimensionen (Feature 103, MVP-514 P2):
 * Dimensionstypen (z. B. „ERP-Auftrag") und deren Werte mit Gültigkeit und
 * externer ID (Anker für die vorgesehene Provider-Synchronisation).
 * Admin-gebunden wie die Terminal-Verwaltung.
 */
class TimeDimensionAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.time-dimensions.index', [
            'types' => TimeDimensionType::query()
                ->where('organization_id', $organization->id)
                ->with('values')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('time_dimension_types', 'code')->where('organization_id', $organization->id),
            ],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $type = TimeDimensionType::query()->create($data + ['organization_id' => $organization->id, 'enabled' => true]);
        $type->audit('timeDimension.type_created', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('allocation.dimensions.flash.type_created'));
    }

    public function toggleType(Request $request, TimeDimensionType $type): RedirectResponse {
        $admin = $this->admin();
        abort_unless((int) $type->organization_id === (int) $admin->organization_id, 404);

        $type->forceFill(['enabled' => ! $type->enabled])->save();
        $type->audit('timeDimension.type_toggled', ['enabled' => (bool) $type->enabled, 'by_user_id' => (int) $admin->id]);

        return back()->with('success', __($type->enabled ? 'allocation.dimensions.flash.type_enabled' : 'allocation.dimensions.flash.type_disabled'));
    }

    public function storeValue(Request $request, TimeDimensionType $type): RedirectResponse {
        $admin = $this->admin();
        abort_unless((int) $type->organization_id === (int) $admin->organization_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'external_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('time_dimension_values', 'external_id')->where('dimension_type_id', $type->id),
            ],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $value = $type->values()->create($data + ['organization_id' => $type->organization_id]);
        $value->audit('timeDimension.value_created', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('allocation.dimensions.flash.value_created'));
    }

    public function destroyValue(TimeDimensionValue $value): RedirectResponse {
        $admin = $this->admin();
        abort_unless((int) $value->organization_id === (int) $admin->organization_id, 404);

        $value->audit('timeDimension.value_deleted', ['by_user_id' => (int) $admin->id, 'name' => $value->name]);
        $value->delete();

        return back()->with('success', __('allocation.dimensions.flash.value_deleted'));
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
