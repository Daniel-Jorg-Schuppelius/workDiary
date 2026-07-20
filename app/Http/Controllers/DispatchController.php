<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Diary\DispatchStatus;
use App\Enums\User\Permission;
use App\Models\{DiaryEntry, User};
use App\Services\Dispatch\{DispatchConflictChecker, DispatchStatusResolver};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Disposition eines Auftrags (Feature 028): Konfliktvorschau und
 * Status-Übergänge. Harte Konflikte blockieren die Bestätigung, sofern nicht
 * mit Begründung bewusst übersteuert wird (Audit über DispatchStatusResolver).
 */
class DispatchController extends Controller {
    public function __construct(
        private readonly DispatchConflictChecker $conflicts,
        private readonly DispatchStatusResolver $resolver,
    ) {}

    /** Konfliktvorschau für die aktuell geplante Zuweisung (JSON). */
    public function conflicts(DiaryEntry $diary): JsonResponse {
        Gate::authorize('view', $diary);

        $report = $this->conflicts->check($diary);

        return response()->json([
            'has_errors' => $report->hasErrors(),
            'conflicts' => $report->toArray(),
            'dispatch_status' => $this->resolver->resolve($diary)->value,
        ]);
    }

    /** Setzt den Dispositionsstatus; bestätigt mit optionalem Override. */
    public function transition(Request $request, DiaryEntry $diary): RedirectResponse {
        $this->authorizeManage($diary);

        $data = $request->validate([
            // Rule::enum statt Handliste (Vollaudit 2026-07, N48).
            'dispatch_status' => ['required', 'string', \Illuminate\Validation\Rule::enum(DispatchStatus::class)],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = DispatchStatus::from($data['dispatch_status']);
        $current = $this->resolver->resolve($diary);

        if ($current !== $target && ! $current->canTransitionTo($target)) {
            return back()->withErrors([
                'dispatch' => __('Ungültiger Dispositions-Übergang: :from → :to.', [
                    'from' => $current->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        // Harte Konflikte blockieren die Bestätigung, außer bewusst übersteuert.
        if (in_array($target, [DispatchStatus::Confirmed, DispatchStatus::EnRoute], true)) {
            $report = $this->conflicts->check($diary);
            if ($report->hasErrors() && empty($data['override_reason'])) {
                return back()->withErrors([
                    'dispatch' => __('Harte Konflikte verhindern die Bestätigung. Bitte Konflikte beheben oder mit Begründung übersteuern.'),
                ])->with('dispatch_conflicts', $report->toArray());
            }
        }

        /** @var User $actor */
        $actor = Auth::user();
        $this->resolver->transition(
            $diary,
            $target,
            $actor->id,
            $data['override_reason'] ?? null,
        );

        return back()->with('success', __('Dispositionsstatus aktualisiert: :status.', [
            'status' => $target->label(),
        ]));
    }

    private function authorizeManage(DiaryEntry $diary): void {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->can(Permission::DispatchManage->value) && ! Gate::allows('update', $diary)) {
            abort(403);
        }
    }
}
