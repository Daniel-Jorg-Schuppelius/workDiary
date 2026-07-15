<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{AuditLog, OnboardingProgress, User};
use App\Services\Onboarding\OnboardingChecklistResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Route};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OnboardingController extends Controller {
    /** @var list<string> */
    private const STEP_CODES = [
        'org.profile',
        'org.branch_profile',
        'org.scope',
        'users.invite',
        'roles.check',
        'classification.check',
        'customer.first',
        'work.first',
        'time.first',
        'protocol.first_signed',
        'backup.heartbeat',
    ];

    /** @var list<string> */
    private const HARD_REQUIRED_STEP_CODES = [
        'org.profile',
        'roles.check',
        'customer.first',
        'work.first',
    ];

    /** @var array<string, string> */
    private const STEP_ROUTES = [
        'org.profile' => 'account.profile.edit',
        'org.branch_profile' => 'admin.branch-profiles.index',
        'org.scope' => 'admin.scope.index',
        'users.invite' => 'org-members.index',
        'roles.check' => 'admin.access.members.index',
        'classification.check' => 'admin.classifications.index',
        'customer.first' => 'customers.index',
        'work.first' => 'projects.index',
        'time.first' => 'time-entries.create',
        'protocol.first_signed' => 'diary.index',
        'backup.heartbeat' => 'audit.index',
    ];

    public function __invoke(Request $request, OnboardingChecklistResolver $resolver): View {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize(Permission::OrgOnboardingView->value);

        $organization = $user->organization;
        abort_if($organization === null, 404);

        $checklist = $resolver->forOrganization($organization, $user);

        $steps = array_map(
            static function (array $step): array {
                $routeName = self::STEP_ROUTES[$step['code']] ?? null;

                return array_merge($step, [
                    'description' => __('onboarding.step.' . $step['code'] . '.description'),
                    'label' => __('onboarding.step.' . $step['code'] . '.link'),
                    'href' => $routeName !== null && Route::has($routeName) ? route($routeName) : null,
                    'skippable' => ! in_array($step['code'], self::HARD_REQUIRED_STEP_CODES, true),
                ]);
            },
            $checklist['steps']
        );

        return view('onboarding.index', [
            'organization' => $organization,
            'checklist' => array_merge($checklist, ['steps' => $steps]),
            'widgetDismissedAt' => $organization->groupSettings('ui')['onboarding_widget_dismissed_at'] ?? null,
        ]);
    }

    public function skipStep(Request $request, string $step): \Illuminate\Http\RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize(Permission::OrgOnboardingSkipStep->value);

        abort_unless(in_array($step, self::STEP_CODES, true), Response::HTTP_NOT_FOUND);

        if (in_array($step, self::HARD_REQUIRED_STEP_CODES, true)) {
            return back()->withErrors([
                'step' => __('onboarding.action.error_step_not_skippable'),
            ]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $organization = $user->organization;
        abort_if($organization === null, 404);

        $progress = OnboardingProgress::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'step_code' => $step,
            ],
            [
                'state' => 'skipped',
                'done_at' => null,
                'done_by_user_id' => $user->id,
                'skipped_reason' => (string) $data['reason'],
            ]
        );

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'onboarding.stepSkipped',
            'auditable_type' => OnboardingProgress::class,
            'auditable_id' => $progress->id,
            'changes' => [
                'step_code' => $step,
                'reason' => (string) $data['reason'],
            ],
        ]);

        return back()->with('success', __('onboarding.action.flash_skipped'));
    }

    public function dismissWidget(Request $request): \Illuminate\Http\RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize(Permission::OrgOnboardingDismissWidget->value);

        $organization = $user->organization;
        abort_if($organization === null, 404);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $ui = is_array($settings['ui'] ?? null) ? $settings['ui'] : [];
        $dismissedAt = CarbonImmutable::now()->toIso8601String();
        $ui['onboarding_widget_dismissed_at'] = $dismissedAt;
        $settings['ui'] = $ui;

        $organization->settings = $settings;
        $organization->save();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'onboarding.widgetDismissed',
            'auditable_type' => $organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'dismissed_at' => $dismissedAt,
            ],
        ]);

        return back()->with('success', __('onboarding.action.flash_dismissed'));
    }
}
