<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Diagnostics\DiagnosticsService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Gate, Mail};
use Illuminate\View\View;

class DiagnosticsController extends Controller {
    use RequiresPlatformOperator;

    use ResolvesCurrentOrganization;

    public function index(Request $request, DiagnosticsService $diagnostics): View {
        Gate::authorize(Permission::PlatformDiagnosticsView->value);
        $this->assertPlatformOperator();

        $report = $diagnostics->collect();

        $this->writeViewedAudit($request->user(), 'html');

        return view('admin.diagnostics.index', [
            'report' => $report,
        ]);
    }

    public function json(Request $request, DiagnosticsService $diagnostics): JsonResponse {
        Gate::authorize(Permission::PlatformDiagnosticsView->value);
        $this->assertPlatformOperator();

        $report = $diagnostics->collect();

        $this->writeViewedAudit($request->user(), 'json');

        return response()->json($report->toArray());
    }

    public function testMail(Request $request): JsonResponse {
        Gate::authorize(Permission::PlatformDiagnosticsRunCheck->value);
        $this->assertPlatformOperator();

        /** @var User $user */
        $user = $request->user();

        try {
            Mail::raw('WorkDiary Diagnose-Testmail', static function ($message) use ($user): void {
                $message->to($user->email)->subject('WorkDiary Diagnose');
            });
            $ok = true;
            $error = null;
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        $this->writeTestTriggeredAudit($user, 'mail', $ok, $error);

        return response()->json([
            'ok' => $ok,
            'error' => $error,
            'target' => $user->email,
        ]);
    }

    private function writeViewedAudit(?User $user, string $format): void {
        if ($user === null) {
            return;
        }

        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'diagnostics.viewed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => ['format' => $format],
        ]);
    }

    private function writeTestTriggeredAudit(User $user, string $kind, bool $ok, ?string $error): void {
        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'diagnostics.testTriggered',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => ['kind' => $kind, 'ok' => $ok, 'error' => $error],
        ]);
    }
}
