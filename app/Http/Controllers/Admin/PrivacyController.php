<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Privacy\PrivacyOverviewService;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request, Response as HttpResponse};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use PDFToolkit\Registries\PDFWriterRegistry;
use Symfony\Component\HttpFoundation\{Response, StreamedResponse};

/**
 * MVP-005: Datenschutzseite für Org-Admins. Aggregiert Status,
 * Datenkategorien, aktive Sessions, API-Tokens, Mandantenexporte und
 * Supportzugriffe auf einer Seite und bietet Widerruf-Aktionen mit
 * Audit-Spur.
 *
 * MVP-327 ergänzt §3.5 (externe Integrationen/Datenflüsse: Config-Dienste
 * plus je Org aktivierte Plugins) und §3.9 (stichtagsbezogener
 * Datenschutzbericht als PDF übers pdf-toolkit, Audit-Event
 * `privacy.report.exported`).
 */
class PrivacyController extends Controller {
    public function __construct(private readonly PrivacyOverviewService $overview) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::PrivacyView->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $data = $this->overview->forUser($user, $organization);

        return view('admin.privacy.index', [
            'organization' => $data['organization'],
            'memberCount' => $data['member_count'],
            'sessions' => $data['sessions'],
            'tokens' => $data['tokens'],
            'sessionUsers' => $data['session_users'],
            'tokenUsers' => $data['token_users'],
            'exports' => $data['exports'],
            'supportAccesses' => $data['support_accesses'],
            'integrations' => $data['integrations'],
            'auditActors' => $data['audit_actors'],
            'categories' => $this->categoriesWithDynamicRetention(),
            'operatingMode' => (string) config('privacy.operating_mode', 'on_premise'),
            'dpaUrl' => config('privacy.dpa_document_url'),
            'canRevokeSessions' => $data['can']['sessions_revoke'],
            'canRevokeTokens' => $data['can']['tokens_revoke'],
            'canViewIntegrations' => $data['can']['integrations_view'],
            'canViewExports' => $data['can']['exports_view'],
            'canViewSupport' => $data['can']['support_view'],
            'canExportReport' => $data['can']['report_export'],
        ]);
    }

    /**
     * §3.9 — Stichtagsbezogener Datenschutzbericht als PDF (MVP-327).
     * Enthält ausschließlich aggregierte Zählungen, Konfigurations- und
     * Audit-Statistiken (Konzept §3.9/§5 — keine personenbezogenen
     * Detaildaten). Rendering übers pdf-toolkit ({@see PDFWriterRegistry},
     * Muster {@see \App\Http\Controllers\Privacy\DpiaController::report()}),
     * Audit-Event `privacy.report.exported` über den Eloquent-Schreibweg
     * der Hash-Kette.
     */
    public function report(Request $request): HttpResponse {
        Gate::authorize(Permission::PrivacyReportExport->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $payload = $this->overview->reportPayload($organization);
        $payload['categories'] = $this->categoriesWithDynamicRetention();
        $payload['dpaUrl'] = config('privacy.dpa_document_url');

        // View→PDF über den zentralen Renderer (C15; Vollaudit 2026-07, N27) — design-frei.
        $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
            \App\Enums\DocumentDesign\RenderDocumentKind::Report,
            'admin.privacy.report-pdf',
            $payload,
            null,
        );

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'privacy.report.exported',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'filter' => 'privacy_report',
                'generated_at' => $payload['generated_at']->toIso8601String(),
                'row_count' => count($payload['integrations']) + count($payload['categories']),
            ],
        ]);

        $filename = sprintf('datenschutzbericht-%d-%s.pdf', $organization->id, now()->format('Y-m-d'));

        return new HttpResponse($bytes, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse {
        Gate::authorize(Permission::PrivacyReportExport->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $format = strtolower((string) $request->query('format', 'json'));
        if (! in_array($format, ['json', 'csv'], true)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Unsupported format.');
        }

        $data = $this->overview->forUser($user, $organization);
        $payload = $this->overview->toExportPayload($data);

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'privacy.overviewExported',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'format' => $format,
                'member_count' => $payload['member_count'],
                'sections' => [
                    'sessions' => count($payload['sessions']),
                    'tokens' => count($payload['tokens']),
                    'exports' => count($payload['exports']),
                    'support_accesses' => count($payload['support_accesses']),
                ],
            ],
        ]);

        $stamp = now()->format('Ymd-His');
        $base = sprintf('privacy-overview-%d-%s', $organization->id, $stamp);

        if ($format === 'json') {
            return new HttpResponse(
                JsonHelper::encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Content-Disposition' => sprintf('attachment; filename="%s.json"', $base),
                ],
            );
        }

        return new StreamedResponse(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, StringHelper::encodeLine(['section', 'id', 'user_id', 'event', 'extra'], ',') . "\r\n");
            foreach ($payload['sessions'] as $s) {
                fwrite($out, StringHelper::encodeLine(['session', $s['id'], $s['user_id'], '', (string) $s['ip_address']], ',') . "\r\n");
            }
            foreach ($payload['tokens'] as $t) {
                fwrite($out, StringHelper::encodeLine(['token', (string) $t['id'], (string) $t['user_id'], '', (string) $t['name']], ',') . "\r\n");
            }
            foreach ($payload['exports'] as $e) {
                fwrite($out, StringHelper::encodeLine(['export', (string) $e['id'], (string) ($e['user_id'] ?? ''), $e['event'], (string) ($e['created_at'] ?? '')], ',') . "\r\n");
            }
            foreach ($payload['support_accesses'] as $a) {
                fwrite($out, StringHelper::encodeLine(['support', (string) $a['id'], (string) ($a['user_id'] ?? ''), $a['event'], (string) ($a['created_at'] ?? '')], ',') . "\r\n");
            }
            fclose($out);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.csv"', $base),
        ]);
    }

    public function destroySession(Request $request, string $id): RedirectResponse {
        Gate::authorize(Permission::PrivacySessionsRevoke->value);

        /** @var User $actor */
        $actor = $request->user();
        $organization = $actor->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $row = DB::table('sessions')->where('id', $id)->first(['id', 'user_id']);
        if ($row === null) {
            return back()->withErrors(['session' => __('Session existiert nicht (mehr).')]);
        }

        $belongsToOrg = User::query()
            ->where('id', $row->user_id)
            ->where('organization_id', $organization->id)
            ->exists();
        abort_unless($belongsToOrg, Response::HTTP_NOT_FOUND);

        DB::table('sessions')->where('id', $id)->delete();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'session.revoked',
            'auditable_type' => User::class,
            'auditable_id' => (int) $row->user_id,
            'changes' => [
                'revoked_user_id' => (int) $row->user_id,
                'by_user_id' => $actor->id,
            ],
        ]);

        return back()->with('success', __('Session widerrufen.'));
    }

    public function destroyToken(Request $request, string $id): RedirectResponse {
        Gate::authorize(Permission::PrivacyTokensRevoke->value);

        /** @var User $actor */
        $actor = $request->user();
        $organization = $actor->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        // Sqid statt roher Token-ID (Enumeration-Schutz); Org-Zugehörigkeit
        // wird weiterhin unten hart geprüft.
        $tokenId = Sqid::decodeOrAbort(PersonalAccessToken::class, $id);

        $row = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->where('tokenable_type', User::class)
            ->first(['id', 'tokenable_id', 'name']);
        if ($row === null) {
            return back()->withErrors(['token' => __('Token existiert nicht (mehr).')]);
        }

        $belongsToOrg = User::query()
            ->where('id', $row->tokenable_id)
            ->where('organization_id', $organization->id)
            ->exists();
        abort_unless($belongsToOrg, Response::HTTP_NOT_FOUND);

        DB::table('personal_access_tokens')->where('id', $tokenId)->delete();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'token.revoked',
            'auditable_type' => User::class,
            'auditable_id' => (int) $row->tokenable_id,
            'changes' => [
                'revoked_token_id' => (int) $row->id,
                'revoked_user_id' => (int) $row->tokenable_id,
                'token_name' => (string) $row->name,
                'by_user_id' => $actor->id,
            ],
        ]);

        return back()->with('success', __('API-Token widerrufen.'));
    }

    /**
     * Kategorien mit dynamischer Aufbewahrungs-Angabe (Restpunkt 67):
     * trägt eine Kategorie eine retention_area, wird Frist+Rechtsgrundlage
     * je Rechtsraum der Organisation aufgelöst; sonst bleibt der statische
     * config-Text.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoriesWithDynamicRetention(): array {
        $registry = app(\App\Services\Privacy\Retention\RetentionRegistry::class);
        $organization = \Illuminate\Support\Facades\Auth::user()?->organization;

        $categories = (array) config('privacy.categories', []);
        foreach ($categories as &$category) {
            $area = $category['retention_area'] ?? null;
            if ($area === null || $organization === null) {
                continue;
            }
            $years = $registry->yearsFor($organization, (string) $area);
            $basis = $registry->basisFor($organization, (string) $area);
            if ($years !== null) {
                $category['retention'] = trim($years . ' Jahre' . ($basis !== null ? ' (' . $basis . ')' : ''));
            }
        }

        return $categories;
    }
}
