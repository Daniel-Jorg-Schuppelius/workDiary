<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_authenticated_user_can_create_comment(): void
    {
        $owner = User::factory()->user()->create();
        $author = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($author)
            ->post(route('diary.comments.store', $entry), ['body' => 'Mein Kommentar'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'diary_entry_id' => $entry->id,
            'user_id' => $author->id,
            'body' => 'Mein Kommentar',
        ]);
    }

    public function test_guest_cannot_create_comment(): void
    {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->post(route('diary.comments.store', $entry), ['body' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_owner_can_update_and_delete_own_comment(): void
    {
        $author = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($author)->create();
        $comment = Comment::factory()->for($entry, 'diaryEntry')->for($author)->create();

        $this->actingAs($author)
            ->put(route('comments.update', $comment), ['body' => 'updated'])
            ->assertRedirect();
        $this->assertSame('updated', $comment->fresh()->body);

        $this->actingAs($author)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect();
        $this->assertNull(Comment::find($comment->id));
    }

    public function test_other_user_cannot_update_or_delete_foreign_comment(): void
    {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($author)->create();
        $comment = Comment::factory()->for($entry, 'diaryEntry')->for($author)->create();

        $this->actingAs($other)
            ->put(route('comments.update', $comment), ['body' => 'x'])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($author)->create();
        $comment = Comment::factory()->for($entry, 'diaryEntry')->for($author)->create();

        $this->actingAs($admin)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect();

        $this->assertNull(Comment::find($comment->id));
    }

    public function test_comment_body_is_required(): void
    {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('diary.comments.store', $entry), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_show_page_renders_comments(): void
    {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        Comment::factory()->for($entry, 'diaryEntry')->for($user)->create(['body' => 'Sichtbarer Inhalt']);

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('Sichtbarer Inhalt');
    }

    public function test_deleting_diary_cascades_comments(): void
    {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        Comment::factory()->for($entry, 'diaryEntry')->for($user)->create();

        $entry->delete();

        $this->assertSame(0, Comment::count());
    }
}
