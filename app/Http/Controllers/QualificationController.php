<?php

namespace App\Http\Controllers;

use App\Models\Qualification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QualificationController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', Qualification::class);

        $qualifications = Qualification::withCount('users')
            ->orderBy('name')
            ->paginate(30);

        return view('qualifications.index', compact('qualifications'));
    }

    public function create(): View {
        Gate::authorize('create', Qualification::class);

        return view('qualifications._form_dialog', [
            'qualification' => null,
            'isEdit'        => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Qualification::class);

        /** @var \App\Models\User $auth */
        $auth = Auth::user();
        $data = $this->validated($request);
        $data['created_by']      = $auth->id;
        $data['organization_id'] = $auth->organization_id;

        Qualification::create($data);

        return redirect()->route('qualifications.index')
            ->with('success', __('Qualifikation wurde angelegt.'));
    }

    public function edit(Qualification $qualification): View {
        Gate::authorize('update', $qualification);

        return view('qualifications._form_dialog', [
            'qualification' => $qualification,
            'isEdit'        => true,
        ]);
    }

    public function update(Request $request, Qualification $qualification): RedirectResponse {
        Gate::authorize('update', $qualification);

        $qualification->update($this->validated($request, $qualification));

        return redirect()->route('qualifications.index')
            ->with('success', __('Qualifikation wurde gespeichert.'));
    }

    public function destroy(Qualification $qualification): RedirectResponse {
        Gate::authorize('delete', $qualification);

        $qualification->delete();

        return redirect()->route('qualifications.index')
            ->with('success', __('Qualifikation wurde gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Qualification $qualification = null): array {
        $uniqueRule = 'unique:qualifications,name';
        if ($qualification) {
            $uniqueRule .= ',' . $qualification->id;
        }

        return $request->validate([
            'name'         => ['required', 'string', 'max:255', $uniqueRule],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['boolean'],
        ]);
    }
}
