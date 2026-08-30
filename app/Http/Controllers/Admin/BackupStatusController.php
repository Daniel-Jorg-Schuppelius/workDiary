<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\LogRestoreTestRequest;
use App\Models\RestoreTest;
use App\Services\Backup\BackupStatusService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Admin-Statusseite „Backup & Restore" (Feature 017, MVP):
 * - letzte erfolgreiche Sicherung je Quelle + Frische-/Ausfall-Warnung,
 * - Restore-Test-Register + „Restore-Test protokollieren"-Modal,
 * - Überfälligkeits-Warnung für Restore-Tests.
 *
 * Plattformweite Sicht (kein Tenant-Kontext) — analog admin/metrics. Gate über
 * die dedizierte Permission backup.view (Anzeige) bzw.
 * backup.restoreTest.log (Protokollieren).
 */
class BackupStatusController extends Controller {
    use RequiresPlatformOperator;

    public function status(Request $request, BackupStatusService $service): View {
        Gate::authorize(Permission::BackupView->value);
        // Plattformweite Sicht ohne Mandanten-Kontext (siehe Klassenkommentar):
        // Ein Org-Admin hätte hier den Betriebszustand der ganzen Installation
        // vor sich (Sicherheitsscan 2026-08-23, S-02).
        $this->assertPlatformOperator();

        $restoreTests = RestoreTest::query()
            ->with('performedBy:id,name')
            ->orderByDesc('tested_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.backup.status', [
            'status' => $service->collect(),
            'restoreTests' => $restoreTests,
        ]);
    }

    public function createRestoreTest(): View {
        Gate::authorize(Permission::BackupRestoreTestLog->value);
        // Plattformweite Sicht ohne Mandanten-Kontext (siehe Klassenkommentar):
        // Ein Org-Admin hätte hier den Betriebszustand der ganzen Installation
        // vor sich (Sicherheitsscan 2026-08-23, S-02).
        $this->assertPlatformOperator();

        return view('admin.backup._restore_test_dialog', [
            'restoreTest' => new RestoreTest(),
        ]);
    }

    public function storeRestoreTest(LogRestoreTestRequest $request): RedirectResponse {
        Gate::authorize(Permission::BackupRestoreTestLog->value);
        // Plattformweite Sicht ohne Mandanten-Kontext (siehe Klassenkommentar):
        // Ein Org-Admin hätte hier den Betriebszustand der ganzen Installation
        // vor sich (Sicherheitsscan 2026-08-23, S-02).
        $this->assertPlatformOperator();

        $data = $request->validated();
        $data['performed_by_user_id'] = Auth::id();

        RestoreTest::create($data);

        return redirect()
            ->route('admin.backup.status')
            ->with('status', __('backup.flash.restore_test_logged'));
    }

}
