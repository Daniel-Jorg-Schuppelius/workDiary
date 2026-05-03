<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiaryController;
use App\Http\Controllers\Api\EmergencyAssignmentController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\OnCallShiftController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('api.me');

    Route::get('diary', [DiaryController::class, 'index'])->name('api.diary.index');
    Route::post('diary', [DiaryController::class, 'store'])->name('api.diary.store');
    Route::get('diary/{diary}', [DiaryController::class, 'show'])->name('api.diary.show');
    Route::put('diary/{diary}', [DiaryController::class, 'update'])->name('api.diary.update');
    Route::patch('diary/{diary}', [DiaryController::class, 'update']);
    Route::delete('diary/{diary}', [DiaryController::class, 'destroy'])->name('api.diary.destroy');
    Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('api.diary.archive');
    Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('api.diary.restore');

    Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('api.diary.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('api.comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');

    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['diary', 'comment', 'shift', 'assignment'])
        ->whereNumber('id')
        ->name('api.attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('api.attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('api.attachments.destroy');

    Route::apiResource('tags', TagController::class)->except('show')->names('api.tags');

    Route::get('shifts', [OnCallShiftController::class, 'index'])->name('api.shifts.index');
    Route::get('shifts/{shift}', [OnCallShiftController::class, 'show'])->name('api.shifts.show');

    Route::get('assignments', [EmergencyAssignmentController::class, 'index'])->name('api.assignments.index');
    Route::get('assignments/{assignment}', [EmergencyAssignmentController::class, 'show'])->name('api.assignments.show');

    Route::get('dashboard', DashboardController::class)->name('api.dashboard');

    Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('api.push.vapid');
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('api.push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('api.push.unsubscribe');
});
