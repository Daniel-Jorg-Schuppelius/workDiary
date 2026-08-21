<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Communication\CustomerCircular;
use App\Models\Customer;
use App\Services\Communication\CustomerCircularService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Kundenrundschreiben (Feature 119, MVP-608).
 *
 * Die Empfängerzahl wird VOR dem Versand angezeigt — eine Mail an alle Kunden
 * ist kein Vorgang, den man versehentlich auslösen können soll.
 */
class CustomerCircularController extends Controller {
    public function __construct(private readonly CustomerCircularService $circulars) {}

    public function index(): View {
        Gate::authorize('viewAny', Customer::class);

        return view('circulars.index', [
            'circulars' => CustomerCircular::query()
                ->withCount([
                    'recipients',
                    'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
                    'recipients as skipped_count' => fn ($q) => $q->where('status', '!=', 'sent'),
                ])
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function form(?CustomerCircular $circular = null): View {
        Gate::authorize('create', Customer::class);

        return view('circulars._form_dialog', ['circular' => $circular]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Customer::class);
        $data = $this->validated($request);

        $circular = CustomerCircular::query()->create([
            'organization_id' => $request->user()?->organization_id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
            'portal_notice' => (bool) ($data['portal_notice'] ?? false),
            'filters' => $this->filtersFrom($data),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('circulars.show', $circular)->with('status', __('circular.created'));
    }

    /** Vorschau: Empfängerkreis mit Zahl — vor dem Versand, nicht danach. */
    public function show(CustomerCircular $circular): View {
        Gate::authorize('viewAny', Customer::class);

        return view('circulars.show', [
            'circular' => $circular->load('recipients.customer'),
            'audience' => $circular->isDraft()
                ? $this->circulars->audience((array) ($circular->filters ?? []), (bool) $circular->is_mandatory)
                : collect(),
        ]);
    }

    public function send(Request $request, CustomerCircular $circular): RedirectResponse {
        Gate::authorize('create', Customer::class);
        $actor = $request->user();
        abort_if($actor === null, 403);

        try {
            $this->circulars->send($circular, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('circular.sent'));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filtersFrom(array $data): array {
        return array_filter([
            'search' => $data['search'] ?? null,
            'city' => $data['city'] ?? null,
            'zip_prefix' => $data['zip_prefix'] ?? null,
            'with_active_projects' => ! empty($data['with_active_projects']),
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== false);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        return $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:20000'],
            'is_mandatory' => ['nullable', 'boolean'],
            'portal_notice' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'zip_prefix' => ['nullable', 'string', 'max:10'],
            'with_active_projects' => ['nullable', 'boolean'],
        ]);
    }
}
