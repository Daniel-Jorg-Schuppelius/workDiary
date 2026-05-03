<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\Tag;
use App\Models\User;
use App\Observers\AttachmentObserver;
use App\Observers\CommentObserver;
use App\Observers\DiaryEntryObserver;
use App\Observers\EmergencyAssignmentObserver;
use App\Observers\TagObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Auth;
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
    }
}
