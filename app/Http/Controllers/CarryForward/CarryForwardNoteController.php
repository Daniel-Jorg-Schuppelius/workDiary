<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNoteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CarryForward;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Contracts\PeriodicOccurrence;
use App\Services\CarryForward\CarryForwardNotes;
use App\Support\MorphMap;
use App\Support\Period\PeriodKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645): Schreibwege der
 * Komponente `<x-carry-forward-notes :subject>`. Das Vorkommen kommt als
 * Morph-Alias und Routenschlüssel aus dem Formular; geschrieben wird nur, wer
 * das Vorkommen bearbeiten darf (`update` seiner Policy) — der Merker hat keine
 * eigenen Rechte.
 */
class CarryForwardNoteController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CarryForwardNotes $notes) {}

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:64'],
            'note' => ['required', 'string', 'max:2000'],
            'period_key' => ['nullable', 'string', 'max:10'],
        ]);
        $subject = $this->occurrence((string) $data['subject_type'], (string) $data['subject_id']);
        $from = null;
        if (($data['period_key'] ?? '') !== '') {
            $from = PeriodKey::tryParse((string) $data['period_key']);
            abort_if($from === null, 422);
        }

        $this->notes->create($subject, (string) $data['note'], $this->authUser(), $from);

        return back()->with('success', __('carry_forward.flash.created'));
    }

    public function resolve(Request $request, CarryForwardNote $carryForwardNote): RedirectResponse {
        $this->assertSameOrganization($carryForwardNote);
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:64'],
            'resolution_note' => ['nullable', 'string', 'max:500'],
        ]);
        $subject = $this->occurrence((string) $data['subject_type'], (string) $data['subject_id']);

        $this->notes->resolve($carryForwardNote, $subject, $this->authUser(), $data['resolution_note'] ?? null);

        return back()->with('success', __('carry_forward.flash.resolved'));
    }

    public function destroy(CarryForwardNote $carryForwardNote): RedirectResponse {
        $this->assertSameOrganization($carryForwardNote);
        Gate::authorize('delete', $carryForwardNote);

        $this->notes->withdraw($carryForwardNote);

        return back()->with('success', __('carry_forward.flash.withdrawn'));
    }

    /** Vorkommen aus Formularwerten: nur Träger des Vertrags, nur der eigenen Organisation, nur mit Bearbeitungsrecht. */
    private function occurrence(string $type, string $key): Model&PeriodicOccurrence {
        $class = MorphMap::classFor($type);
        abort_unless($class !== null && is_subclass_of($class, Model::class) && is_subclass_of($class, PeriodicOccurrence::class), 404);

        $subject = (new $class)->resolveRouteBinding($key);
        abort_unless($subject instanceof Model && $subject instanceof PeriodicOccurrence, 404);
        $this->assertSameOrganization($subject);
        Gate::authorize('update', $subject);

        return $subject;
    }
}
