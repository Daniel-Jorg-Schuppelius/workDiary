<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Construction;

use App\Enums\Construction\ConstructionNoticeStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Mail\DocumentMail;
use App\Models\Construction\ConstructionNotice;
use App\Models\{Customer, DiaryEntry, DocumentDispatch, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * VOB/B-Schreiben (Feature 062, MVP-728, H23): Anlage aus dem Tagebuch, PDF,
 * Zugangsnachweis im Dispatch-Log, Festschreibung nach dem Versand und
 * Mandantentrennung.
 */
class ConstructionNoticeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgAdmin();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array {
        return array_merge([
            'kind' => RenderDocumentKind::ConstructionObstructionNotice->value,
            'subject' => 'Fehlende Vorleistung Rohbau',
            'occurred_on' => now()->toDateString(),
            'facts' => 'Die Deckenöffnungen wurden nicht wie vereinbart bereitgestellt; die Montage kann nicht beginnen.',
            'impact_schedule' => 'Verzug von voraussichtlich fünf Arbeitstagen.',
            'claims_time_extension' => '1',
        ], $overrides);
    }

    private function createNotice(array $overrides = []): ConstructionNotice {
        $this->actingAs($this->user)
            ->post(route('construction-notices.store'), $this->payload($overrides))
            ->assertRedirect();

        return ConstructionNotice::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_index_and_dialog_are_reachable(): void {
        $this->actingAs($this->user)->get(route('construction-notices.index'))->assertOk();
        $this->actingAs($this->user)
            ->get(route('construction-notices.create', ['kind' => RenderDocumentKind::ConstructionConcernNotice->value]))
            ->assertOk()
            ->assertSee(__('construction.kind.concern'));
    }

    public function test_unknown_kind_is_rejected(): void {
        $this->actingAs($this->user)->get(route('construction-notices.create', ['kind' => 'invoice']))->assertNotFound();
    }

    public function test_notice_is_created_from_a_diary_entry_with_number_and_legal_reference(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $notice = $this->createNotice([
            'diary_entry_id' => $entry->sqid,
            'customer_id' => $customer->sqid,
        ]);

        $this->assertSame(1, $notice->notice_no);
        $this->assertSame('BA-1', $notice->displayNo());
        $this->assertSame(RenderDocumentKind::ConstructionObstructionNotice, $notice->kind);
        $this->assertSame(ConstructionNoticeStatus::Draft, $notice->status);
        $this->assertSame((int) $entry->id, (int) $notice->diary_entry_id);
        $this->assertSame(__('construction.legal.obstruction'), $notice->legal_reference);
        $this->assertTrue($notice->claims_time_extension);

        // Verknüpfung ist im Tagebucheintrag sichtbar.
        $this->actingAs($this->user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee('BA-1');
    }

    public function test_numbers_run_per_organization(): void {
        $this->createNotice();
        $second = $this->createNotice(['kind' => RenderDocumentKind::ConstructionConcernNotice->value]);

        $this->assertSame(2, $second->notice_no);
        $this->assertSame('BE-2', $second->displayNo());
        $this->assertSame(__('construction.legal.concern'), $second->legal_reference);
    }

    public function test_pdf_renders(): void {
        $notice = $this->createNotice();

        $response = $this->actingAs($this->user)->get(route('construction-notices.pdf', $notice));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_mail_dispatch_creates_a_delivery_record_and_freezes_the_notice(): void {
        Mail::fake();
        $notice = $this->createNotice();

        $this->actingAs($this->user)
            ->post(route('construction-notices.mail', $notice), ['to' => ['bauherr@example.test']])
            ->assertRedirect(route('construction-notices.show', $notice));

        Mail::assertQueued(DocumentMail::class);

        $dispatch = DocumentDispatch::withoutGlobalScopes()
            ->forDocument($notice->kind, (int) $notice->id)
            ->firstOrFail();
        $this->assertSame(DocumentDispatch::CHANNEL_EMAIL, $dispatch->channel);
        $this->assertSame('bauherr@example.test', $dispatch->recipient);

        $notice->refresh();
        $this->assertSame(ConstructionNoticeStatus::Sent, $notice->status);
        $this->assertNotNull($notice->sent_at);
        $this->assertFalse($notice->isEditable());
    }

    public function test_manual_delivery_is_recorded_as_proof_and_freezes_the_notice(): void {
        $notice = $this->createNotice();

        $this->actingAs($this->user)
            ->post(route('construction-notices.delivery', $notice), [
                'method' => 'registered_mail',
                'delivered_at' => now()->toDateString(),
                'recipient' => 'Bauherr GmbH',
                'reference' => 'RR123456789DE',
            ])->assertRedirect();

        $dispatch = DocumentDispatch::withoutGlobalScopes()
            ->forDocument($notice->kind, (int) $notice->id)
            ->firstOrFail();
        $this->assertSame(DocumentDispatch::CHANNEL_MANUAL, $dispatch->channel);
        $this->assertSame('sent', $dispatch->status);
        $this->assertSame('Bauherr GmbH', $dispatch->recipient);
        $this->assertSame('registered_mail', $dispatch->meta['method'] ?? null);
        $this->assertSame('RR123456789DE', $dispatch->meta['reference'] ?? null);
        $this->assertNotNull($dispatch->sha256);

        $notice->refresh();
        $this->assertSame(ConstructionNoticeStatus::Sent, $notice->status);
    }

    public function test_sent_notice_is_immutable(): void {
        $notice = $this->createNotice();
        $this->actingAs($this->user)->post(route('construction-notices.delivery', $notice), [
            'method' => 'handover',
            'delivered_at' => now()->toDateString(),
            'recipient' => 'Bauleitung',
        ])->assertRedirect();

        $this->actingAs($this->user)
            ->put(route('construction-notices.update', $notice), $this->payload(['subject' => 'Nachträglich geändert']))
            ->assertStatus(422);
        $this->actingAs($this->user)->get(route('construction-notices.edit', $notice))->assertStatus(422);
        $this->actingAs($this->user)->delete(route('construction-notices.destroy', $notice))->assertStatus(422);

        $this->assertSame('Fehlende Vorleistung Rohbau', $notice->refresh()->subject);
    }

    public function test_acknowledgement_is_only_possible_after_the_delivery(): void {
        $notice = $this->createNotice();

        $this->actingAs($this->user)
            ->post(route('construction-notices.acknowledge', $notice), ['acknowledged_note' => 'Per Mail bestätigt'])
            ->assertSessionHas('error');

        $this->actingAs($this->user)->post(route('construction-notices.delivery', $notice), [
            'method' => 'courier',
            'delivered_at' => now()->toDateString(),
            'recipient' => 'Bauleitung',
        ]);

        $this->actingAs($this->user)
            ->post(route('construction-notices.acknowledge', $notice), ['acknowledged_note' => 'Per Mail bestätigt'])
            ->assertRedirect();

        $notice->refresh();
        $this->assertSame(ConstructionNoticeStatus::Acknowledged, $notice->status);
        $this->assertSame('Per Mail bestätigt', $notice->acknowledged_note);
    }

    public function test_draft_can_be_deleted(): void {
        $notice = $this->createNotice();

        $this->actingAs($this->user)->delete(route('construction-notices.destroy', $notice))->assertRedirect();
        $this->assertSame(0, ConstructionNotice::withoutGlobalScopes()->count());
    }

    public function test_notice_of_another_organization_is_not_reachable(): void {
        $notice = $this->createNotice();

        $other = Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $other->id]);
        app()->instance('currentOrganization', $other);

        $this->actingAs($stranger)->get(route('construction-notices.show', $notice))->assertNotFound();
        $this->actingAs($stranger)->get(route('construction-notices.pdf', $notice))->assertNotFound();
    }

    public function test_module_gate_locks_the_module_for_a_free_plan(): void {
        $this->organization->forceFill(['plan' => Organization::PLAN_FREE])->save();
        app()->instance('currentOrganization', $this->organization->fresh());

        $this->actingAs($this->user)->get(route('construction-notices.index'))->assertStatus(423);
    }
}
