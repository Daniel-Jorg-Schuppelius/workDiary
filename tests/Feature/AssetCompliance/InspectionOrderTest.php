<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetCompliance;

use App\Enums\AssetCompliance\{AssetInspectionOrderStatus, AssetInspectionScheduleStatus};
use App\Mail\InspectionOrderMail;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetCalibrationCertificate, AssetComplianceProfile, AssetInspectionEvent, AssetInspectionOrder, AssetInspectionSchedule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\AssetCompliance\AssetComplianceService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-938: Prüfauftrag an einen Dienstleister — Angebot, Annahme, Ergebnisse, Übernahme. */
final class InspectionOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private AssetInspectionSchedule $schedule;

    private Supplier $provider;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->seed(AssetComplianceCatalogSeeder::class);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Messschieber', 'asset_no' => 'M-1']);
        $profile = AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'uvv_general')->firstOrFail();
        $assignment = app(AssetComplianceService::class)->assign($profile, $asset, $this->admin);
        $this->schedule = AssetInspectionSchedule::query()->create([
            'organization_id' => $this->organization->id, 'asset_compliance_assignment_id' => $assignment->id, 'asset_id' => $asset->id,
            'due_on' => now()->addDays(10)->toDateString(), 'status' => AssetInspectionScheduleStatus::Planned->value,
        ]);
        $this->provider = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kalibrierdienst GmbH']);
    }

    private function logout(): void {
        auth()->logout();
        app()->forgetInstance('currentOrganization');
    }

    public function test_order_runs_from_offer_to_inspection_record(): void {
        $this->actingAs($this->admin)->post(route('asset-compliance.orders.store'), [
            'title' => 'Kalibrierung 2026', 'supplier_id' => $this->provider->sqid, 'recipient_email' => 'labor@kalib.test', 'schedule_ids' => [$this->schedule->sqid],
        ])->assertRedirect();
        $order = AssetInspectionOrder::query()->sole();
        $this->assertSame(AssetInspectionScheduleStatus::Announced, $this->schedule->fresh()?->status);
        $token = null;
        Mail::assertQueued(InspectionOrderMail::class, function (InspectionOrderMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });
        $this->logout();

        $this->get(route('inspection-order.public', $token))->assertOk()->assertSee('Messschieber');
        $this->post(route('inspection-order.public.report', $token), ['items' => []])->assertNotFound();
        $this->post(route('inspection-order.public.offer', $token), ['offer_amount' => '180', 'offer_planned_on' => now()->addDays(5)->toDateString()])->assertRedirect();
        $this->assertSame(AssetInspectionOrderStatus::Offered, $order->fresh()?->status);

        $this->actingAs($this->admin)->post(route('asset-compliance.orders.decide', $order), ['decision' => 'accept'])->assertSessionHas('success');
        $this->logout();

        $item = $order->items()->sole();
        $this->post(route('inspection-order.public.report', $token), [
            'items' => [$item->sqid => ['result' => 'passed', 'performed_on' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'certificate_no' => 'K-4711']],
            'certificates' => [$item->sqid => UploadedFile::fake()->create('zertifikat.pdf', 20, 'application/pdf')],
        ])->assertRedirect();
        $this->assertSame(AssetInspectionOrderStatus::Reported, $order->fresh()?->status);
        $this->get(route('inspection-order.public', $token))->assertOk();

        $this->actingAs($this->admin)->post(route('asset-compliance.orders.take-over', $order))->assertSessionHas('success');
        $event = AssetInspectionEvent::query()->sole();
        $this->assertSame('Kalibrierdienst GmbH', $event->external_inspector_name);
        $this->assertSame('K-4711', AssetCalibrationCertificate::query()->sole()->certificate_no);
        $this->assertSame(AssetInspectionScheduleStatus::Done, $this->schedule->fresh()?->status);
        $this->assertSame(AssetInspectionOrderStatus::Completed, $order->fresh()?->status);
        $this->logout();
        $this->get(route('inspection-order.public', $token))->assertNotFound();
    }

    public function test_cancel_releases_schedules_and_rights(): void {
        $this->actingAs($this->admin)->post(route('asset-compliance.orders.store'), [
            'title' => 'x', 'supplier_id' => $this->provider->sqid, 'recipient_email' => 'labor@kalib.test', 'schedule_ids' => [$this->schedule->sqid],
        ]);
        $order = AssetInspectionOrder::query()->sole();
        $this->actingAs($this->admin)->post(route('asset-compliance.orders.cancel', $order))->assertSessionHas('success');
        $this->assertSame(AssetInspectionScheduleStatus::Planned, $this->schedule->fresh()?->status);

        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('asset-compliance.orders.create'))->assertForbidden();
    }
}
