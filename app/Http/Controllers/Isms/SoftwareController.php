<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{SoftwareCategory, SupportStatus};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsSoftwareInstallation, IsmsSoftwareProduct};
use App\Models\User;
use App\Services\Isms\SoftwareInventoryService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Organisationsbezogenes Softwareinventar (Feature 044, MVP 1, Ebene 1):
 * Listenseite (Name, Hersteller, Version, Kategorie, Support-Status,
 * EOL mit Warn-Badge, Installationen-Zahl), Filter (Kategorie/Status/
 * Suche), Modal-CRUD für Produkte und Installationen (Aufklapp-Detail
 * je Produkt, analog Risiken). Autorisierung über
 * IsmsSoftwareProduct-/IsmsSoftwareInstallationPolicy
 * (isms.viewAny/view/manage).
 */
class SoftwareController extends Controller {
    public function __construct(
        private readonly SoftwareInventoryService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsSoftwareProduct::class);

        $filters = [
            'category' => (string) $request->query('category', 'all'),
            'support_status' => (string) $request->query('support_status', 'all'),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = IsmsSoftwareProduct::query()
            ->with(['owner', 'installations'])
            ->withCount('installations');

        if (SoftwareCategory::tryFrom($filters['category']) !== null) {
            $query->where('category', $filters['category']);
        }
        if (SupportStatus::tryFrom($filters['support_status']) !== null) {
            $query->where('support_status', $filters['support_status']);
        }
        if ($filters['q'] !== '') {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', '%' . $filters['q'] . '%')
                    ->orWhere('vendor', 'like', '%' . $filters['q'] . '%');
            });
        }

        $hasActiveFilters = $filters['category'] !== 'all'
            || $filters['support_status'] !== 'all'
            || $filters['q'] !== '';

        return view('isms.software.index', [
            'products' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'eolReachedCount' => IsmsSoftwareProduct::query()->eolReached()->count(),
            'canManage' => Gate::allows('create', IsmsSoftwareProduct::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsSoftwareProduct::class);

        return view('isms.software._form_dialog', [
            'product' => null,
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsSoftwareProduct::class);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->createProduct($creator, $this->validateProduct($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.software_created'));
    }

    public function edit(IsmsSoftwareProduct $product): View {
        Gate::authorize('update', $product);

        return view('isms.software._form_dialog', [
            'product' => $product,
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsSoftwareProduct $product): RedirectResponse {
        Gate::authorize('update', $product);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateProduct($product, $actor, $this->validateProduct($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.software_updated'));
    }

    public function destroy(IsmsSoftwareProduct $product): RedirectResponse {
        Gate::authorize('delete', $product);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteProduct($product, $actor);

        return redirect()
            ->route('isms.software.index')
            ->with('success', __('isms.flash.software_deleted'));
    }

    // ── Installationen (Zeilen-CRUD im Aufklapp-Detail) ──────────────────

    public function createInstallation(IsmsSoftwareProduct $product): View {
        Gate::authorize('create', IsmsSoftwareInstallation::class);

        return view('isms.software._installation_dialog', [
            'product' => $product,
            'installation' => null,
        ]);
    }

    public function storeInstallation(Request $request, IsmsSoftwareProduct $product): RedirectResponse {
        Gate::authorize('create', IsmsSoftwareInstallation::class);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->createInstallation($product, $creator, $this->validateInstallation($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.installation_created'));
    }

    public function editInstallation(IsmsSoftwareInstallation $installation): View {
        Gate::authorize('update', $installation);

        return view('isms.software._installation_dialog', [
            'product' => $installation->product,
            'installation' => $installation,
        ]);
    }

    public function updateInstallation(Request $request, IsmsSoftwareInstallation $installation): RedirectResponse {
        Gate::authorize('update', $installation);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->updateInstallation($installation, $actor, $this->validateInstallation($request));

        return redirect()
            ->back()
            ->with('success', __('isms.flash.installation_updated'));
    }

    public function destroyInstallation(IsmsSoftwareInstallation $installation): RedirectResponse {
        Gate::authorize('delete', $installation);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteInstallation($installation, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.installation_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'product_version' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', Rule::enum(SoftwareCategory::class)],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $request->user()?->organization_id)],
            'support_status' => ['required', 'string', Rule::enum(SupportStatus::class)],
            'eol_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInstallation(Request $request): array {
        return $request->validate([
            'installed_version' => ['nullable', 'string', 'max:64'],
            'asset_ref' => ['nullable', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function ownerOptions() {
        /** @var User $user */
        $user = Auth::user();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
