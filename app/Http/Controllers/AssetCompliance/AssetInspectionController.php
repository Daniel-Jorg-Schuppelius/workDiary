<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\AssetCompliance\{AssetInspectionResult, AssetInspectionScheduleStatus};
use App\Http\Controllers\Controller;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceProfile, AssetComplianceRequirement, AssetInspectionSchedule};
use App\Models\{ExternalContact, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Attachments\FileAttacher;
use App\Support\Sqid;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Prüfplanung und -durchführung (MVP-285/286/287): Prüfkalender mit
 * Terminen, internen/externen Prüfern und Nachweisanforderung;
 * Prüfprotokolle mit Messwerten, Grenzwerten (P2-Snapshot), Zertifikat,
 * Ergebnis und Folgeentscheidung (MVP-289).
 */
class AssetInspectionController extends Controller {
    public function __construct(private readonly AssetComplianceService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $statusValue = AssetInspectionScheduleStatus::tryFrom($request->string('status')->toString())?->value;

        return view('asset-compliance.schedules', [
            'schedules' => AssetInspectionSchedule::query()
                ->with(['asset', 'assignment.profile', 'inspector', 'externalContact'])
                ->when($statusValue !== null, fn($q) => $q->where('status', $statusValue), fn($q) => $q->open())
                ->orderBy('due_on')
                ->paginate(50)
                ->withQueryString(),
            'assignments' => AssetComplianceAssignment::query()
                ->active()
                ->with(['asset', 'profile'])
                ->orderBy('next_due_on')
                ->get(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'externalContacts' => ExternalContact::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'results' => AssetInspectionResult::cases(),
        ]);
    }

    /** Prüftermin planen (MVP-285). */
    public function storeSchedule(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        foreach (['assignment_id' => AssetComplianceAssignment::class, 'inspector_user_id' => User::class, 'external_contact_id' => ExternalContact::class] as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $data = $request->validate([
            'assignment_id' => ['required', 'integer', new ExistsInCurrentOrganization('asset_compliance_assignments')],
            'due_on' => ['required', 'date'],
            'planned_on' => ['nullable', 'date'],
            'inspector_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'external_contact_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('external_contacts')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = AssetComplianceAssignment::query()->whereKey($data['assignment_id'])->firstOrFail();

        AssetInspectionSchedule::query()->create([
            'organization_id' => $assignment->organization_id,
            'asset_compliance_assignment_id' => $assignment->id,
            'asset_id' => $assignment->asset_id,
            'due_on' => $data['due_on'],
            'planned_on' => $data['planned_on'] ?? null,
            'inspector_user_id' => $data['inspector_user_id'] ?? null,
            'external_contact_id' => $data['external_contact_id'] ?? null,
            'status' => AssetInspectionScheduleStatus::Planned->value,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('Prüftermin geplant.'));
    }

    /** Prüfung erfassen (MVP-286/287) mit Folgeentscheidung (MVP-289). */
    public function record(Request $request, AssetComplianceAssignment $assignment): RedirectResponse {
        Gate::authorize('inspect', AssetComplianceProfile::class);

        if ($request->filled('schedule_id')) {
            $request->merge(['schedule_id' => Sqid::decodeOrNumeric(AssetInspectionSchedule::class, $request->input('schedule_id'))]);
        }

        // Formular liefert Requirement-Sqids (Konvention: Sqid in Formularen) —
        // vor der Validierung auf die numerischen IDs zurückführen.
        $results = $request->input('results');
        if (is_array($results)) {
            foreach ($results as $i => $row) {
                if (is_array($row) && isset($row['requirement_id']) && $row['requirement_id'] !== '') {
                    $results[$i]['requirement_id'] = Sqid::decodeOrNumeric(AssetComplianceRequirement::class, $row['requirement_id']);
                }
            }
            $request->merge(['results' => $results]);
        }

        $data = $request->validate([
            'schedule_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('asset_inspection_schedules')],
            'performed_at' => ['nullable', 'date'],
            'result' => ['required', Rule::enum(AssetInspectionResult::class)],
            'valid_until' => ['nullable', 'date'],
            'external_inspector_name' => ['nullable', 'string', 'max:255'],
            'signature_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            // Prüfkosten (MVP-291; Vollaudit 2026-07, M33).
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'results' => ['sometimes', 'array'],
            'results.*.requirement_id' => ['nullable', 'integer'],
            'results.*.label' => ['nullable', 'string', 'max:255'],
            'results.*.value' => ['nullable', 'numeric'],
            'results.*.unit' => ['nullable', 'string', 'max:30'],
            'measurements' => ['sometimes', 'array'],
            'measurements.*.label' => ['nullable', 'string', 'max:255'],
            'measurements.*.value' => ['nullable', 'numeric'],
            'measurements.*.unit' => ['nullable', 'string', 'max:30'],
            'follow_up' => ['nullable', Rule::in(['none', 'recalibration', 'repair', 'restricted', 'block', 'decommission', 'claim'])],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
            'certificate_no' => ['nullable', 'string', 'max:255'],
            'certificate_issuer' => ['nullable', 'string', 'max:255', 'required_with:certificate_no'],
            'certificate_issued_on' => ['nullable', 'date'],
            'certificate_valid_until' => ['nullable', 'date'],
            'certificate_measurement_range' => ['nullable', 'string', 'max:255'],
            'certificate_tolerance' => ['nullable', 'string', 'max:255'],
            'certificate_file' => ['nullable', ...FileAttacher::rule()],
        ]);

        if (! empty($data['certificate_no'])) {
            $data['certificate'] = [
                'certificate_no' => $data['certificate_no'],
                'issuer' => $data['certificate_issuer'] ?? '',
                'issued_on' => $data['certificate_issued_on'] ?? now()->toDateString(),
                'valid_until' => $data['certificate_valid_until'] ?? null,
                'measurement_range' => $data['certificate_measurement_range'] ?? null,
                'tolerance' => $data['certificate_tolerance'] ?? null,
                'sha256' => $request->hasFile('certificate_file')
                    ? File::hash((string) $request->file('certificate_file')->getRealPath())
                    : null,
            ];
        }

        $actor = $request->user() ?? abort(401);

        try {
            $event = $this->service->recordInspection($assignment, $actor, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['certificate_no' => $e->getMessage()]);
        }

        if ($request->hasFile('certificate_file')) {
            app(FileAttacher::class)->store($event, $request->file('certificate_file'), $actor->id);
        }

        return back()->with('status', __('Prüfung dokumentiert — Nachweis ist unveränderbar.'));
    }
}
