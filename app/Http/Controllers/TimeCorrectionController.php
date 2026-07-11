<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission;
use App\Models\{Attendance, TimeCorrectionRequest, TimeEntry, User};
use App\Services\TimeApproval\{TimeCorrectionService, TimeCorrectionWorkflowException};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Mitarbeiter-Ansicht für Zeit-Korrekturanträge (MVP-017).
 *
 * Listet eigene Anträge, erlaubt das Anlegen neuer Entwürfe sowie das
 * Einreichen und Zurückziehen. Admin-Entscheidungen liegen im
 * {@see Admin\TimeCorrectionInboxController}.
 */
class TimeCorrectionController extends Controller {
    private const ALLOWED_SORTS = ['scope_date', 'status'];

    public function __construct(private readonly TimeCorrectionService $service) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        Gate::authorize('viewAny', TimeCorrectionRequest::class);

        $statusFilter = (string) $request->input('status', '');

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'scope_date';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = TimeCorrectionRequest::query()
            ->where('organization_id', $user->organization_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('requested_by_user_id', $user->id);
            })
            ->with(['user', 'items'])
            ->orderBy($sort, $dir)
            ->orderByDesc('id');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('time-approval.correction.index', [
            'requests' => $requests,
            'filters' => ['status' => $statusFilter],
            'statuses' => TimeCorrectionStatus::cases(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', TimeCorrectionRequest::class);
        /** @var User $user */
        $user = Auth::user();

        $scopeDate = $request->filled('date')
            ? (CarbonImmutable::parse((string) $request->input('date')))
            : CarbonImmutable::now()->subDay();

        // Personalverwaltung/Teamleitung dürfen im Namen von Mitarbeitenden nachtragen.
        $canCreateForOthers = $user->can(Permission::CorrectionCreateForOthers->value);
        $members = $canCreateForOthers
            ? User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name'])
            : collect();

        return view('time-approval.correction._form_dialog', [
            'isDialog' => true,
            'scopeDate' => $scopeDate,
            'canCreateForOthers' => $canCreateForOthers,
            'members' => $members,
            'targetTypes' => [
                TimeEntry::class => __('Zeitbuchung'),
                Attendance::class => __('Anwesenheit'),
            ],
            'actions' => [
                'create' => __('Anlegen'),
                'update' => __('Ändern'),
                'delete' => __('Löschen'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TimeCorrectionRequest::class);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'scope_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.target_type' => ['required', 'string', 'in:' . TimeEntry::class . ',' . Attendance::class],
            'items.*.target_id' => ['nullable', 'integer'],
            'items.*.action' => ['required', 'string', 'in:create,update,delete'],
            'items.*.before' => ['nullable', 'string'],
            'items.*.after' => ['nullable', 'string'],
        ]);

        // „Im Namen von": Eigentümer = Mitarbeiter, Antragsteller = aktueller Nutzer.
        // Nur mit Permission und nur innerhalb derselben Organisation.
        $owner = $user;
        if (! empty($data['user_id']) && (int) $data['user_id'] !== (int) $user->id) {
            abort_unless($user->can(Permission::CorrectionCreateForOthers->value), 403);
            $owner = User::query()
                ->where('organization_id', $user->organization_id)
                ->findOrFail((int) $data['user_id']);
        }

        $items = [];
        foreach ($data['items'] as $row) {
            $items[] = [
                'target_type' => $row['target_type'],
                'target_id' => isset($row['target_id']) ? (int) $row['target_id'] : null,
                'action' => $row['action'],
                'before' => self::decodeJson($row['before'] ?? null),
                'after' => self::decodeJson($row['after'] ?? null),
            ];
        }

        try {
            $req = $this->service->createDraft(
                $owner,
                CarbonImmutable::parse($data['scope_date']),
                $data['reason'],
                $items,
                $user,
            );
        } catch (TimeCorrectionWorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('corrections.show', $req)
            ->with('status', __('Korrekturantrag als Entwurf gespeichert.'));
    }

    public function show(TimeCorrectionRequest $correction): View {
        Gate::authorize('view', $correction);

        return view('time-approval.correction.show', [
            'request' => $correction->load(['items', 'user', 'requestedBy', 'decidedBy']),
        ]);
    }

    public function submit(TimeCorrectionRequest $correction): RedirectResponse {
        Gate::authorize('submit', $correction);
        /** @var User $user */
        $user = Auth::user();

        try {
            $correction = $this->service->submit($correction, $user);

            // Selbstkorrektur-Modus: Eigenkorrekturen direkt anwenden (Manual).
            if ($this->service->selfApplicable($correction)) {
                $this->service->selfApply($correction);

                return back()->with('status', __('Vergessene Stempelung wurde nachgetragen (manuell, selbst nachgetragen).'));
            }
        } catch (TimeCorrectionWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Antrag eingereicht.'));
    }

    public function withdraw(TimeCorrectionRequest $correction): RedirectResponse {
        Gate::authorize('withdraw', $correction);
        /** @var User $user */
        $user = Auth::user();

        try {
            $this->service->withdraw($correction, $user);
        } catch (TimeCorrectionWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Antrag zurückgezogen.'));
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(?string $raw): ?array {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        try {
            $decoded = JsonHelper::decode($raw);

            return is_array($decoded) ? $decoded : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
