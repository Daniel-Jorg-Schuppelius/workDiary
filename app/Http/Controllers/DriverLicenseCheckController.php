<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheckController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{DriverLicenseCheck, User};
use App\Services\Fleet\DriverLicenseCheckService;
use App\Support\{LookupCache, Sqid};
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Führerscheinkontrolle (MVP-417): Fälligkeitsübersicht + dokumentierte Sichtprüfung. */
class DriverLicenseCheckController extends Controller {
    public function index(DriverLicenseCheckService $service): View {
        Gate::authorize(Permission::VehicleViewAny->value);

        /** @var User $auth */
        $auth = Auth::user();

        $users = User::query()
            ->where('organization_id', $auth->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];
        foreach ($users as $user) {
            $latest = $service->latestFor((int) $user->id);
            $rows[] = [
                'user' => $user,
                'latest' => $latest,
                'overdue' => $service->isOverdue((int) $user->id),
            ];
        }

        // Überfällige zuerst, dann fällige, dann Rest.
        usort($rows, static function (array $a, array $b): int {
            $rank = static fn(array $r): int => $r['overdue'] ? 0 : ($r['latest'] !== null ? 1 : 2);
            $dueKey = static fn(array $r): string => $r['latest'] !== null
                ? $r['latest']->next_due_on->toDateString()
                : '9999-12-31';

            return [$rank($a), $dueKey($a)] <=> [$rank($b), $dueKey($b)];
        });

        return view('driver-license-checks.index', ['rows' => $rows]);
    }

    public function create(Request $request): View {
        Gate::authorize(Permission::VehicleManage->value);

        return view('driver-license-checks._form_dialog', [
            'assignableUsers' => LookupCache::userDropdown(),
            'prefillUser' => (string) $request->query('user', ''),
        ]);
    }

    public function store(Request $request, DriverLicenseCheckService $service): RedirectResponse {
        Gate::authorize(Permission::VehicleManage->value);

        /** @var User $auth */
        $auth = Auth::user();
        $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        $data = $request->validate([
            'user_id' => ['required', new \App\Rules\ExistsInCurrentOrganization()],
            'checked_at' => ['required', 'date', 'before_or_equal:today'],
            'license_classes' => ['nullable', 'string', 'max:60'],
            'license_valid_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $driver */
        $driver = User::query()
            ->where('organization_id', $auth->organization_id)
            ->findOrFail((int) $data['user_id']);

        $service->record(
            $driver,
            $auth,
            Carbon::parse($data['checked_at']),
            $data['license_classes'] ?? null,
            isset($data['license_valid_until']) ? Carbon::parse($data['license_valid_until']) : null,
            $data['note'] ?? null,
        );

        return redirect()->route('driver-license-checks.index')->with('status', __('Führerscheinkontrolle dokumentiert.'));
    }

    /** Historie eines Fahrers (Nachweis). */
    public function show(User $user, DriverLicenseCheckService $service): View {
        Gate::authorize(Permission::VehicleViewAny->value);

        /** @var User $auth */
        $auth = Auth::user();
        abort_unless((int) $user->organization_id === (int) $auth->organization_id, 404);

        return view('driver-license-checks.show', [
            'driver' => $user,
            'checks' => DriverLicenseCheck::query()
                ->where('user_id', $user->id)
                ->orderByDesc('checked_at')
                ->orderByDesc('id')
                ->get(),
            'overdue' => $service->isOverdue((int) $user->id),
        ]);
    }
}
