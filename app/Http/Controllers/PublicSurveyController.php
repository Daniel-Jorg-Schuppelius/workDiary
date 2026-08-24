<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSurveyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Survey\SurveyInvitation;
use App\Services\Survey\SurveyService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use RuntimeException;

/**
 * Öffentliche Umfrage-Teilnahme (Feature 090): token-basiert ohne Login;
 * widerrufen/abgelaufen/unbekannt ⇒ 404 (Muster PublicAuditPackage).
 */
class PublicSurveyController extends Controller {
    public function show(string $token): View {
        [$invitation, $survey] = $this->resolve($token);

        return view('public.survey', [
            'survey' => $survey,
            'questions' => $survey->questions()->withoutGlobalScopes()->orderBy('position')->get(),
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $token, SurveyService $service): View|RedirectResponse {
        [$invitation, $survey] = $this->resolve($token);

        // Frage-IDs kommen als q<id> aus dem eigenen Formular — die Auflösung
        // bleibt serverseitig. Öffentlicher Endpunkt: Regeln je Fragetyp
        // (Vollscan 2026-08-23, E1) — Array-Input und Bereichsverletzungen
        // enden als Validierungsfehler statt als 500/Datenverfälschung.
        $questions = $survey->questions()->withoutGlobalScopes()->get();
        $rules = [];
        foreach ($questions as $question) {
            $rules['q' . $question->id] = match ($question->type) {
                'nps' => [$question->required ? 'required' : 'nullable', 'integer', 'between:0,10'],
                'scale' => [$question->required ? 'required' : 'nullable', 'integer', 'between:1,5'],
                'choice' => [$question->required ? 'required' : 'nullable', 'string', \Illuminate\Validation\Rule::in($question->options ?? [])],
                default => [$question->required ? 'required' : 'nullable', 'string', 'max:4000'],
            };
        }
        $validated = $request->validate($rules);

        $answers = [];
        foreach ($questions as $question) {
            $answers[$question->id] = $validated['q' . $question->id] ?? null;
        }

        try {
            $service->submit($invitation, $answers);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return view('public.survey-thanks', ['survey' => $survey]);
    }

    /**
     * @return array{0: SurveyInvitation, 1: \App\Models\Survey\Survey}
     */
    private function resolve(string $token): array {
        // Kein Org-Kontext gebunden ⇒ Auflösung ausschließlich über den
        // Token-Hash; jeder Fehlweg ist ein 404.
        $invitation = SurveyInvitation::query()
            ->withoutGlobalScopes()
            ->where('token_hash', SurveyInvitation::hashToken($token))
            ->first();
        abort_if($invitation === null || ! $invitation->isUsable(), 404);

        $survey = $invitation->survey()->withoutGlobalScopes()->first();
        abort_if($survey === null || ! $survey->active, 404);

        return [$invitation, $survey];
    }
}
