<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\TextCorrection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Pflege-UI des Schreibfehler-Wörterbuchs: Einträge (falsch => richtig) je
 * Organisation, die automatisch auf generierte Positionstexte wirken.
 * Berechtigung `finance.config` — das Wörterbuch verändert Rechnungs-Output
 * ({@see \App\Policies\TextCorrectionPolicy}).
 */
class TextCorrectionController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', TextCorrection::class);

        $q = trim((string) $request->query('q', ''));

        $corrections = TextCorrection::query()
            ->with('creator:id,name')
            ->when($q !== '', static fn ($query) => $query->where(static function ($inner) use ($q): void {
                $inner->whereLikeEscaped('wrong', $q)->orWhereLikeEscaped('correct', $q);
            }))
            ->orderBy('wrong_normalized')
            ->paginate(50)
            ->withQueryString();

        return view('admin.invoicing.text-corrections.index', [
            'corrections' => $corrections,
            'canManage' => Gate::allows('create', TextCorrection::class),
            'q' => $q,
        ]);
    }

    /** Anlege-Dialog (modal-first). */
    public function create(): View {
        Gate::authorize('create', TextCorrection::class);

        return view('admin.invoicing.text-corrections._dialog', ['correction' => null]);
    }

    /** Bearbeiten-Dialog (modal-first). */
    public function edit(TextCorrection $correction): View {
        Gate::authorize('update', $correction);

        return view('admin.invoicing.text-corrections._dialog', ['correction' => $correction]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TextCorrection::class);

        $data = $this->validated($request, null);

        TextCorrection::query()->create([
            'wrong' => $data['wrong'],
            'correct' => $data['correct'],
            'origin' => TextCorrection::ORIGIN_MANUAL,
            'active' => true,
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.text-corrections.index')->with('success', __('textcorrections.flash.saved'));
    }

    public function update(Request $request, TextCorrection $correction): RedirectResponse {
        Gate::authorize('update', $correction);

        $data = $this->validated($request, $correction);

        $correction->update([
            'wrong' => $data['wrong'],
            'correct' => $data['correct'],
        ]);

        return redirect()->route('admin.text-corrections.index')->with('success', __('textcorrections.flash.updated'));
    }

    public function toggle(TextCorrection $correction): RedirectResponse {
        Gate::authorize('update', $correction);

        $correction->forceFill(['active' => ! $correction->active])->save();

        return back()->with('success', $correction->active ? __('textcorrections.flash.activated') : __('textcorrections.flash.deactivated'));
    }

    public function destroy(TextCorrection $correction): RedirectResponse {
        Gate::authorize('delete', $correction);

        $correction->delete();

        return back()->with('success', __('textcorrections.flash.deleted'));
    }

    /**
     * Validierung inkl. org-gescopter Eindeutigkeit auf dem normalisierten
     * Falschwort (Org-Scope über den Global Scope des Models) und Ablehnung
     * von falsch == richtig.
     *
     * @return array{wrong: string, correct: string}
     */
    private function validated(Request $request, ?TextCorrection $ignore): array {
        $ignoreKey = $ignore?->getKey();

        /** @var array{wrong: string, correct: string} $data */
        $data = $request->validate([
            'wrong' => [
                'required', 'string', 'max:190',
                function (string $attribute, mixed $value, callable $fail) use ($ignoreKey): void {
                    $key = TextCorrection::normalizeKey((string) $value);
                    if ($key === '') {
                        $fail(__('validation.required', ['attribute' => $attribute]));

                        return;
                    }
                    $exists = TextCorrection::query()
                        ->where('wrong_normalized', $key)
                        ->when($ignoreKey !== null, static fn ($q) => $q->whereKeyNot($ignoreKey))
                        ->exists();
                    if ($exists) {
                        $fail(__('textcorrections.validation.duplicate'));
                    }
                },
            ],
            'correct' => ['required', 'string', 'max:190'],
        ]);

        if (TextCorrection::normalizeKey($data['wrong']) === TextCorrection::normalizeKey($data['correct'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct' => __('textcorrections.flash.invalid'),
            ]);
        }

        return $data;
    }
}
