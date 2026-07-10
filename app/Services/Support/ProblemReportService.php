<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\Numbering\NumberScope;
use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Enums\Support\{ProblemReportDeliveryTarget, ProblemReportSeverity, ProblemReportStatus};
use App\Http\Middleware\AssignRequestId;
use App\Mail\ProblemReportForwardMail;
use App\Models\{ProblemReport, User};
use App\Services\Diagnostics\DiagnosticsService;
use App\Services\Numbering\NumberSequenceService;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use App\Support\{Setting, UrlSafety};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{File, Http, Log, Mail};

/**
 * Fehlermeldesystem (Feature 041, MVP-053): erzeugt Meldungen mit
 * serverseitig erhobenem Seitenkontext, optionalem REDAKTIERTEM
 * Diagnoseauszug (bestehende SupportReport-Redaktion, keine fachlichen
 * Kundendaten) und stellt sie gemäß Betriebsmodell zu.
 */
class ProblemReportService {
    public const DIAG_MODE_ASK = 'ask';

    public const DIAG_MODE_ALWAYS = 'always';

    public const DIAG_MODE_NEVER = 'never';

    private const LOG_EXCERPT_LINES = 40;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly DiagnosticsService $diagnostics,
        private readonly SupportReportLogFilter $logFilter,
        private readonly OperationsAlertService $alerts,
    ) {}

    /** Org-Regel für den Diagnose-Anhang (ask/always/never). */
    public function diagnosticsMode(): string {
        $mode = (string) Setting::get('support.problem_reports.diagnostics', self::DIAG_MODE_ASK);

        return in_array($mode, [self::DIAG_MODE_ASK, self::DIAG_MODE_ALWAYS, self::DIAG_MODE_NEVER], true)
            ? $mode
            : self::DIAG_MODE_ASK;
    }

    public function deliveryTarget(): ProblemReportDeliveryTarget {
        $configured = (string) Setting::get('support.problem_reports.delivery', ProblemReportDeliveryTarget::SaasInbox->value);

        return ProblemReportDeliveryTarget::tryFrom($configured) ?? ProblemReportDeliveryTarget::SaasInbox;
    }

    /**
     * @param array<string, mixed> $input validierte Formulardaten
     * @param array<string, mixed> $clientContext route/url/topic aus dem Dialog
     * @param list<UploadedFile> $attachments
     */
    public function create(User $reporter, array $input, array $clientContext, array $attachments = []): ProblemReport {
        $includeDiagnostics = match ($this->diagnosticsMode()) {
            self::DIAG_MODE_ALWAYS => true,
            self::DIAG_MODE_NEVER => false,
            default => (bool) ($input['include_diagnostics'] ?? false),
        };

        $report = new ProblemReport([
            'organization_id' => (int) $reporter->organization_id,
            'user_id' => $reporter->id,
            'reference_no' => $this->numbers->next((int) $reporter->organization_id, NumberScope::ProblemReport),
            'status' => ProblemReportStatus::New_,
            'severity' => ProblemReportSeverity::tryFrom((string) ($input['severity'] ?? '')) ?? ProblemReportSeverity::Normal,
            'summary' => (string) $input['summary'],
            'description' => (string) $input['description'],
            'expected_behavior' => $input['expected_behavior'] ?? null,
            'actual_behavior' => $input['actual_behavior'] ?? null,
            'contact_ok' => (bool) ($input['contact_ok'] ?? false),
            'page_context' => $this->buildContext($reporter, $clientContext),
            'diagnostic_excerpt' => $includeDiagnostics ? $this->buildDiagnosticExcerpt() : null,
            'diagnostics_approved_by' => $includeDiagnostics ? $reporter->id : null,
            'delivery_target' => $this->deliveryTarget(),
        ]);
        $report->save();

        foreach ($attachments as $file) {
            $path = $file->storeAs(
                'attachments/' . now()->format('Y/m'),
                uniqid('pr_', true) . '.' . $file->getClientOriginalExtension(),
                'local',
            );
            $report->attachments()->create([
                'organization_id' => $report->organization_id,
                'user_id' => $reporter->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => \App\Support\Filename::sanitize($file->getClientOriginalName()),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $this->deliver($report);
        $this->notifyOperators($report);

        return $report;
    }

    /**
     * Serverseitig verlässlicher Kontext — Client-Angaben werden nur als
     * Zusatzinfo übernommen (Route/Topic), nie als Wahrheitsquelle.
     *
     * @param array<string, mixed> $clientContext
     * @return array<string, mixed>
     */
    private function buildContext(User $reporter, array $clientContext): array {
        return [
            'route' => substr((string) ($clientContext['route'] ?? ''), 0, 150) ?: null,
            'path' => substr(parse_url((string) ($clientContext['url'] ?? ''), PHP_URL_PATH) ?: '', 0, 200) ?: null,
            'help_topic' => substr((string) ($clientContext['help_topic'] ?? ''), 0, 150) ?: null,
            'error_code' => isset($clientContext['error_code']) ? (int) $clientContext['error_code'] : null,
            'role_profile' => $reporter->getRoleNames()->take(3)->implode(','),
            'app_version' => (string) config('app.version'),
            'php_version' => PHP_VERSION,
            'browser' => substr((string) request()->userAgent(), 0, 255),
            'locale' => app()->getLocale(),
            'request_id' => app()->bound(AssignRequestId::CONTAINER_KEY)
                ? (string) app(AssignRequestId::CONTAINER_KEY)
                : null,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Redaktierter Diagnoseauszug (DoD: keine fachlichen Kundendaten):
     * Diagnose-Ampeln + Meldungen sowie die letzten Logzeilen zur
     * Request-ID, gefiltert durch die bestehende SupportReport-Redaktion.
     *
     * @return array<string, mixed>
     */
    public function buildDiagnosticExcerpt(): array {
        $sections = [];
        try {
            foreach ($this->diagnostics->collect()->sections as $section) {
                $sections[$section->code] = [
                    'status' => $section->status->value,
                    'messages' => array_map($this->logFilter->filter(...), $section->messages),
                ];
            }
        } catch (\Throwable $e) {
            $sections['error'] = ['status' => 'unknown', 'messages' => [$e->getMessage()]];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'app_version' => (string) config('app.version'),
            'health' => $sections,
            'log_excerpt' => $this->logExcerptForRequest(),
        ];
    }

    /** @return list<string> */
    private function logExcerptForRequest(): array {
        $requestId = app()->bound(AssignRequestId::CONTAINER_KEY)
            ? (string) app(AssignRequestId::CONTAINER_KEY)
            : null;
        $path = storage_path('logs/laravel.log');
        if ($requestId === null || !File::exists($path)) {
            return [];
        }

        try {
            $lines = array_slice(explode("\n", File::get($path)), -2000);
            $matching = array_values(array_filter(
                $lines,
                static fn(string $line): bool => str_contains($line, $requestId),
            ));

            return $this->logFilter->filterMany(array_slice($matching, -self::LOG_EXCERPT_LINES));
        } catch (\Throwable) {
            return [];
        }
    }

    private function deliver(ProblemReport $report): void {
        try {
            match ($report->delivery_target) {
                // Inbox/Export: die Meldung liegt bereits in der DB — nichts zu senden.
                ProblemReportDeliveryTarget::SaasInbox,
                ProblemReportDeliveryTarget::LocalExport => $report->forceFill(['delivered_at' => now()])->save(),
                ProblemReportDeliveryTarget::Mail => $this->deliverByMail($report),
                ProblemReportDeliveryTarget::Webhook => $this->deliverByWebhook($report),
            };
        } catch (\Throwable $e) {
            $report->forceFill(['delivery_error' => substr($e->getMessage(), 0, 300)])->save();
            Log::warning('problemReport.delivery_failed', [
                'reference' => $report->reference_no,
                'target' => $report->delivery_target->value,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function deliverByMail(ProblemReport $report): void {
        $to = (string) Setting::get('support.problem_reports.mail_to', '');
        if ($to === '') {
            throw new \RuntimeException('Keine Support-Mailadresse konfiguriert (support.problem_reports.mail_to).');
        }
        Mail::to($to)->queue(new ProblemReportForwardMail($report));
        $report->forceFill(['delivered_at' => now()])->save();
    }

    private function deliverByWebhook(ProblemReport $report): void {
        $url = (string) Setting::get('support.problem_reports.webhook_url', '');
        if ($url === '') {
            throw new \RuntimeException('Keine Webhook-URL konfiguriert (support.problem_reports.webhook_url).');
        }
        if (!UrlSafety::isAcceptableExternalHttpUrl($url)) {
            throw new \RuntimeException('Webhook-URL nicht zulässig (SSRF-Schutz).');
        }

        Http::withoutRedirecting()
            ->timeout(10)
            ->post($url, $report->exportPayload())
            ->throw();
        $report->forceFill(['delivered_at' => now()])->save();
    }

    /** Betreiber informieren (installationsweit — SaaS-Inbox/Support). */
    private function notifyOperators(ProblemReport $report): void {
        $this->alerts->report(new OperationsSignal(
            type: OperationsTaskType::ProblemReportOpen,
            dedupeKey: 'problem_report:' . $report->getKey(),
            severity: OperationsTaskSeverity::Info,
            titleKey: 'operations.task.problem_report_open',
            params: [
                'reference' => $report->reference_no,
                'name' => $report->reporter->name ?? '—',
            ],
            organizationId: null,
            linkRoute: 'admin.problem-reports.index',
            message: $report->summary,
        ));
    }
}
