<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Support\{SupportReportBuilder, SupportReportPackager};
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupportReportController extends Controller {
    use RequiresPlatformOperator;

    use ResolvesCurrentOrganization;

    public function index(Request $request, SupportReportBuilder $builder, SupportReportPackager $packager): View {
        Gate::authorize(Permission::PlatformSupportExport->value);

        // Inhalts-Übersicht (Spec §5): bauen, aber nicht packen.
        $bundle = $builder->build();
        $preview = $packager->preview($bundle);

        return view('admin.support.report', [
            'preview' => $preview,
            'canExportWithSamples' => $this->isPlatformOperator()
                && ($request->user()?->can(Permission::PlatformSupportExportWithSamples->value) ?? false),
        ]);
    }

    public function generate(Request $request, SupportReportBuilder $builder, SupportReportPackager $packager): BinaryFileResponse|RedirectResponse {
        Gate::authorize(Permission::PlatformSupportExport->value);

        /** @var User $user */
        $user = $request->user();

        $includeSamples = (bool) $request->boolean('include_samples');
        $includeSchema = (bool) $request->boolean('include_schema');
        $password = $request->string('password')->toString() ?: null;

        // Proben sind laut supportbericht.md §6 dem Plattform-Betreiber
        // vorbehalten. Die Permission allein trägt das nicht: die org-lokale
        // admin-Rolle hält sie ebenfalls (Sicherheitsscan 2026-08-23, S-02).
        if ($includeSamples && ! ($this->isPlatformOperator() && $user->can(Permission::PlatformSupportExportWithSamples->value))) {
            return back()->withErrors([
                'include_samples' => __('Sample-Daten erfordern die Plattform-Admin-Berechtigung.'),
            ]);
        }

        $bundle = $builder->build([
            'include_samples' => $includeSamples,
            'include_schema' => $includeSchema,
        ]);

        $package = $packager->package($bundle, $password);

        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'support.reportGenerated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'sha256' => $package['sha256'],
                'bytes' => $package['bytes'],
                'password_set' => $package['password_set'],
                'include_samples' => $includeSamples,
                'include_schema' => $includeSchema,
            ],
        ]);

        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'support.reportDownloaded',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => ['sha256' => $package['sha256']],
        ]);

        return response()->download($package['path'], basename($package['path']))
            ->deleteFileAfterSend(true);
    }

    /**
     * Lädt den Bericht als reine JSON-Datei (ohne ZIP/Verschlüsselung) herunter.
     * Datensparsam: nur die Whitelist-Felder des Builders, keine Kundendaten.
     */
    public function download(Request $request, SupportReportBuilder $builder): Response {
        Gate::authorize(Permission::PlatformSupportExport->value);

        /** @var User $user */
        $user = $request->user();

        $bundle = $builder->build();
        $json = JsonHelper::encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $filename = 'support-report-' . Carbon::now()->format('Y-m-d') . '.json';

        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'support.reportDownloaded',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'format' => 'json',
                'sha256' => CryptoHelper::hash($json),
                'bytes' => strlen($json),
            ],
        ]);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Zeigt den Bericht als JSON im Browser an (Vorschau, ohne Download).
     * Identische Whitelist-Quelle wie {@see download()}.
     */
    public function preview(Request $request, SupportReportBuilder $builder): JsonResponse {
        Gate::authorize(Permission::PlatformSupportExport->value);

        $bundle = $builder->build();

        return response()->json($bundle, 200, [
            'X-Content-Type-Options' => 'nosniff',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
