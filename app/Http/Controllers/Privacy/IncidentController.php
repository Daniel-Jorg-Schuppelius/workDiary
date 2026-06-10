<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\IncidentType;
use App\Http\Controllers\Controller;
use App\Models\Privacy\{Incident, Measure};
use App\Services\Privacy\IncidentService;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Datenschutzvorfaelle (Art. 33/34) mit zeitkritischem 72-h-Workflow. */
class IncidentController extends Controller {
    public function __construct(private readonly IncidentService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', Incident::class);

        return view('privacy.incidents.index', [
            'incidents' => Incident::query()->latest('discovered_at')->paginate(20),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Incident::class);

        return view('privacy.incidents._form_dialog', ['types' => IncidentType::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Incident::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'summary' => ['required', 'string', 'max:20000'],
            'affected' => ['nullable', 'string', 'max:20000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $incident = $this->service->open(
            $org,
            IncidentType::from($data['type']),
            $data['summary'],
            $data['affected'] ?? null,
            isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
            $request->user(),
        );

        return redirect()->route('dataprotection.incidents.show', $incident)
            ->with('status', __('Datenschutzvorfall angelegt – 72-h-Frist läuft.'));
    }

    public function show(Incident $incident): View {
        Gate::authorize('view', $incident);

        return view('privacy.incidents.show', [
            'incident' => $incident->load(['assignedUser', 'measures']),
            'events' => $incident->events()->get(),
        ]);
    }

    public function assess(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $data = $request->validate([
            'risk_level' => ['required', 'in:low,medium,high'],
            'measures' => ['nullable', 'string', 'max:20000'],
        ]);
        $this->service->assess($incident, $data['risk_level'], $data['measures'] ?? null, $request->user());

        return back()->with('status', __('Risikobewertung gespeichert.'));
    }

    public function decideNotification(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $this->service->decideNotification($incident, $request->boolean('authority'), $request->boolean('subjects'), $request->user());

        return back()->with('status', __('Meldeentscheidung dokumentiert.'));
    }

    public function markReported(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $this->service->markReported($incident, $request->boolean('authority'), $request->boolean('subjects'), $request->user());

        return back()->with('status', __('Meldung vermerkt.'));
    }

    public function close(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $data = $request->validate(['lessons' => ['nullable', 'string', 'max:20000']]);
        $this->service->close($incident, $data['lessons'] ?? null, $request->user());

        return redirect()->route('dataprotection.incidents.show', $incident)
            ->with('status', __('Vorfall abgeschlossen.'));
    }

    public function storeMeasure(Request $request, Incident $incident): RedirectResponse {
        Gate::authorize('update', $incident);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ]);
        $this->service->addMeasure(
            $incident,
            $data['title'],
            $data['description'] ?? null,
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
            $request->user(),
        );

        return back()->with('status', __('Maßnahme erfasst.'));
    }

    public function completeMeasure(Request $request, Incident $incident, Measure $measure): RedirectResponse {
        Gate::authorize('update', $incident);
        abort_unless((int) $measure->incident_id === (int) $incident->id, 404);
        $this->service->completeMeasure($measure, $request->user());

        return back()->with('status', __('Maßnahme erledigt.'));
    }

    /**
     * Vorbereiteter Meldungsentwurf (Art. 33 Behörde / Art. 34 Betroffene) als
     * Textdatei – bewusst NICHT automatisch versendet.
     */
    public function reportDraft(Incident $incident, string $kind): Response {
        Gate::authorize('view', $incident);
        abort_unless(in_array($kind, ['authority', 'subjects'], true), 404);

        $isAuthority = $kind === 'authority';
        $lines = $isAuthority
            ? [
                __('ENTWURF – Meldung einer Verletzung des Schutzes personenbezogener Daten (Art. 33 DSGVO)'),
                '',
                __('Aktenzeichen') . ': ' . $incident->incident_number,
                __('Art des Vorfalls') . ': ' . $incident->type->label(),
                __('Zeitpunkt der Entdeckung') . ': ' . $incident->discovered_at?->format('d.m.Y H:i'),
                __('Meldefrist (72 h)') . ': ' . $incident->authority_deadline_at?->format('d.m.Y H:i'),
                __('Risikoeinstufung') . ': ' . ($incident->risk_level ?? '—'),
                __('Zahl betroffener Personen (ca.)') . ': ' . ($incident->affected_count ?? '—'),
                '',
                __('Beschreibung des Vorfalls') . ':',
                (string) ($incident->summary_ciphertext ?? ''),
                '',
                __('Betroffene Datenkategorien / Systeme') . ':',
                (string) ($incident->affected_ciphertext ?? ''),
                '',
                __('Wahrscheinliche Folgen und ergriffene/vorgeschlagene Maßnahmen') . ':',
                (string) ($incident->measures_ciphertext ?? ''),
            ]
            : [
                __('ENTWURF – Benachrichtigung betroffener Personen (Art. 34 DSGVO)'),
                '',
                __('Sehr geehrte Damen und Herren,'),
                '',
                __('wir informieren Sie über einen Vorfall, der den Schutz Ihrer personenbezogenen Daten betrifft.'),
                '',
                __('Art des Vorfalls') . ': ' . $incident->type->label(),
                __('Voraussichtliche Folgen / empfohlene Schutzmaßnahmen') . ':',
                (string) ($incident->measures_ciphertext ?? ''),
                '',
                __('Für Rückfragen steht Ihnen unser Datenschutzteam zur Verfügung.'),
            ];

        $body = implode("\n", $lines);
        $name = $incident->incident_number . '-' . ($isAuthority ? 'meldung-aufsicht' : 'benachrichtigung-betroffene') . '.txt';

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }
}
