<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailNotificationsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Mail\CommentCreatedMail;
use App\Mail\DiaryStatusChangedMail;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailNotificationsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Config::set('app.mail_notifications_enabled', true);
        Mail::fake();
    }

    public function test_comment_sends_mail_to_entry_owner(): void {
        $owner = User::factory()->user()->create(['email' => 'owner@example.test']);
        $other = User::factory()->user()->create(['email' => 'other@example.test']);
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($other);
        Comment::create(['diary_entry_id' => $entry->id, 'user_id' => $other->id, 'body' => 'Hi']);

        Mail::assertQueued(CommentCreatedMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
    }

    public function test_status_change_to_problem_sends_mail_to_owner(): void {
        $owner = User::factory()->user()->create(['email' => 'owner@example.test']);
        $changer = User::factory()->admin()->create();
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 2]);

        $this->actingAs($changer);
        $entry->update(['status' => 3]);

        Mail::assertQueued(DiaryStatusChangedMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
    }

    public function test_status_change_to_open_does_not_send_mail(): void {
        $owner = User::factory()->user()->create(['email' => 'owner@example.test']);
        $changer = User::factory()->admin()->create();
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 3]);

        $this->actingAs($changer);
        $entry->update(['status' => 2]);

        Mail::assertNotQueued(DiaryStatusChangedMail::class);
    }

    public function test_disabled_flag_skips_mails(): void {
        Config::set('app.mail_notifications_enabled', false);
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($other);
        Comment::create(['diary_entry_id' => $entry->id, 'user_id' => $other->id, 'body' => 'Hi']);

        Mail::assertNothingQueued();
    }
}
