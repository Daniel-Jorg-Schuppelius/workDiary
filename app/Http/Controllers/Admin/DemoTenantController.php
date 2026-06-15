<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoTenantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Demo\DemoIndustry;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Organization, User};
use App\Services\Demo\DemoSeederService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemoTenantController extends Controller {
    public function __construct(private readonly DemoSeederService $seeder) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OrgDemoSeed->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, 404);

        $isEmpty = \App\Models\Customer::query()->where('organization_id', $organization->id)->doesntExist()
            && \App\Models\DiaryEntry::query()->where('organization_id', $organization->id)->doesntExist();

        return view('admin.demo.index', [
            'organization' => $organization,
            'isEmpty' => $isEmpty,
            'industries' => DemoIndustry::all(),
            'currentIndustry' => $organization->is_demo ? $this->seeder->resolveIndustry($organization) : DemoIndustry::default(),
        ]);
    }

    public function seed(Request $request): RedirectResponse {
        Gate::authorize(Permission::OrgDemoSeed->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, 404);

        $industry = DemoIndustry::fromKey((string) $request->input('industry'));

        $counts = $this->seeder->seed($organization, $user, $industry);

        $this->writeAudit($user, $organization, 'demo.seeded', $counts);

        return back()->with('success', __('Demo-Daten wurden erzeugt.'));
    }

    public function reset(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformDemoReset->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, 404);

        if (! $organization->is_demo) {
            return back()->withErrors([
                'organization' => __('Reset ist nur für Demo-Mandanten erlaubt.'),
            ]);
        }

        $counts = $this->seeder->reset($organization, $user);

        $this->writeAudit($user, $organization, 'demo.reset', $counts);

        return back()->with('success', __('Demo-Mandant wurde zurückgesetzt.'));
    }

    /** @param array<string, int|string> $counts */
    private function writeAudit(User $user, Organization $organization, string $event, array $counts): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => $counts,
        ]);
    }
}
