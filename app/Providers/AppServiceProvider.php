<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Services\MailNotifier;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });

        Comment::created(function (Comment $c) {
            app(PushNotifier::class)->newComment($c);
            app(MailNotifier::class)->commentCreated($c);
        });
        Attachment::created(fn(Attachment $a) => app(PushNotifier::class)->newAttachment($a));
        EmergencyAssignment::created(fn(EmergencyAssignment $e) => app(PushNotifier::class)->emergencyAssigned($e));
        DiaryEntry::created(fn(DiaryEntry $d) => app(PushNotifier::class)->diaryProblem($d));
        DiaryEntry::updated(function (DiaryEntry $d) {
            if ($d->wasChanged('status')) {
                app(PushNotifier::class)->diaryProblem($d);
                app(MailNotifier::class)->diaryStatusChanged(
                    $d,
                    $d->getOriginal('status') !== null ? (int) $d->getOriginal('status') : null,
                    (int) $d->status,
                );
            }
        });
    }
}
