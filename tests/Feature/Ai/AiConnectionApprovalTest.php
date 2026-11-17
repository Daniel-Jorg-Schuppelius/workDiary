<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionApprovalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\Ai\{AiConnectionStatus, AiProviderType};
use App\Enums\User\Permission;
use App\Models\Ai\AiProviderConnection;
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Services\Ai\AiConnectionPrivacy;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeAiProviderFactory;
use Tests\TestCase;

/**
 * Datenschutzfreigabe je KI-Verbindung (Feature 025, MVP-1672): Freigabe mit
 * AVV-Dokument, Widerruf, Rechte `ai.approve`, Einstellungen der
 * Organisation, Grund im Vortest und Aufgaben im Arbeitsvorrat.
 */
class AiConnectionApprovalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $approver;

    private User $manager;

    protected function setUp(): void {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
        FakeAiProviderFactory::install();
        Storage::fake('local');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->approver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Paula Prüferin']);
        $this->approver->givePermissionTo(Permission::AiApprove->value);
        $this->manager = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->manager->givePermissionTo(Permission::AiManage->value);
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function unapproved(array $attributes = []): AiProviderConnection {
        return AiProviderConnection::factory()->unapproved()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    private function pdf(): UploadedFile {
        return UploadedFile::fake()->createWithContent('avv-anbieter.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    }

    public function test_cloud_connection_is_approved_with_document_and_audited(): void {
        $connection = $this->unapproved(['name' => 'Cloud-Anbieter']);

        $this->actingAs($this->approver)->get(route('admin.ai.approval.edit', $connection))->assertOk()->assertSee('Cloud-Anbieter');
        $this->actingAs($this->approver)->post(route('admin.ai.approval.store', $connection), [
            'has_dpa' => '1',
            'is_eu_processing' => '1',
            'is_training_excluded' => '1',
            'dpa_document' => $this->pdf(),
        ])->assertRedirect(route('admin.ai.index'));

        $connection->refresh();
        $this->assertTrue($connection->has_dpa && $connection->is_eu_processing && $connection->is_training_excluded);
        $this->assertSame('2026-11-12 10:00:00', $connection->privacy_reviewed_at?->format('Y-m-d H:i:s'));
        $this->assertSame((int) $this->approver->id, $connection->privacy_reviewed_by);
        $this->assertSame('avv-anbieter.pdf', $connection->dpaDocument()?->original_name);
        $this->assertTrue(app(AiConnectionPrivacy::class)->state($connection, $this->organization)->isApproved());
        $this->assertTrue(AuditLog::query()
            ->where('event', 'ai.connection_approved')
            ->where('auditable_type', MorphMap::stableKey(AiProviderConnection::class))
            ->where('auditable_id', $connection->id)
            ->exists());
    }

    public function test_cloud_approval_needs_dpa_document_eu_and_no_training(): void {
        $connection = $this->unapproved();

        $this->actingAs($this->approver)->post(route('admin.ai.approval.store', $connection), [
            'has_dpa' => '1', 'is_eu_processing' => '1', 'is_training_excluded' => '1',
        ])->assertSessionHasErrors('dpa_document');
        $this->actingAs($this->approver)->post(route('admin.ai.approval.store', $connection), [
            'has_dpa' => '0', 'is_eu_processing' => '1', 'is_training_excluded' => '1', 'dpa_document' => $this->pdf(),
        ])->assertSessionHasErrors('has_dpa');
        $this->actingAs($this->approver)->post(route('admin.ai.approval.store', $connection), [
            'has_dpa' => '1', 'is_eu_processing' => '0', 'is_training_excluded' => '0', 'dpa_document' => $this->pdf(),
        ])->assertSessionHasErrors(['is_eu_processing', 'is_training_excluded']);

        $this->assertNull($connection->refresh()->privacy_reviewed_at);
        $this->assertNull($connection->dpaDocument(), 'Abgelehnte Freigabe legt kein Dokument ab.');
    }

    public function test_local_connection_is_approved_without_processor_agreement(): void {
        $connection = $this->unapproved(['provider' => AiProviderType::Ollama, 'name' => 'Ollama im Haus']);

        $this->actingAs($this->approver)->post(route('admin.ai.approval.store', $connection), [
            'has_dpa' => '0', 'is_eu_processing' => '1', 'is_training_excluded' => '1',
        ])->assertRedirect(route('admin.ai.index'));

        $this->assertTrue(app(AiConnectionPrivacy::class)->state($connection->refresh(), $this->organization)->isApproved());
    }

    public function test_revocation_ends_the_approval_and_is_audited(): void {
        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->approver)->delete(route('admin.ai.approval.destroy', $connection))->assertRedirect(route('admin.ai.index'));

        $this->assertNull($connection->refresh()->privacy_reviewed_at);
        $this->assertTrue(AuditLog::query()->where('event', 'ai.connection_approval_revoked')->where('auditable_id', $connection->id)->exists());
    }

    public function test_only_the_approval_right_grants_and_sets_privacy(): void {
        $connection = $this->unapproved();

        $this->actingAs($this->manager)->get(route('admin.ai.approval.edit', $connection))->assertForbidden();
        $this->actingAs($this->manager)->post(route('admin.ai.approval.store', $connection), ['is_eu_processing' => '1'])->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.ai.privacy.edit'))->assertForbidden();
        $this->actingAs($this->manager)->put(route('admin.ai.privacy.update'), ['approval_months' => 6])->assertForbidden();

        // Wer freigibt, sieht die Verbindungen, verwaltet sie aber nicht.
        $this->actingAs($this->approver)->get(route('admin.ai.index'))->assertOk()->assertSee(__('ai.privacy.approval.action'));
        $this->actingAs($this->approver)->post(route('admin.ai.block', $connection))->assertForbidden();
    }

    public function test_privacy_settings_are_stored_for_the_organization(): void {
        $this->actingAs($this->approver)->get(route('admin.ai.privacy.edit'))->assertOk();
        $this->actingAs($this->approver)->put(route('admin.ai.privacy.update'), [
            'require_approval' => '0',
            'approval_months' => '6',
            'mask_local' => '1',
        ])->assertRedirect(route('admin.ai.index'));

        $settings = $this->organization->refresh()->settings;
        $this->assertFalse(data_get($settings, 'ai.privacy.require_approval'));
        $this->assertSame(6, data_get($settings, 'ai.privacy.approval_months'));
        $this->assertTrue(data_get($settings, 'ai.privacy.mask_local'));

        $this->actingAs($this->approver)->put(route('admin.ai.privacy.update'), ['approval_months' => '0'])->assertSessionHasErrors('approval_months');
    }

    public function test_preflight_names_the_missing_approval(): void {
        $connection = $this->unapproved(['status' => AiConnectionStatus::Draft]);

        $this->actingAs($this->manager)->post(route('admin.ai.test', $connection))
            ->assertSessionHas('warning', static fn (string $message): bool => str_contains($message, (string) __('ai.privacy.reason.missing')));

        $this->assertSame(AiConnectionStatus::Active, $connection->refresh()->status, 'Technisch geprüft bleibt technisch geprüft.');
    }

    /** Neue Adresse, anderes Modell oder neuer Schlüssel: ein anderer Empfänger der Daten — die Freigabe ist hinfällig. */
    public function test_changing_address_model_or_key_resets_the_approval_audited(): void {
        $changes = [
            'base_url' => ['base_url' => 'https://ki.example.test/v1'],
            'model' => ['model' => 'anderes-modell'],
            'api_key' => ['api_key' => 'neuer-schluessel'],
        ];
        foreach ($changes as $label => $change) {
            $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud ' . $label]);

            $this->actingAs($this->manager)->get(route('admin.ai.edit', $connection))->assertOk()
                ->assertSee((string) __('ai.privacy.approval.change_hint'));
            $this->actingAs($this->manager)->patch(route('admin.ai.update', $connection), ['name' => 'Cloud ' . $label] + $change)
                ->assertRedirect(route('admin.ai.index'))
                ->assertSessionHas('warning', static fn (string $message): bool => str_contains($message, (string) __('ai.privacy.flash.approval_reset', ['name' => 'Cloud ' . $label])));

            $connection->refresh();
            $this->assertNull($connection->privacy_reviewed_at, $label . ' setzt die Freigabe zurück.');
            $this->assertNull($connection->privacy_reviewed_by);
            $this->assertTrue($connection->is_eu_processing, 'Die Angaben bleiben als Stand der letzten Prüfung.');
            $this->assertFalse(app(AiConnectionPrivacy::class)->permits($connection, $this->organization));
            $this->assertTrue(AuditLog::query()->where('event', 'ai.connection_approval_revoked')->where('auditable_id', $connection->id)->exists(), $label . ': Zurücksetzen ist auditiert.');
        }
    }

    public function test_renaming_or_saving_unchanged_keeps_the_approval(): void {
        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud alt', 'model' => 'modell-a']);
        $reviewedAt = $connection->privacy_reviewed_at?->format('Y-m-d H:i:s');

        // Neuer Name; Modell und Schlüssel unverändert (derselbe Schlüssel erneut eingegeben).
        $this->actingAs($this->manager)->patch(route('admin.ai.update', $connection), ['name' => 'Cloud neu', 'model' => 'modell-a', 'api_key' => 'test-key'])
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $connection->refresh();
        $this->assertSame('Cloud neu', $connection->name);
        $this->assertSame($reviewedAt, $connection->privacy_reviewed_at?->format('Y-m-d H:i:s'));
        $this->assertFalse(AuditLog::query()->where('event', 'ai.connection_approval_revoked')->where('auditable_id', $connection->id)->exists());
    }

    public function test_unapproved_connection_shows_no_reset_hint(): void {
        $connection = $this->unapproved();

        $this->actingAs($this->manager)->get(route('admin.ai.edit', $connection))->assertOk()
            ->assertDontSee((string) __('ai.privacy.approval.change_hint'));
    }

    public function test_work_queue_lists_open_and_expiring_approvals_for_approvers_only(): void {
        $this->unapproved(['name' => 'Cloud A']);
        AiProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud B']);
        AiProviderConnection::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Cloud C', 'privacy_reviewed_at' => CarbonImmutable::now()->subMonths(11)->subDays(10)]);
        $this->unapproved(['name' => 'Cloud D', 'status' => AiConnectionStatus::Blocked]);

        $items = $this->items($this->approver);

        // Mit Frist vor ohne Frist (Sortierung des Arbeitsvorrats).
        $this->assertSame(['Freigabe der KI-Verbindung „Cloud C“ erneuern', 'KI-Verbindung „Cloud A“ datenschutzrechtlich freigeben'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame('2026-12-02', $items[0]->dueAt?->toDateString());
        $this->assertSame((string) __('work-queue.detail.ai_approval_expires', ['date' => '02.12.2026']), $items[0]->detail);
        $this->assertNull($items[1]->dueAt);
        $this->assertSame((string) __('work-queue.detail.ai_approval_blocking'), $items[1]->detail);
        $this->assertSame([], $this->items($this->manager));
    }

    public function test_work_queue_lists_open_approvals_while_approval_is_optional(): void {
        $this->organization->forceFill(['settings' => ['ai' => ['privacy' => ['require_approval' => false]]]])->save();
        $this->unapproved(['name' => 'Bestand']);

        $items = $this->items($this->approver);

        $this->assertCount(1, $items);
        $this->assertSame((string) __('work-queue.detail.ai_approval_optional'), $items[0]->detail);
    }

    /** @return list<WorkItem> */
    private function items(User $viewer): array {
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return array_values(array_filter(
            $queue->itemsFor($viewer, $this->organization),
            static fn (WorkItem $item): bool => $item->source === 'ai_connection_approval',
        ));
    }
}
