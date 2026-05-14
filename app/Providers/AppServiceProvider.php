<?php

namespace App\Providers;

use App\Legacy\Auth\LegacyUserProvider;
use App\Listeners\AuthEventSubscriber;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\CoverageRequirement;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\DutyPlan;
use App\Models\EmergencyAssignment;
use App\Models\Material;
use App\Models\MaterialUsage;
use App\Models\Milestone;
use App\Models\Qualification;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Observers\AttachmentObserver;
use App\Observers\CommentObserver;
use App\Observers\CustomerObserver;
use App\Observers\DiaryEntryObserver;
use App\Observers\EmergencyAssignmentObserver;
use App\Observers\MaterialUsageObserver;
use App\Observers\TagObserver;
use App\Observers\TimeEntryObserver;
use App\Observers\TimesheetObserver;
use App\Observers\UserObserver;
use App\Policies\CoverageRequirementPolicy;
use App\Policies\DutyPlanPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\MaterialUsagePolicy;
use App\Policies\MilestonePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\QualificationPolicy;
use App\Policies\ScheduledShiftPolicy;
use App\Policies\ShiftTypePolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeEntryPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });

        Event::subscribe(AuthEventSubscriber::class);

        Comment::observe(CommentObserver::class);
        Attachment::observe(AttachmentObserver::class);
        Customer::observe(CustomerObserver::class);
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        MaterialUsage::observe(MaterialUsageObserver::class);

        Gate::policy(DutyPlan::class, DutyPlanPolicy::class);
        Gate::policy(CoverageRequirement::class, CoverageRequirementPolicy::class);
        Gate::policy(Milestone::class, MilestonePolicy::class);
        Gate::policy(Qualification::class, QualificationPolicy::class);
        Gate::policy(ScheduledShift::class, ScheduledShiftPolicy::class);
        Gate::policy(ShiftType::class, ShiftTypePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
        Gate::policy(Timesheet::class, TimesheetPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(MaterialUsage::class, MaterialUsagePolicy::class);
        Gate::policy(WorkSchedule::class, WorkSchedulePolicy::class);

        // manage-members: Org-Admin darf Mitglieder der eigenen Org verwalten
        Gate::define('manage-members', [OrganizationPolicy::class, 'manageMembers']);

        $this->configureRateLimiters();

        Password::defaults(function () {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return $this->app->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });
    }

    private function configureRateLimiters(): void {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', $request->input('username', ''));

            return [
                Limit::perMinute(5)->by(strtolower($email) . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', fn(Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('password', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(5)->by('pwd:' . $userId . '|' . $request->ip()),
                Limit::perHour(20)->by('pwd:' . $userId),
            ];
        });
    }
}
