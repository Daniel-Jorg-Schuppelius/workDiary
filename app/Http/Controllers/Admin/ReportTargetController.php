<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetPeriod, ReportTargetScope};
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveReportTargetRequest;
use App\Models\{Customer, Project, ReportTarget, User};
use App\Support\Setting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Feature 002 (Zielwerte & Benchmarks): Pflege der Soll-Werte je Kennzahl.
 * Die Reports lesen diese Werte über {@see \App\Services\Reporting\ReportTargetEvaluator}.
 */
class ReportTargetController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', ReportTarget::class);

        $targets = ReportTarget::query()
            ->with('creator:id,name')
            ->orderBy('metric')
            ->orderBy('scope')
            ->paginate((int) Setting::get('pagination.report_targets', 25))
            ->withQueryString();

        return view('admin.report-targets.index', [
            'targets' => $targets,
            'scopeNames' => $this->scopeNames(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ReportTarget::class);

        return view('admin.report-targets._form_dialog', $this->formData(new ReportTarget));
    }

    public function store(SaveReportTargetRequest $request): RedirectResponse {
        Gate::authorize('create', ReportTarget::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();
        ReportTarget::create($data);

        return redirect()->route('admin.report-targets.index')
            ->with('success', __('reporting.target.created'));
    }

    public function edit(ReportTarget $reportTarget): View {
        Gate::authorize('update', $reportTarget);

        return view('admin.report-targets._form_dialog', $this->formData($reportTarget));
    }

    public function update(SaveReportTargetRequest $request, ReportTarget $reportTarget): RedirectResponse {
        Gate::authorize('update', $reportTarget);

        $reportTarget->update($request->validated());

        return redirect()->route('admin.report-targets.index')
            ->with('success', __('reporting.target.updated'));
    }

    public function destroy(ReportTarget $reportTarget): RedirectResponse {
        Gate::authorize('delete', $reportTarget);

        $reportTarget->delete();

        return redirect()->route('admin.report-targets.index')
            ->with('success', __('reporting.target.deleted'));
    }

    /** @return array<string, mixed> */
    private function formData(ReportTarget $reportTarget): array {
        return [
            'target' => $reportTarget,
            'metricOptions' => ReportTargetMetric::cases(),
            'scopeOptions' => ReportTargetScope::cases(),
            'periodOptions' => ReportTargetPeriod::cases(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Auflösung scope_id → menschenlesbarer Name für die Liste.
     *
     * @return array{customer: array<int, string>, project: array<int, string>, user: array<int, string>}
     */
    private function scopeNames(): array {
        return [
            'customer' => Customer::query()->pluck('name', 'id')->map(static fn($v): string => (string) $v)->all(),
            'project' => Project::query()->pluck('name', 'id')->map(static fn($v): string => (string) $v)->all(),
            'user' => User::query()->pluck('name', 'id')->map(static fn($v): string => (string) $v)->all(),
        ];
    }
}
