<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchBoardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Diary\{DispatchStatus, Mode, Priority};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Customer, User};
use App\Services\Dispatch\DispatchBoardService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Leitstellen-Ansicht (Feature 029): Dispatch-Board und Karten-Sicht mit
 * SLA-Risiko-Filter. Reine VISUALISIERUNG vorhandener Plan-/Geo-/Statusdaten —
 * keine Tourenoptimierung, kein Echtzeit-Tracking, keine Standortüberwachung.
 *
 * Rechte: dispatch.viewAny (Feature 028) — Board UND Karte teilen sich die
 * Permission. Plan-Gating: module.planung (über config/plans.php, Muster
 * dispatch.*). Cross-Org-Isolation läuft automatisch über den
 * OrganizationScope der Modelle.
 */
class DispatchBoardController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly DispatchBoardService $board) {}

    /** Kompakte Tagesübersicht: Aufträge gruppiert nach Status oder Mitarbeiter. */
    public function board(Request $request): View {
        $this->authorizeView();

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $entries = $this->board->entries($from, $to, $target?->id);
        $items = $this->board->items($entries);

        // Status-Filter (Dispositionsstatus) wirkt rein auf der Anzeige.
        $statusFilter = $this->resolveStatusFilter($request);
        if ($statusFilter !== null) {
            $items = array_values(array_filter(
                $items,
                static fn(array $i): bool => $i['dispatch'] === $statusFilter,
            ));
        }

        // Vollaudit 2026-07 (M13): Prioritäts-Filter (MVP-Filterliste).
        $priorityFilter = $this->resolvePriorityFilter($request);
        if ($priorityFilter !== null) {
            $items = array_values(array_filter(
                $items,
                static fn(array $i): bool => $i['entry']->priority === $priorityFilter,
            ));
        }

        $groupBy = $request->query('group') === 'employee' ? 'employee' : 'status';

        return view('dispatch.board', [
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
            'columns' => $this->board->groupByDispatchStatus($items),
            'employees' => $this->board->groupByEmployee($items),
            'statusOptions' => DispatchStatus::cases(),
            'selectedStatus' => $statusFilter,
            'priorityOptions' => Priority::cases(),
            'selectedPriority' => $priorityFilter,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'total' => count($items),
        ]);
    }

    /**
     * Kalender-/Tagesansicht (Rang 52): Zeilen = Mitarbeitende, Zellen =
     * geplante Aufträge mit Dispositions-Tone + SLA-Risiko-Marker; Klick
     * öffnet den Auftrag. Fenster wird auf 14 Tage gekappt (Lesbarkeit).
     */
    public function calendar(Request $request): View {
        $this->authorizeView();

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $capped = false;
        if ($from->diffInDays($to) > 13) {
            $to = $from->addDays(13);
            $capped = true;
        }

        $entries = $this->board->entries($from, $to, $target?->id);
        $items = $this->board->items($entries);
        $matrix = $this->board->calendar($items, $from, $to);

        return view('dispatch.calendar', [
            'from' => $from,
            'to' => $to,
            'capped' => $capped,
            'days' => $matrix['days'],
            'rows' => $matrix['rows'],
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'total' => count($items),
        ]);
    }

    /**
     * Auftrags-Qualifikationsmatrix (Rang 53): Anforderungen des Auftrags ×
     * Mitarbeitende; Zellen erfüllt/läuft ab (< 30 Tage)/fehlt — Datenquelle
     * ausschließlich {@see \App\Services\Schedule\QualificationGate}.
     */
    public function qualifications(\App\Models\DiaryEntry $diary, \App\Services\Schedule\QualificationGate $gate): View {
        $this->authorizeView();

        $required = $diary->requiredQualifications()->orderBy('name')->get();
        $date = $diary->start_at !== null ? \Carbon\CarbonImmutable::parse((string) $diary->start_at) : null;

        $rows = [];
        if ($required->isNotEmpty()) {
            $users = User::inCurrentOrganization()->with('qualifications')->orderBy('name')->get();
            foreach ($users as $user) {
                $rows[] = [
                    'user' => $user,
                    'status' => $gate->statusFor($user, $required, $date),
                ];
            }
        }

        return view('dispatch.qualifications', [
            'diary' => $diary,
            'required' => $required,
            'rows' => $rows,
            'date' => $date,
        ]);
    }

    /** Karten-Sicht: Auftrags-Marker nach Disposition/SLA-Risiko. */
    public function map(Request $request): View {
        $this->authorizeView();

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $entries = $this->board->entries($from, $to, $target?->id);
        $items = $this->board->items($entries);

        $onlyRisk = $request->boolean('risk');
        $onlyUnconfirmed = $request->boolean('unconfirmed');
        $priorityFilter = $this->resolvePriorityFilter($request);

        $markers = [];
        foreach ($items as $item) {
            $entry = $item['entry'];
            $sla = $item['sla'];
            $dispatch = $item['dispatch'];

            $isRisk = in_array($sla->value, ['atRisk', 'breached'], true);
            $isUnconfirmed = in_array($dispatch, [DispatchStatus::Unplanned, DispatchStatus::Planned], true);

            if ($onlyRisk && ! $isRisk) {
                continue;
            }
            if ($onlyUnconfirmed && ! $isUnconfirmed) {
                continue;
            }
            if ($priorityFilter !== null && $entry->priority !== $priorityFilter) {
                continue;
            }

            [$lat, $lng] = $this->coordinatesFor($entry);
            if ($lat === null || $lng === null) {
                continue;
            }

            // Vollaudit 2026-07 (M13): Layer nach Terminmodus — Disponenten
            // dürfen harte Kundentermine nicht mit flexiblen verwechseln;
            // SLA-Risiko bleibt der oberste Layer. (M14): Titel verlinkt den
            // Auftrag, damit Kartenpunkte zur Akte führen.
            $layer = $isRisk ? 'risk' : $this->modeLayer($entry->mode);
            $popup = '<a href="' . e(route('diary.show', $entry)) . '" class="link">' . e((string) $entry->title) . '</a>'
                . '<br>' . e($entry->mode->label())
                . '<br>' . e($dispatch->label())
                . ($isRisk ? '<br><strong>' . e($sla->label()) . '</strong>' : '');

            $markers[] = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'label' => $entry->title,
                'color' => $this->board->markerColor($sla, $dispatch),
                'layer' => $layer,
                'popup' => $popup,
            ];
        }

        $layers = [
            ['key' => 'risk', 'label' => __('SLA-Risiko'), 'color' => '#dc2626'],
            ['key' => 'fixed', 'label' => __('Feste Termine'), 'color' => '#2563eb'],
            ['key' => 'flexible', 'label' => __('Flexible Zeitfenster'), 'color' => '#0d9488'],
            ['key' => 'backlog', 'label' => __('Backlog-Kandidaten'), 'color' => '#64748b'],
        ];

        return view('dispatch.map', [
            'from' => $from,
            'to' => $to,
            'markers' => $markers,
            'layers' => $layers,
            'onlyRisk' => $onlyRisk,
            'onlyUnconfirmed' => $onlyUnconfirmed,
            'priorityOptions' => Priority::cases(),
            'selectedPriority' => $priorityFilter,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'markerCount' => count($markers),
        ]);
    }

    /**
     * Auftragskoordinaten: eigener Standort, sonst über den Kunden verorten.
     *
     * @return array{0: float|string|null, 1: float|string|null}
     */
    private function coordinatesFor(\App\Models\DiaryEntry $entry): array {
        if ($entry->address_lat !== null && $entry->address_lng !== null) {
            return [$entry->address_lat, $entry->address_lng];
        }

        $customer = $entry->customer;
        if ($customer instanceof Customer && $customer->address_lat !== null && $customer->address_lng !== null) {
            return [$customer->address_lat, $customer->address_lng];
        }

        return [null, null];
    }

    private function authorizeView(): void {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->can(Permission::DispatchViewAny->value)) {
            abort(403);
        }
    }

    private function resolveStatusFilter(Request $request): ?DispatchStatus {
        if (! $request->filled('status')) {
            return null;
        }

        return DispatchStatus::tryFrom((string) $request->query('status'));
    }

    private function resolvePriorityFilter(Request $request): ?Priority {
        if (! $request->filled('priority')) {
            return null;
        }

        return Priority::tryFrom((string) $request->query('priority'));
    }

    /** Karten-Layer je Terminmodus (M13): fest / flexibel / Backlog. */
    private function modeLayer(Mode $mode): string {
        return match ($mode) {
            Mode::Fixed => 'fixed',
            Mode::Deadline, Mode::Window, Mode::Recurring => 'flexible',
            Mode::Backlog => 'backlog',
        };
    }

    /**
     * Standard-Scope der Leitstelle ist der GESAMTE Mandant (wer
     * dispatch.viewAny hält, ist Disponent und sieht alle Aufträge). Der
     * `user`-Filter schränkt optional auf einen Mitarbeiter ein. Cross-Org
     * bleibt über den OrganizationScope der Modelle ausgeschlossen — ein
     * fremder Mitarbeiter liefert schlicht keine Treffer.
     */
    private function resolveTargetUser(Request $request, User $authUser): ?User {
        $raw = (string) $request->query('user', 'all');
        if ($raw === '' || $raw === 'all') {
            return null;
        }

        $requestedId = Sqid::decodeOrNumeric(User::class, $raw);
        if ($requestedId === null) {
            return null;
        }

        // Mandantengrenze: nur Nutzer der eigenen Organisation (User hat keinen
        // globalen OrganizationScope — Whitebox-Befund 2026-07).
        $target = User::query()
            ->where('organization_id', $authUser->organization_id)
            ->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }

    /** @return Collection<int, User> */
    private function loadSelectableUsers(): Collection {
        $authUser = auth()->user();
        $orgId = $authUser instanceof User ? $authUser->organization_id : null;

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->when($orgId !== null, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return $users;
    }
}
