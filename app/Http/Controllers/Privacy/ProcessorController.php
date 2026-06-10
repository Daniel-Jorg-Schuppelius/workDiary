<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\ProcessorRole;
use App\Http\Controllers\Controller;
use App\Models\Privacy\Processor;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Dienstleister-/Vertragspartnerregister (Art. 28). */
class ProcessorController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', Processor::class);

        return view('privacy.processors.index', [
            'processors' => Processor::query()->withCount('agreements')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Processor::class);

        return view('privacy.processors._form_dialog', ['roles' => ProcessorRole::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Processor::class);
        $user = $request->user();
        $org = $user?->organization;
        abort_unless($org !== null, 403);

        $data = $this->validateProcessor($request);
        $processor = Processor::create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            ...$data,
        ]);

        return redirect()->route('dataprotection.processors.show', $processor)
            ->with('status', __('Dienstleister angelegt.'));
    }

    public function show(Processor $processor): View {
        Gate::authorize('view', $processor);

        return view('privacy.processors.show', [
            'processor' => $processor->load('agreements'),
            'roles' => ProcessorRole::cases(),
        ]);
    }

    public function update(Request $request, Processor $processor): RedirectResponse {
        Gate::authorize('update', $processor);
        $processor->update($this->validateProcessor($request));

        return back()->with('status', __('Dienstleister aktualisiert.'));
    }

    /** @return array<string, mixed> */
    private function validateProcessor(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string'],
            'contact' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'third_country' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
