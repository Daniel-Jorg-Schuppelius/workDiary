<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\Tag;
use App\Models\User;
use App\Observers\AttachmentObserver;
use App\Observers\CommentObserver;
use App\Observers\DiaryEntryObserver;
use App\Observers\EmergencyAssignmentObserver;
use App\Observers\TagObserver;
use App\Observers\UserObserver;
use App\Policies\ScheduledShiftPolicy;
use App\Policies\ShiftTypePolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });

        Comment::observe(CommentObserver::class);
        Attachment::observe(AttachmentObserver::class);
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);

        Gate::policy(ScheduledShift::class, ScheduledShiftPolicy::class);
        Gate::policy(ShiftType::class, ShiftTypePolicy::class);
    }
}
