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
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Support\{SupportReportBuilder, SupportReportPackager};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupportReportController extends Controller {
    public function index(Request $request, SupportReportBuilder $builder, SupportReportPackager $packager): View {
        Gate::authorize(Permission::PlatformSupportExport->value);

        // Inhalts-Übersicht (Spec §5): bauen, aber nicht packen.
        $bundle = $builder->build();
        $preview = $packager->preview($bundle);

        return view('admin.support.report', [
            'preview' => $preview,
            'canExportWithSamples' => $request->user()?->can(Permission::PlatformSupportExportWithSamples->value) ?? false,
        ]);
    }

    public function generate(Request $request, SupportReportBuilder $builder, SupportReportPackager $packager): BinaryFileResponse|RedirectResponse {
        Gate::authorize(Permission::PlatformSupportExport->value);

        /** @var User $user */
        $user = $request->user();

        $includeSamples = (bool) $request->boolean('include_samples');
        $includeSchema = (bool) $request->boolean('include_schema');
        $password = $request->string('password')->toString() ?: null;

        if ($includeSamples && ! $user->can(Permission::PlatformSupportExportWithSamples->value)) {
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
            'organization_id' => $user->organization_id,
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
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'event' => 'support.reportDownloaded',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => ['sha256' => $package['sha256']],
        ]);

        return response()->download($package['path'], basename($package['path']))
            ->deleteFileAfterSend(true);
    }
}
