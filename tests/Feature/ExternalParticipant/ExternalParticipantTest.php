<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ExternalParticipant;

use App\Enums\ExternalParticipant\ExternalAbility;
use App\Models\{DiaryEntry, ExternalParticipant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Externe Beteiligte (Feature 033): Einladung erzeugt Hash-Token (Klartext nur
 * einmal), login-freier öffentlicher Zugriff (gültig ok, abgelaufen/widerrufen
 * ⇒ 404), strikte abilities-Durchsetzung (view-only ⇒ 403 bei Upload/Confirm),
 * Nachweis aller externen Aktionen, Widerruf, Cross-Org.
 */
class ExternalParticipantTest extends TestCase {
    use RefreshDatabase;

    private function manager(): User {
        $user = User::factory()->user()->create();
        $user->givePermissionTo('externalParticipant.manage');

        return $user;
    }

    public function test_invite_creates_token_and_shows_plaintext_once(): void {
        $manager = $this->manager();
        $entry = DiaryEntry::factory()->for($manager)->create();

        $response = $this->actingAs($manager)
            ->from(route('diary.show', $entry))
            ->post(route('external.store', ['type' => 'diary', 'id' => $entry->getRouteKey()]), [
                'name' => 'Subunternehmer Müller',
                'party' => 'subcontractor',
                'abilities' => ['comment'],
                'ttl_days' => 7,
            ]);

        $response->assertRedirect(route('diary.show', $entry));
        $response->assertSessionHas('external_participant_link');

        /** @var ExternalParticipant $participant */
        $participant = ExternalParticipant::query()->firstOrFail();
        $this->assertSame('Subunternehmer Müller', $participant->name);
        $this->assertSame((int) $manager->organization_id, (int) $participant->organization_id);
        $this->assertSame([ExternalAbility::Comment->value], $participant->abilities);

        // Klartext-Token wird NICHT gespeichert (nur der Hash).
        $link = (string) session('external_participant_link');
        $token = (string) parse_url($link, PHP_URL_PATH);
        $this->assertStringContainsString('/extern/', $link);
        $this->assertNotSame($participant->token_hash, $token);
        $this->assertSame(64, strlen($participant->token_hash));

        // Einladung wird intern auditiert.
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'external.participant.invited',
            'auditable_type' => DiaryEntry::class,
            'auditable_id' => $entry->id,
        ]);
    }

    public function test_public_access_without_login_works_with_valid_token(): void {
        $entry = DiaryEntry::factory()->create();
        $token = 'plain-token-valid-1234567890';
        ExternalParticipant::factory()
            ->withPlainToken($token)
            ->state(['subject_type' => DiaryEntry::class, 'subject_id' => $entry->id, 'organization_id' => $entry->organization_id])
            ->create();

        $this->get(route('external.show', ['token' => $token]))->assertOk();

        // Zugriff wird nachgewiesen + last_access_at gesetzt.
        $this->assertDatabaseHas('external_participant_events', ['event' => 'accessed']);
        $this->assertNotNull(ExternalParticipant::query()->firstOrFail()->last_access_at);
    }

    public function test_expired_token_returns_404(): void {
        $token = 'plain-token-expired-123456789';
        ExternalParticipant::factory()->withPlainToken($token)->expired()->create();

        $this->get(route('external.show', ['token' => $token]))->assertNotFound();
    }

    public function test_revoked_token_returns_404(): void {
        $token = 'plain-token-revoked-123456789';
        ExternalParticipant::factory()->withPlainToken($token)->revoked()->create();

        $this->get(route('external.show', ['token' => $token]))->assertNotFound();
    }

    public function test_unknown_token_returns_404(): void {
        $this->get(route('external.show', ['token' => 'does-not-exist']))->assertNotFound();
    }

    public function test_view_only_cannot_upload_or_confirm(): void {
        $entry = DiaryEntry::factory()->create();
        $token = 'plain-token-viewonly-12345678';
        ExternalParticipant::factory()
            ->withPlainToken($token)
            ->viewOnly()
            ->state(['subject_type' => DiaryEntry::class, 'subject_id' => $entry->id, 'organization_id' => $entry->organization_id])
            ->create();

        $this->post(route('external.upload', ['token' => $token]), [])->assertForbidden();
        $this->post(route('external.confirm', ['token' => $token]), ['accept' => '1'])->assertForbidden();
        $this->post(route('external.comment', ['token' => $token]), ['body' => 'hallo'])->assertForbidden();
    }

    public function test_comment_ability_creates_comment_and_event(): void {
        $entry = DiaryEntry::factory()->create();
        $token = 'plain-token-comment-123456789';
        ExternalParticipant::factory()
            ->withPlainToken($token)
            ->abilities([ExternalAbility::Comment->value])
            ->state(['subject_type' => DiaryEntry::class, 'subject_id' => $entry->id, 'organization_id' => $entry->organization_id])
            ->create();

        $this->post(route('external.comment', ['token' => $token]), ['body' => 'Bitte Termin verschieben.'])
            ->assertRedirect(route('external.show', ['token' => $token]));

        $this->assertDatabaseHas('comments', [
            'commentable_type' => DiaryEntry::class,
            'commentable_id' => $entry->id,
            'user_id' => null,
            'body' => 'Bitte Termin verschieben.',
        ]);
        $this->assertDatabaseHas('external_participant_events', ['event' => 'commented']);
    }

    public function test_confirm_ability_logs_confirmation(): void {
        $entry = DiaryEntry::factory()->create();
        $token = 'plain-token-confirm-123456789';
        ExternalParticipant::factory()
            ->withPlainToken($token)
            ->abilities([ExternalAbility::Confirm->value])
            ->state(['subject_type' => DiaryEntry::class, 'subject_id' => $entry->id, 'organization_id' => $entry->organization_id])
            ->create();

        $this->post(route('external.confirm', ['token' => $token]), ['accept' => '1', 'note' => 'Abnahme ok'])
            ->assertRedirect(route('external.show', ['token' => $token]));

        $this->assertDatabaseHas('external_participant_events', ['event' => 'confirmed']);
    }

    public function test_revoke_blocks_further_access(): void {
        $manager = $this->manager();
        $entry = DiaryEntry::factory()->for($manager)->create();
        $token = 'plain-token-toberevoked-12345';
        $participant = ExternalParticipant::factory()
            ->withPlainToken($token)
            ->state(['subject_type' => DiaryEntry::class, 'subject_id' => $entry->id, 'organization_id' => $manager->organization_id])
            ->create();

        $this->actingAs($manager)
            ->from(route('diary.show', $entry))
            ->post(route('external.revoke', $participant))
            ->assertRedirect(route('diary.show', $entry));

        $this->assertNotNull($participant->fresh()->revoked_at);
        $this->get(route('external.show', ['token' => $token]))->assertNotFound();
    }

    public function test_cross_org_subject_is_not_reachable(): void {
        // Manager aus Org A versucht, einen Externen an einen fremden Auftrag
        // (Org B) zu hängen — die org-gescopte Subject-Query findet ihn nicht.
        $manager = $this->manager();
        $foreignEntry = DiaryEntry::factory()->create(); // andere Org

        $this->actingAs($manager)
            ->post(route('external.store', ['type' => 'diary', 'id' => $foreignEntry->getRouteKey()]), [
                'name' => 'Fremd',
                'party' => 'subcontractor',
                'ttl_days' => 7,
            ])
            ->assertNotFound();

        $this->assertSame(0, ExternalParticipant::query()->count());
    }

    public function test_invite_requires_manage_permission(): void {
        $user = User::factory()->user()->create(); // ohne Permission
        $entry = DiaryEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('external.store', ['type' => 'diary', 'id' => $entry->getRouteKey()]), [
                'name' => 'X',
                'party' => 'subcontractor',
                'ttl_days' => 7,
            ])
            ->assertForbidden();
    }
}
