<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\ControllerRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Privacy\{ProcessingActivity, ProcessingActivityVersion};
use App\Services\Privacy\{PrivacyExportService, ProcessingActivityService};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Verzeichnis von Verarbeitungstaetigkeiten (DSGVO Art. 30) mit Versionierung,
 * Freigabe und stichtagsbezogenem Export. Organisationsgebunden + policy-gesichert.
 */
class ProcessingActivityController extends Controller {
    public function __construct(
        private readonly ProcessingActivityService $service,
        private readonly PrivacyExportService $exporter,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ProcessingActivity::class);

        $activities = ProcessingActivity::query()->orderBy('name')->paginate(20);

        return view('privacy.activities.index', ['activities' => $activities]);
    }

    public function create(): View {
        Gate::authorize('create', ProcessingActivity::class);

        return view('privacy.activities._form_dialog', ['roles' => ControllerRole::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ProcessingActivity::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $data = $this->validateActivity($request);
        $activity = $this->service->createDraft(
            $org,
            $data['name'],
            $data['purpose'] ?? null,
            ControllerRole::from($data['controller_role']),
            $this->payload($data),
            $request->user(),
            $data['area'] ?? null,
        );

        return redirect()->route('dataprotection.activities.show', $activity)
            ->with('status', __('Verarbeitungstätigkeit angelegt (Entwurf).'));
    }

    public function show(ProcessingActivity $activity): View {
        Gate::authorize('view', $activity);

        return view('privacy.activities.show', [
            'activity' => $activity->load(['currentVersion', 'dpia']),
            'versions' => $activity->versions()->get(),
        ]);
    }

    public function addVersion(Request $request, ProcessingActivity $activity): RedirectResponse {
        Gate::authorize('update', $activity);
        $data = $this->validateActivity($request);
        $this->service->addVersion($activity, $this->payload($data), $request->user(), $request->string('note')->toString() ?: null);

        return back()->with('status', __('Neue Version gespeichert.'));
    }

    public function submitReview(ProcessingActivity $activity): RedirectResponse {
        Gate::authorize('update', $activity);
        $this->service->submitForReview($activity);

        return back()->with('status', __('Zur Prüfung eingereicht.'));
    }

    public function approve(Request $request, ProcessingActivity $activity): RedirectResponse {
        Gate::authorize('approve', $activity);
        $user = $request->user();
        abort_unless($user !== null, 403);
        $data = $request->validate(['version_id' => ['required', 'integer']]);

        $version = ProcessingActivityVersion::query()
            ->where('activity_id', $activity->id)
            ->findOrFail((int) $data['version_id']);
        $this->service->approve($activity, $version, $user);

        return back()->with('status', __('Version freigegeben.'));
    }

    public function export(Request $request): Response|View {
        Gate::authorize('export', ProcessingActivity::class);
        $user = $request->user();
        $org = $user?->organization;
        abort_unless($org !== null, 403);

        $snapshot = $this->exporter->ropaSnapshot($org);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'event' => 'privacy.ropa.exported',
            'auditable_type' => ProcessingActivity::class,
            'auditable_id' => 0,
            'changes' => ['format' => (string) $request->query('format', 'json')],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $date = now()->toDateString();
        return match ((string) $request->query('format', 'json')) {
            'csv' => response($this->toCsv($this->exporter->ropaCsvRows($snapshot)), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="vvt-' . $date . '.csv"',
            ]),
            'print' => view('privacy.activities.print', ['snapshot' => $snapshot]),
            default => response(
                (string) json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="vvt-' . $date . '.json"'],
            ),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function toCsv(array $rows): string {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fputcsv($out, ['name', 'purpose', 'controller_role', 'area', 'status', 'review_due_at', 'dsfa_required', 'version_no']);
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v): string => (string) $v, $row));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** @return array<string, mixed> */
    private function validateActivity(Request $request): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'controller_role' => ['required', 'string'],
            'area' => ['nullable', 'string', 'max:255'],
            'data_categories' => ['nullable', 'string', 'max:5000'],
            'legal_basis' => ['nullable', 'string', 'max:5000'],
            'recipients' => ['nullable', 'string', 'max:5000'],
            'transfers' => ['nullable', 'string', 'max:5000'],
            'retention' => ['nullable', 'string', 'max:5000'],
            'tom' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array {
        return [
            'data_categories' => $data['data_categories'] ?? null,
            'legal_basis' => $data['legal_basis'] ?? null,
            'recipients' => $data['recipients'] ?? null,
            'transfers' => $data['transfers'] ?? null,
            'retention' => $data['retention'] ?? null,
            'tom' => $data['tom'] ?? null,
        ];
    }
}
