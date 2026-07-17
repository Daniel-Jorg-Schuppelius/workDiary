<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\{KnowledgeArticleLink, Problem, ServiceTicket, User};
use App\Services\ServiceTicket\ProblemService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Problem-UI (Feature 065, MVP-156): Ursachenobjekte hinter Incidents —
 * Modal-CRUD, Statuswechsel strikt über {@see ProblemService::TRANSITIONS}
 * (einzige Wahrheit, der Service erzwingt die Matrix inkl. Pflichtfrist
 * beim Lösen), Wirksamkeitsprüfung und idempotente Known-Error-
 * Veröffentlichung in die Wissensbasis.
 */
class ProblemController extends Controller {
    public function __construct(private readonly ProblemService $problems) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Problem::class);

        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $query = Problem::query()
            ->with('owner:id,name')
            ->withCount('tickets');
        if (in_array($status, Problem::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->search($q);
        }

        return view('helpdesk.problems.index', [
            'problems' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => ['status' => $status, 'q' => $q],
            'statusLabels' => $this->statusLabels(),
            'canManage' => Gate::allows('create', Problem::class),
        ]);
    }

    public function show(Problem $problem): View {
        Gate::authorize('view', $problem);

        $problem->load(['owner:id,name', 'tickets:id,ticket_no,title,status', 'changes:id,problem_id,title,status,change_type']);

        return view('helpdesk.problems.show', [
            'problem' => $problem,
            'article' => $this->knownErrorArticle($problem),
            // Statusoptionen aus der Service-Matrix ableiten — NICHT duplizieren.
            'transitions' => ProblemService::TRANSITIONS[$problem->status] ?? [],
            'statusLabels' => $this->statusLabels(),
            'canManage' => Gate::allows('update', $problem),
        ]);
    }

    /** Modal — optional mit vorbelegten Incident-Sqids (?incidents[]=…). */
    public function create(Request $request): View {
        Gate::authorize('create', Problem::class);

        $selected = array_values(array_filter(array_map(
            static fn($sqid): string => (string) $sqid,
            (array) $request->query('incidents', []),
        ), static fn(string $sqid): bool => $sqid !== ''));

        return view('helpdesk.problems._form_dialog', [
            'problem' => new Problem,
            'isEdit' => false,
            'incidentOptions' => $this->incidentOptions(),
            'selectedIncidents' => $selected,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Problem::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'incidents' => ['nullable', 'array', 'max:20'],
            'incidents.*' => ['string'],
        ]);

        $user = $this->actor();
        $tickets = $this->resolveIncidents(array_values((array) ($data['incidents'] ?? [])), $user);

        if ($tickets !== []) {
            $problem = $this->problems->openFromIncidents($tickets, $data['title'], $user, $data['description'] ?? null);
        } else {
            $problem = Problem::query()->create([
                'organization_id' => (int) $user->organization_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'owner_id' => $user->id,
            ]);
            $problem->audit('problem.opened', ['tickets' => []]);
        }

        return redirect()->route('servicedesk.problems.show', $problem)
            ->with('success', __('Problem angelegt.'));
    }

    public function edit(Problem $problem): View {
        Gate::authorize('update', $problem);

        return view('helpdesk.problems._form_dialog', [
            'problem' => $problem,
            'isEdit' => true,
            'incidentOptions' => collect(),
            'selectedIncidents' => [],
        ]);
    }

    public function update(Request $request, Problem $problem): RedirectResponse {
        Gate::authorize('update', $problem);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'evidence' => ['nullable', 'string', 'max:10000'],
            'workaround' => ['nullable', 'string', 'max:10000'],
            'permanent_fix' => ['nullable', 'string', 'max:10000'],
            'visibility' => ['required', 'in:internal,customer'],
        ]);

        $problem->update($data);
        $problem->audit('problem.updated', ['actor' => $this->actor()->id]);

        return redirect()->route('servicedesk.problems.show', $problem)
            ->with('success', __('Problem gespeichert.'));
    }

    /** Statuswechsel — Matrix + Pflichtfrist erzwingt der Service. */
    public function transition(Request $request, Problem $problem): RedirectResponse {
        Gate::authorize('update', $problem);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', Problem::STATUSES)],
            'effectiveness_check_due_at' => ['nullable', 'date', 'required_if:status,resolved'],
        ]);

        $due = $data['effectiveness_check_due_at'] ?? null;

        try {
            $this->problems->transition(
                $problem,
                $data['status'],
                $this->actor(),
                $due !== null ? \Illuminate\Support\Carbon::parse((string) $due) : null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('servicedesk.problems.show', $problem)
            ->with('success', __('Problem-Status aktualisiert.'));
    }

    /** Wirksamkeitsprüfung dokumentieren (stoppt den Fristen-Scanner). */
    public function effectiveness(Request $request, Problem $problem): RedirectResponse {
        Gate::authorize('update', $problem);

        if ($problem->effectiveness_check_due_at === null) {
            return back()->with('error', __('Es ist keine Wirksamkeitsprüfung fällig.'));
        }

        $data = $request->validate([
            'result' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $this->problems->recordEffectiveness($problem, $this->actor(), $data['result']);

        return redirect()->route('servicedesk.problems.show', $problem)
            ->with('success', __('Wirksamkeitsprüfung dokumentiert.'));
    }

    /** Known Error → Wissensartikel; idempotent im Service (MVP-156). */
    public function publish(Problem $problem): RedirectResponse {
        Gate::authorize('update', $problem);

        $this->problems->publishKnownError($problem, $this->actor());

        return redirect()->route('servicedesk.problems.show', $problem)
            ->with('success', __('Known-Error-Artikel veröffentlicht.'));
    }

    /** @return array<string, string> Labels je Problem-Status (Strings, kein Enum). */
    private function statusLabels(): array {
        return [
            'open' => (string) __('Offen'),
            'analyzing' => (string) __('In Analyse'),
            'known_error' => (string) __('Known Error'),
            'resolved' => (string) __('Gelöst'),
            'closed' => (string) __('Geschlossen'),
        ];
    }

    /**
     * Die 100 jüngsten Incidents der Org als Verknüpfungs-Angebot.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ServiceTicket>
     */
    private function incidentOptions(): \Illuminate\Database\Eloquent\Collection {
        return ServiceTicket::query()
            ->where('kind', \App\Enums\ServiceTicket\ServiceTicketKind::Incident->value)
            ->orderByDesc('reported_at')
            ->limit(100)
            ->get(['id', 'ticket_no', 'title']);
    }

    /**
     * Incident-Sqids STRIKT mit der Zielklasse dekodieren und org-gescopt
     * auflösen — fremde Organisationen enden als Validierungsfehler (der
     * Service hält als zweite Linie die harte Tenant-Grenze).
     *
     * @param  list<mixed>  $sqids
     * @return array<int, ServiceTicket>
     */
    private function resolveIncidents(array $sqids, User $user): array {
        $tickets = [];
        foreach (array_unique(array_filter($sqids)) as $sqid) {
            $id = Sqid::decode(ServiceTicket::class, (string) $sqid);
            $ticket = $id !== null
                ? ServiceTicket::query()
                    ->whereKey($id)
                    ->where('organization_id', (int) $user->organization_id)
                    ->first()
                : null;
            if ($ticket === null) {
                throw ValidationException::withMessages([
                    'incidents' => (string) __('Bitte nur Incidents der eigenen Organisation verknüpfen.'),
                ]);
            }
            $tickets[] = $ticket;
        }

        return $tickets;
    }

    private function knownErrorArticle(Problem $problem): ?\App\Models\KnowledgeArticle {
        return KnowledgeArticleLink::query()
            ->where('linkable_type', $problem->getMorphClass())
            ->where('linkable_id', $problem->id)
            ->first()?->article()->first();
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
