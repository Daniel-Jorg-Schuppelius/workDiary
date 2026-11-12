<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalAssetPhotoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Disposal;

use App\Enums\Asset\AssetStatus;
use App\Enums\Disposal\{DisposalJobEventType, DisposalJobStatus};
use App\Models\Asset\{Asset, AssetAssignment};
use App\Models\Attachments\Attachment;
use App\Models\Customer\Customer;
use App\Models\Disposal\{DisposalItem, DisposalJob};
use App\Models\Platform\User;
use App\Services\Disposal\DisposalRecordPdfRenderer;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1578: Asset an der Geräteposition (Auswahl, Vorbelegung, Anzeige,
 * Ausmusterung beim Abschluss) und Fotos je Position — über die HTTP-Wege.
 */
final class DisposalAssetPhotoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /** @param array<string, mixed> $overrides */
    private function asset(array $overrides = []): Asset {
        return Asset::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Etagendrucker',
            'manufacturer' => 'Druckwerk',
            'model' => 'DX-40',
            'serial_no' => 'DX40-0815',
        ], $overrides));
    }

    private function job(): DisposalJob {
        return DisposalJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function itemData(array $overrides = []): array {
        return array_merge(['category' => 'Drucker', 'quantity' => 1, 'avv_code' => '16 02 14'], $overrides);
    }

    public function test_item_dialog_offers_assets_in_operation_with_prefill_values(): void {
        $asset = $this->asset();
        $retired = $this->asset(['name' => 'Altgerät', 'status' => AssetStatus::Decommissioned->value]);
        $job = $this->job();

        $this->get(route('disposal.show', $job))->assertOk()
            ->assertSee('name="asset_id"', false)
            ->assertSee('value="' . $asset->sqid . '"', false)
            ->assertSee('data-prefill-serial="DX40-0815"', false)
            ->assertSee('data-prefill-from="asset_id"', false)
            ->assertDontSee('value="' . $retired->sqid . '"', false);
    }

    public function test_completion_decommissions_the_linked_asset_over_http(): void {
        $asset = $this->asset();
        $job = $this->job();

        $this->post(route('disposal.items.store', $job), $this->itemData(['asset_id' => $asset->sqid, 'serial_number' => 'DX40-0815']))
            ->assertRedirect(route('disposal.show', $job))->assertSessionHasNoErrors();
        $item = DisposalItem::query()->sole();
        $this->assertSame($asset->id, $item->asset_id);

        $this->get(route('disposal.show', $job))->assertOk()->assertSee(route('assets.show', $asset), false)->assertSee('Etagendrucker');

        $this->post(route('disposal.collect', $job))->assertSessionHasNoErrors();
        $this->post(route('disposal.handed-over', $job))->assertSessionHasNoErrors();
        $this->post(route('disposal.sign', $job), ['signer_name' => 'Kundin Beispiel', 'signature' => self::PNG])->assertSessionHasNoErrors();
        $this->post(route('disposal.complete', $job))->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $this->assertSame(DisposalJobStatus::Completed, $job->fresh()->status);
        $asset->refresh();
        $this->assertSame(AssetStatus::Decommissioned, $asset->status);
        $this->assertNotNull($asset->decommissioned_on);
    }

    public function test_checked_out_asset_is_reported_instead_of_silently_skipped(): void {
        $asset = $this->asset();
        AssetAssignment::query()->create([
            'organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'assigned_to_user_id' => $this->admin->id,
            'checked_out_at' => now()->subDay(), 'checked_out_by_user_id' => $this->admin->id,
        ]);
        $job = $this->job();
        $this->post(route('disposal.items.store', $job), $this->itemData(['asset_id' => $asset->sqid]))->assertSessionHasNoErrors();
        $this->post(route('disposal.collect', $job));
        $this->post(route('disposal.handed-over', $job));
        $this->post(route('disposal.sign', $job), ['signer_name' => 'Kundin Beispiel', 'signature' => self::PNG]);

        $this->post(route('disposal.complete', $job))->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn (string $text): bool => str_contains($text, 'Etagendrucker'));
        $this->assertSame(DisposalJobStatus::Completed, $job->fresh()->status);
        $this->assertTrue($asset->fresh()->isInOperation());
        $this->get(route('disposal.show', $job))->assertOk()->assertSee(__('disposal.asset.pending_decommission', ['assets' => 'Etagendrucker']));
    }

    public function test_asset_must_be_in_operation_own_and_not_in_another_open_job(): void {
        $job = $this->job();
        $retired = $this->asset(['status' => AssetStatus::Decommissioned->value]);
        $this->post(route('disposal.items.store', $job), $this->itemData(['asset_id' => $retired->sqid]))->assertSessionHasErrors('asset_id');

        $foreign = Asset::factory()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        $this->post(route('disposal.items.store', $job), $this->itemData(['asset_id' => $foreign->sqid]))->assertSessionHasErrors('asset_id');

        $asset = $this->asset();
        $this->post(route('disposal.items.store', $job), $this->itemData(['asset_id' => $asset->sqid]))->assertSessionHasNoErrors();
        $this->post(route('disposal.items.store', $this->job()), $this->itemData(['asset_id' => $asset->sqid]))->assertSessionHasErrors('asset_id');

        // Die eigene Position behält ihr Asset beim Bearbeiten und kann es wieder lösen.
        $item = DisposalItem::query()->sole();
        $this->put(route('disposal.items.update', $item), $this->itemData(['asset_id' => $asset->sqid, 'model' => 'DX-40']))->assertSessionHasNoErrors();
        $this->assertSame($asset->id, $item->fresh()->asset_id);
        $this->put(route('disposal.items.update', $item), $this->itemData(['asset_id' => '']))->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->asset_id);
    }

    public function test_asset_file_offers_disposal_and_carries_the_asset_into_the_job(): void {
        $asset = $this->asset();

        $this->get(route('assets.show', $asset))->assertOk()->assertSee(route('disposal.create', ['asset' => $asset->sqid]), false);
        $this->get(route('disposal.create', ['asset' => $asset->sqid]))->assertOk()
            ->assertSee('name="asset" value="' . $asset->sqid . '"', false)
            ->assertSee('<option value="' . $this->customer->sqid . '" selected', false);

        $this->post(route('disposal.store'), ['customer_id' => $this->customer->sqid, 'asset' => $asset->sqid])->assertSessionHasNoErrors();
        $job = DisposalJob::query()->sole();
        $this->get(route('disposal.show', ['disposalJob' => $job, 'asset' => $asset->sqid]))->assertOk()
            ->assertSee(__('disposal.asset.add_hint', ['asset' => 'Etagendrucker']))
            ->assertSee('<option value="' . $asset->sqid . '" selected', false)
            ->assertSee('value="DX40-0815"', false);

        // Außer Betrieb oder Modul aus: kein Einstieg.
        $retired = $this->asset(['status' => AssetStatus::Decommissioned->value]);
        $this->get(route('assets.show', $retired))->assertOk()->assertDontSee('entsorgung/neu', false);
        config(['license.feature_overrides' => ['module.entsorgung' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->get(route('assets.show', $asset))->assertOk()->assertDontSee('entsorgung/neu', false);
    }

    public function test_photos_per_item_upload_show_lock_and_appear_in_the_record_on_request(): void {
        $job = $this->job();
        $item = DisposalItem::factory()->create(['disposal_job_id' => $job->id, 'category' => 'Drucker']);

        $this->get(route('disposal.show', $job))->assertOk()->assertSee(route('disposal.items.photos.store', $item), false);
        $this->post(route('disposal.items.photos.store', $item), ['photo' => UploadedFile::fake()->create('notiz.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->post(route('disposal.items.photos.store', $item), ['photo' => UploadedFile::fake()->image('zustand.png', 40, 30)])
            ->assertRedirect(route('disposal.show', $job))->assertSessionHasNoErrors();

        $photo = Attachment::query()->sole();
        $this->assertTrue($item->is($photo->attachable));
        $this->assertSame(1, $job->journal()->where('event', DisposalJobEventType::ItemPhotoAdded->value)->count());
        $this->get(route('disposal.show', $job))->assertOk()->assertSee('<img', false)->assertSee(__('disposal.photo.alt', ['item' => 'Drucker']));

        // Fremde ohne Recht an der Akte laden nichts hoch.
        $this->actingAs($this->orgUser())->post(route('disposal.items.photos.store', $item), ['photo' => UploadedFile::fake()->image('x.png')])->assertForbidden();
        $this->actingAs($this->admin);

        // Nachweis: Fotos nur auf Wunsch.
        $photosFor = fn (DisposalJob $record): array => (new \ReflectionMethod(DisposalRecordPdfRenderer::class, 'itemPhotos'))
            ->invoke(app(DisposalRecordPdfRenderer::class), $record->load('items.attachments'));
        $this->put(route('disposal.update', $job), ['customer_id' => $this->customer->sqid, 'is_photo_in_record' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($job->fresh()->is_photo_in_record);
        $this->assertCount(1, $photosFor($job->fresh())[$item->id] ?? []);
        $this->assertStringStartsWith('%PDF', app(DisposalRecordPdfRenderer::class)->render($job->fresh()));

        // Nach der Entsorger-Übergabe ist die Position ein Nachweis: kein Upload, kein Löschen.
        $job->forceFill(['status' => DisposalJobStatus::HandedOver->value])->save();
        $this->post(route('disposal.items.photos.store', $item), ['photo' => UploadedFile::fake()->image('spaet.png')])->assertForbidden();
        $this->delete(route('attachments.destroy', $photo))->assertForbidden();
        $this->assertSame(1, Attachment::query()->count());

        // Solange die Akte änderbar ist, lässt sich das Foto entfernen — mit Journaleintrag.
        $job->forceFill(['status' => DisposalJobStatus::Collected->value])->save();
        $this->delete(route('attachments.destroy', $photo))->assertSessionHasNoErrors();
        $this->assertSame(0, Attachment::query()->count());
        $this->assertSame(1, $job->journal()->where('event', DisposalJobEventType::ItemPhotoRemoved->value)->count());
    }
}
