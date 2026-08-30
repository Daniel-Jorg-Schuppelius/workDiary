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
use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Organization, User};
use App\Services\Demo\DemoSeederService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DemoTenantController extends Controller {
    use RequiresPlatformOperator;

    public function __construct(private readonly DemoSeederService $seeder) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OrgDemoSeed->value);
        $this->assertPlatformOperator();

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
        $this->assertPlatformOperator();

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
        $this->assertPlatformOperator();

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

    /**
     * freshDemoOrg-Dialog (MVP-349, demo-mandant.md §2): Modal-Partial nach
     * Mandantenverwaltungs-Muster. NUR Plattform-Admin — die `create`-Ability
     * der OrganizationPolicy lässt ausschließlich `is_platform_admin` durch
     * (org-lokale Admins: 403), genau wie das reguläre Anlegen von Mandanten.
     */
    public function createFreshOrg(): View {
        Gate::authorize('create', Organization::class);

        return view('admin.demo._fresh_org_dialog', [
            'industries' => DemoIndustry::all(),
            'defaultIndustry' => DemoIndustry::default(),
            'platformAdmins' => User::query()
                ->where('is_platform_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * freshDemoOrg ausführen: neue, isolierte Demo-Organisation aus der
     * Musterbranche erzeugen (Kern: {@see DemoSeederService::freshOrg()},
     * inkl. `demo.orgCreated`-Audit). Optional wird ein Plattform-Admin als
     * Mitglied zugewiesen, damit er den Mandanten direkt einsehen kann.
     */
    public function storeFreshOrg(Request $request): RedirectResponse {
        Gate::authorize('create', Organization::class);

        $data = $request->validate([
            'industry' => ['required', \Illuminate\Validation\Rule::in(array_map(
                static fn(DemoIndustry $i): string => $i->value,
                DemoIndustry::all(),
            ))],
            'member' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $member = null;
        if (filled($data['member'] ?? null)) {
            $memberId = Sqid::decodeOrNumeric(User::class, (string) $data['member']);
            $member = $memberId !== null
                ? User::query()->whereKey($memberId)->where('is_platform_admin', true)->first()
                : null;
            if ($member === null) {
                throw ValidationException::withMessages([
                    'member' => __('Der ausgewählte Benutzer ist kein Plattform-Admin.'),
                ]);
            }
        }

        $result = $this->seeder->freshOrg(DemoIndustry::fromKey((string) $data['industry']), $actor, $member);

        return redirect()->route('admin.organizations.index')
            ->with('success', __('Demo-Organisation ":name" wurde angelegt.', ['name' => $result['organization']->name]));
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
