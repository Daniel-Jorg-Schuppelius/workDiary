<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use App\Http\Requests\SaveSoftwareRequest;
use App\Models\{Software, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SoftwareController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Software::class);

        $query = trim($request->string('q')->toString());
        $kindFilter = $this->normalizeKind($request->string('kind')->toString());

        $softwareQuery = Software::query()
            ->withCount('installations')
            ->orderBy('name');

        if ($query !== '') {
            $softwareQuery->where(function ($builder) use ($query): void {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('vendor', 'like', "%{$query}%");
            });
        }

        if ($kindFilter !== null) {
            $softwareQuery->where('kind', $kindFilter);
        }

        return view('software.index', [
            'softwareItems' => $softwareQuery->paginate(20)->withQueryString(),
            'kindOptions' => $this->kindOptions(),
            'licenseTypeOptions' => $this->licenseTypeOptions(),
            'activeFilters' => [
                'q' => $query,
                'kind' => $kindFilter ?? 'all',
            ],
            'canCreate' => Gate::allows('create', Software::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Software::class);

        return view('software._form_dialog', [
            'software' => new Software([
                'kind' => SoftwareKind::Application->value,
                'license_type' => SoftwareLicenseType::Subscription->value,
                'is_active' => true,
            ]),
            'kindOptions' => $this->kindOptions(),
            'licenseTypeOptions' => $this->licenseTypeOptions(),
        ]);
    }

    public function store(SaveSoftwareRequest $request): RedirectResponse {
        Gate::authorize('create', Software::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $payload = $request->validated();
        $payload['organization_id'] = (int) $user->organization_id;
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        Software::query()->create($payload);

        return redirect()->route('software.index')->with('success', __('Software angelegt.'));
    }

    public function edit(Software $software): View {
        Gate::authorize('update', $software);

        return view('software._form_dialog', [
            'software' => $software,
            'kindOptions' => $this->kindOptions(),
            'licenseTypeOptions' => $this->licenseTypeOptions(),
        ]);
    }

    public function update(SaveSoftwareRequest $request, Software $software): RedirectResponse {
        Gate::authorize('update', $software);

        $payload = $request->validated();
        $payload['is_active'] = (bool) ($payload['is_active'] ?? false);
        $software->update($payload);

        return redirect()->route('software.index')->with('success', __('Software aktualisiert.'));
    }

    public function destroy(Software $software): RedirectResponse {
        Gate::authorize('delete', $software);

        if ($software->installations()->exists()) {
            return back()->withErrors([
                'software' => __('Software wird noch genutzt und kann nicht gelöscht werden.'),
            ]);
        }

        $software->delete();

        return redirect()->route('software.index')->with('success', __('Software gelöscht.'));
    }

    private function normalizeKind(string $value): ?string {
        return array_key_exists($value, $this->kindOptions()) ? $value : null;
    }

    /** @return array<string, string> */
    private function kindOptions(): array {
        $out = [];
        foreach (SoftwareKind::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /** @return array<string, string> */
    private function licenseTypeOptions(): array {
        $out = [];
        foreach (SoftwareLicenseType::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
