<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceEmergencyReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{AttendanceTerminal, AuditLog, Site, User};
use App\Services\Attendance\EmergencyAttendanceService;
use App\Support\{Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Notfall-Anwesenheitsliste (Feature 103, MVP-518): zeitpunktbezogene
 * Übersicht „Im Gebäude / Außer Haus / Abwesend / Ohne Meldung" für
 * Evakuierungs- und Notfalllagen — Bildschirm, Druck, PDF und CSV.
 *
 * Sensible Übersicht: jeder Abruf wird auditiert
 * (`report.presenceEmergencyViewed` bzw. `report.exported`).
 */
class PresenceEmergencyReportController extends Controller {
    use RendersReportPdf;
    use WritesReportCsv;

    public function __construct(private readonly EmergencyAttendanceService $service) {}

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::ReportPresenceEmergency->value);

        /** @var User $viewer */
        $viewer = $request->user();
        $organizationId = (int) $viewer->organization_id;

        $at = $this->resolveAt($request);
        $siteId = Sqid::decodeOrNumeric(Site::class, (string) $request->query('site'));
        $snapshot = $this->service->snapshot($organizationId, $at, $siteId);

        $filters = [
            'at' => $snapshot['at']->toIso8601String(),
            'site' => $siteId,
        ];

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($snapshot, $filters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->pdfDownload(
                'reports.pdf.presence-emergency',
                ['snapshot' => $snapshot, 'siteId' => $siteId, 'generatedAt' => CarbonImmutable::now()],
                'notfall-anwesenheit-' . $snapshot['at']->setTimezone(Tz::current())->format('Y-m-d-Hi') . '.pdf',
                request: $request,
                reportCode: 'presence-emergency',
                filters: $filters,
            );
        }

        // Jeder Bildschirm-Abruf ist auditpflichtig (sensible Personalübersicht).
        AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $viewer->id,
            'event' => 'report.presenceEmergencyViewed',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => [
                'at' => $filters['at'],
                'site' => $siteId,
                'counts' => [
                    'present' => count($snapshot['present']),
                    'present_unmapped' => count($snapshot['present_unmapped']),
                    'off_site' => count($snapshot['off_site']),
                    'absent' => count($snapshot['absent']),
                    'unaccounted' => count($snapshot['unaccounted']),
                ],
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return view('reports.presence-emergency', [
            'snapshot' => $snapshot,
            'siteId' => $siteId,
            'siteOptions' => $this->siteOptions($organizationId),
            'atLocal' => $snapshot['at']->setTimezone(Tz::current())->format('Y-m-d\TH:i'),
            'generatedAt' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Zeitpunkt aus `at` (datetime-local, lokale Zeitzone) — Default: jetzt.
     */
    private function resolveAt(Request $request): CarbonImmutable {
        $raw = trim((string) $request->query('at', ''));
        if ($raw === '') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($raw, Tz::current())
                ->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    /**
     * Standorte, an denen Stempelterminals hängen — nur dort ist eine
     * Standortzuordnung der Anwesenheit überhaupt möglich.
     *
     * @return list<array{sqid: string, id: int, name: string}>
     */
    private function siteOptions(int $organizationId): array {
        return array_values(Site::query()
            ->whereIn('id', AttendanceTerminal::query()
                ->where('organization_id', $organizationId)
                ->whereNotNull('site_id')
                ->select('site_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $site): array => [
                'sqid' => Sqid::encode(Site::class, $site->id),
                'id' => (int) $site->id,
                'name' => (string) $site->name,
            ])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $snapshot, array $filters, Request $request): SymfonyResponse {
        $rows = [[
            __('reporting.presence_emergency.csv_group'),
            __('reporting.presence_emergency.csv_name'),
            __('reporting.presence_emergency.csv_since'),
            __('reporting.presence_emergency.csv_detail'),
        ]];

        $push = function (string $group, User $user, ?CarbonImmutable $since, ?string $detail) use (&$rows): void {
            $rows[] = [
                $group,
                $user->name,
                $since?->setTimezone(Tz::current())->format('Y-m-d H:i') ?? '',
                $detail ?? '',
            ];
        };

        foreach ($snapshot['present'] as $row) {
            $push(__('reporting.presence_emergency.group_present'), $row['user'], $row['since'], $row['site_name']);
        }
        foreach ($snapshot['present_unmapped'] as $row) {
            $push(__('reporting.presence_emergency.group_present_unmapped'), $row['user'], $row['since'], null);
        }
        foreach ($snapshot['off_site'] as $row) {
            $push(__('reporting.presence_emergency.group_off_site'), $row['user'], $row['since'], $row['context']);
        }
        foreach ($snapshot['absent'] as $row) {
            $push(__('reporting.presence_emergency.group_absent'), $row['user'], null, __('reporting.presence_emergency.reason_' . $row['reason']));
        }
        foreach ($snapshot['unaccounted'] as $row) {
            $push(__('reporting.presence_emergency.group_unaccounted'), $row['user'], null, null);
        }

        $filename = 'notfall-anwesenheit-' . $snapshot['at']->setTimezone(Tz::current())->format('Y-m-d-Hi') . '.csv';

        return $this->csvWithMetadata($rows, $filename, 'presence-emergency', $filters, $request);
    }
}
