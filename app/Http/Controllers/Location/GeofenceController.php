<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeofenceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\CustomerGeofence;
use App\Models\{Customer, Project, Site};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verwaltung der Kunden-Geofences (Standortbasierte Zeiterfassung). Modal-CRUD
 * analog {@see \App\Http\Controllers\SiteController}.
 */
class GeofenceController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', CustomerGeofence::class);

        $customerId = Sqid::decodeOrNumeric(Customer::class, (string) $request->query('customer', ''));

        $query = CustomerGeofence::query()
            ->with(['customer', 'site'])
            ->orderBy('label');
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return view('geofences.index', [
            'geofences' => $query->paginate(50)->withQueryString(),
            'customer' => $customerId !== null ? Customer::query()->find($customerId) : null,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CustomerGeofence::class);

        return view('geofences._form_dialog', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CustomerGeofence::class);

        $geofence = CustomerGeofence::create($this->validateGeofence($request) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('geofences.index', ['customer' => $geofence->customer?->sqid])
            ->with('success', __('Geofence angelegt.'));
    }

    public function edit(CustomerGeofence $geofence): View {
        Gate::authorize('update', $geofence);

        return view('geofences._form_dialog', $this->formData($geofence));
    }

    public function update(Request $request, CustomerGeofence $geofence): RedirectResponse {
        Gate::authorize('update', $geofence);

        $geofence->update($this->validateGeofence($request) + [
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('geofences.index', ['customer' => $geofence->customer?->sqid])
            ->with('success', __('Geofence aktualisiert.'));
    }

    public function destroy(CustomerGeofence $geofence): RedirectResponse {
        Gate::authorize('delete', $geofence);
        $geofence->delete();

        return redirect()->route('geofences.index')->with('success', __('Geofence gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function formData(?CustomerGeofence $geofence): array {
        return [
            'geofence' => $geofence,
            'customers' => Customer::query()->orderBy('name')->get(),
            'sites' => Site::query()->orderBy('name')->get(['id', 'customer_id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'customer_id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validateGeofence(Request $request): array {
        $request->merge([
            'customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'site_id' => Sqid::decodeOrNumeric(Site::class, $request->input('site_id')),
            'project_id' => Sqid::decodeOrNumeric(Project::class, $request->input('project_id')),
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'label' => ['required', 'string', 'max:160'],
            'center_lat' => ['required', 'numeric', 'between:-90,90'],
            'center_lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:10,5000'],
            'min_dwell_minutes' => ['required', 'integer', 'between:0,1440'],
            'gap_merge_minutes' => ['required', 'integer', 'between:0,1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['is_active'] ??= false;

        return $data;
    }
}
