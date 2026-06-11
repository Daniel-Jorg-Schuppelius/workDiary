<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility, ParticipantParty};
use App\Models\{CommunicationNote, DiaryEntry, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationNoteControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_store_note_against_diary_entry(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->subMinutes(30)->format('Y-m-d H:i'),
                'subject' => 'Rückruf wegen Angebot',
                'body' => 'Kunde wünscht ein aktualisiertes Angebot bis Freitag.',
                'participants' => [
                    ['name' => 'Max Kunde', 'role' => 'Auftraggeber', 'party' => ParticipantParty::Customer->value],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('communication_notes', [
            'notable_type' => DiaryEntry::class,
            'notable_id' => $entry->id,
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Outbound->value,
            'subject' => 'Rückruf wegen Angebot',
            'visibility' => CommunicationVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('communication_note_participants', [
            'name' => 'Max Kunde',
            'role' => 'Auftraggeber',
            'party' => ParticipantParty::Customer->value,
        ]);
    }

    public function test_can_open_create_dialog(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('communication-notes.create', [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            ]))
            ->assertOk()
            ->assertSee('name="notable_kind"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="body"', false);
    }

    public function test_guest_cannot_store(): void {
        $entry = DiaryEntry::factory()->for(User::factory()->user())->create();

        $this->post(route('communication-notes.store'), [
            'notable_kind' => 'diary',
            'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
        ])->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_store(): void {
        // Geschäftsführung hat nur viewAny/view, kein communication.create.
        $author = User::factory()->user()->create();
        $gf = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();

        $this->actingAs($gf)
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->format('Y-m-d H:i'),
                'subject' => 'Verboten',
                'body' => 'Darf nicht gespeichert werden.',
            ])
            ->assertForbidden();
    }

    public function test_internal_type_requires_internal_direction(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Internal->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->format('Y-m-d H:i'),
                'subject' => 'Interne Rücksprache',
                'body' => 'Abstimmung im Team.',
            ])
            ->assertSessionHasErrors('direction');

        $this->assertSame(0, CommunicationNote::query()->count());
    }

    public function test_followup_due_date_must_be_after_occurrence(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Inbound->value,
                'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i'),
                'subject' => 'Frist-Test',
                'body' => 'Folgeaktion mit Frist vor dem Zeitpunkt.',
                'next_action' => 'Angebot raus',
                'next_action_due_at' => now()->subHours(2)->format('Y-m-d H:i'),
            ])
            ->assertSessionHasErrors('next_action_due_at');
    }

    public function test_occurred_at_must_not_be_in_future(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Inbound->value,
                'occurred_at' => now()->addDay()->format('Y-m-d H:i'),
                'subject' => 'Zukunfts-Test',
                'body' => 'Darf nicht in der Zukunft liegen.',
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_storing_customer_visible_note_requires_publish_permission(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('communication-notes.store'), [
                'notable_kind' => 'diary',
                'notable_id' => Sqid::encode(DiaryEntry::class, $entry->id),
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->format('Y-m-d H:i'),
                'subject' => 'Kunden-sichtbar',
                'body' => 'Ohne Freigabe-Permission verboten.',
                'visibility' => CommunicationVisibility::Customer->value,
            ])
            ->assertForbidden();
    }

    public function test_publish_emits_audit_event(): void {
        $lead = User::factory()->teamleitung()->create();
        $entry = DiaryEntry::factory()->for($lead)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $lead->organization_id,
            'created_by_user_id' => $lead->id,
        ]);

        $this->actingAs($lead)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.publish', $note))
            ->assertRedirect();

        $this->assertSame(CommunicationVisibility::Customer, $note->refresh()->visibility);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'communication.published',
            'auditable_type' => CommunicationNote::class,
            'auditable_id' => $note->id,
        ]);
    }

    public function test_confidential_note_cannot_be_published(): void {
        $admin = User::factory()->admin()->create();
        $entry = DiaryEntry::factory()->for($admin)->create();
        $note = CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $admin->organization_id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.publish', $note))
            ->assertSessionHasErrors('visibility');

        $this->assertSame(CommunicationVisibility::Internal, $note->refresh()->visibility);
    }

    public function test_creator_can_update_within_24_hours(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)
            ->from(route('diary.show', $entry))
            ->put(route('communication-notes.update', $note), [
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->subHour()->format('Y-m-d H:i'),
                'subject' => 'Aktualisierter Betreff',
                'body' => 'Aktualisierter Inhalt.',
            ])
            ->assertRedirect();

        $this->assertSame('Aktualisierter Betreff', $note->refresh()->subject);
    }

    public function test_creator_cannot_update_after_24_hours(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subHours(25),
        ]);

        $this->actingAs($user)
            ->put(route('communication-notes.update', $note), [
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->subHour()->format('Y-m-d H:i'),
                'subject' => 'Zu spät',
                'body' => 'Nach 24 h gesperrt.',
            ])
            ->assertForbidden();
    }

    public function test_org_admin_can_update_after_24_hours(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $entry = DiaryEntry::factory()->for($user)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin)
            ->from(route('diary.show', $entry))
            ->put(route('communication-notes.update', $note), [
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->subHour()->format('Y-m-d H:i'),
                'subject' => 'Admin-Korrektur',
                'body' => 'Org-Admin darf jederzeit.',
            ])
            ->assertRedirect();

        $this->assertSame('Admin-Korrektur', $note->refresh()->subject);
    }

    public function test_other_user_cannot_update_foreign_note(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($other)
            ->put(route('communication-notes.update', $note), [
                'type' => CommunicationNoteType::Call->value,
                'direction' => CommunicationDirection::Outbound->value,
                'occurred_at' => now()->subHour()->format('Y-m-d H:i'),
                'subject' => 'Fremdzugriff',
                'body' => 'Verboten.',
            ])
            ->assertForbidden();
    }

    public function test_responsible_user_can_complete_followup(): void {
        $author = User::factory()->user()->create();
        $responsible = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->withFollowUp()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
            'next_action_user_id' => $responsible->id,
        ]);

        $this->actingAs($responsible)
            ->from(route('diary.show', $entry))
            ->post(route('communication-notes.followup-complete', $note))
            ->assertRedirect();

        $note->refresh();
        $this->assertNotNull($note->next_action_completed_at);
        $this->assertSame((int) $responsible->id, (int) $note->next_action_completed_by_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'communication.followup.completed',
            'auditable_type' => CommunicationNote::class,
            'auditable_id' => $note->id,
        ]);
    }

    public function test_uninvolved_user_cannot_complete_followup(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->withFollowUp()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($other)
            ->post(route('communication-notes.followup-complete', $note))
            ->assertForbidden();
    }

    public function test_delete_requires_permission_and_writes_audit(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $entry = DiaryEntry::factory()->for($user)->create();
        $note = CommunicationNote::factory()->for($entry, 'notable')->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
        ]);

        // User-Rolle hat kein communication.delete.
        $this->actingAs($user)
            ->delete(route('communication-notes.destroy', $note))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('diary.show', $entry))
            ->delete(route('communication-notes.destroy', $note), ['reason' => 'Falsch erfasst'])
            ->assertRedirect();

        $this->assertSoftDeleted('communication_notes', ['id' => $note->id]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'communication.deleted',
            'auditable_type' => CommunicationNote::class,
            'auditable_id' => $note->id,
        ]);
    }

    public function test_confidential_view_by_admin_is_audited_on_edit(): void {
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $entry = DiaryEntry::factory()->for($author)->create();
        $note = CommunicationNote::factory()->confidential()->for($entry, 'notable')->create([
            'organization_id' => $author->organization_id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($admin)
            ->get(route('communication-notes.edit', $note))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'communication.confidential.viewed',
            'auditable_type' => CommunicationNote::class,
            'auditable_id' => $note->id,
        ]);
    }
}
