<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Support\{ProblemReportSeverity, ProblemReportStatus};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\ProblemReport;
use App\Support\Setting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Betreiber-/Admin-Inbox für Fehlermeldungen (Feature 041, MVP-053).
 * Org-gescoped (Selbst-Hosting: Betreiber = Org-Admin); Übernahme in
 * den Helpdesk nur bei aktivem module.helpdesk — kein zweites
 * Ticketmodell.
 */
class ProblemReportInboxController extends Controller {
    public function index(Request $request): View {
        Gate::authorize(Permission::ProblemReportManage->value);

        $status = ProblemReportStatus::tryFrom((string) $request->query('status', ''));

        $reports = ProblemReport::query()
            ->when($status !== null, fn($query) => $query->where('status', $status?->value))
            ->latest()
            ->paginate((int) Setting::get('pagination.notifications', 25))
            ->withQueryString();

        return view('admin.problem-reports.index', [
            'reports' => $reports,
            'statusFilter' => $status,
            'openCount' => ProblemReport::query()->where('status', '!=', ProblemReportStatus::Closed->value)->count(),
        ]);
    }

    public function show(ProblemReport $problemReport): View {
        Gate::authorize(Permission::ProblemReportManage->value);

        return view('admin.problem-reports.show', [
            'report' => $problemReport->load(['reporter', 'attachments']),
            'canConvert' => app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.helpdesk')
                && $problemReport->external_ref === null,
        ]);
    }

    public function updateStatus(Request $request, ProblemReport $problemReport): RedirectResponse {
        Gate::authorize(Permission::ProblemReportManage->value);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column(ProblemReportStatus::cases(), 'value'))],
        ]);

        $problemReport->update(['status' => ProblemReportStatus::from($validated['status'])]);

        return redirect()->route('admin.problem-reports.show', $problemReport)
            ->with('status', __('problemreport.flash.status_updated'));
    }

    /** Übernahme als Helpdesk-Ticket (idempotent via external_ref). */
    public function convertToTicket(Request $request, ProblemReport $problemReport): RedirectResponse {
        Gate::authorize(Permission::ProblemReportManage->value);
        abort_unless(app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.helpdesk'), 423);

        if ($problemReport->external_ref !== null) {
            return redirect()->route('admin.problem-reports.show', $problemReport)
                ->with('status', __('problemreport.flash.already_converted', ['reference' => $problemReport->external_ref]));
        }

        $organization = $problemReport->organization;
        abort_if($organization === null, 404);

        $ticket = app(\App\Services\ServiceTicket\ServiceTicketService::class)->create(
            $organization,
            $request->user(),
            [
                'title' => '[' . $problemReport->reference_no . '] ' . $problemReport->summary,
                'description' => $problemReport->description,
                'priority' => $this->ticketPriorityFor($problemReport->severity),
            ],
        );

        $problemReport->update([
            'external_ref' => $ticket->ticket_no,
            'status' => ProblemReportStatus::InReview,
        ]);

        return redirect()->route('admin.problem-reports.show', $problemReport)
            ->with('status', __('problemreport.flash.converted', ['reference' => $ticket->ticket_no]));
    }

    public function download(ProblemReport $problemReport): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize(Permission::ProblemReportManage->value);

        return response()->streamDownload(
            static function () use ($problemReport): void {
                echo json_encode($problemReport->exportPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            },
            $problemReport->reference_no . '.json',
            ['Content-Type' => 'application/json'],
        );
    }

    private function ticketPriorityFor(ProblemReportSeverity $severity): string {
        return match ($severity) {
            ProblemReportSeverity::Low => 'low',
            ProblemReportSeverity::Normal => 'normal',
            ProblemReportSeverity::High, ProblemReportSeverity::Blocking => 'high',
        };
    }
}
