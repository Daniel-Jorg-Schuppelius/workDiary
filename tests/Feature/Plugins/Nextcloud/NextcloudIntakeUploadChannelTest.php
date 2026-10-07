<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudIntakeUploadChannelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Nextcloud;

use App\Enums\Customer\IntakeKind;
use App\Models\Customer\{Customer, CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\{PluginSetting, User};
use App\Modules\ModuleRegistry;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\Services\NextcloudIntakeUploadChannel;
use App\Services\Customer\Contracts\IntakeUploadChannel;
use App\Services\Customer\Intake\{CustomerIntakeUploadChannels, IntakeTemplates};
use App\Services\Fields\{FieldDocument, FieldValues};
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\Support\FakeNextcloudTransportFactory;
use Tests\TestCase;

/**
 * MVP-1078: Nextcloud als Upload-Kanal — Ordner je Eingang, öffentliche
 * Freigabe „nur hochladen" mit Passwort und Ablauf über die OCS-API,
 * Übernahme der Dateien per WebDAV und Widerruf; eigene Zugangsdaten in den
 * Plugin-Einstellungen.
 */
final class NextcloudIntakeUploadChannelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private CustomerIntake $intake;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer, ['intakes']);
        $this->portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $schema = app(IntakeTemplates::class)->schema(IntakeKind::Print);
        $this->intake = CustomerIntake::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'submitted_by_user_id' => $this->portalUser->id,
            'number' => 'KE-2026-0042',
            'form' => new FieldDocument($schema, FieldValues::normalize($schema, ['product' => 'Plakat', 'final_format' => 'a3', 'color_mode' => '4_0', 'delivery' => 'pickup'])),
            'submission_key' => (string) Str::uuid(),
        ]);
    }

    private function configure(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'nextcloud',
            'enabled' => true,
            'settings' => [
                'intake_server_url' => 'https://nextcloud.test',
                'intake_username' => 'wd',
                'intake_app_password' => 'app-pw',
                'intake_base_folder' => 'WorkDiary/Kundeneingaenge',
                'intake_link_days' => '10',
            ],
        ]);
    }

    /** @param list<Response> $responses */
    private function fake(array $responses): FakeNextcloudTransportFactory {
        $factory = new FakeNextcloudTransportFactory($responses);
        $this->app->instance(NextcloudTransportFactory::class, $factory);

        return $factory;
    }

    public function test_channel_is_unavailable_without_own_credentials(): void {
        $this->assertFalse(app(NextcloudIntakeUploadChannel::class)->isAvailable($this->organization));

        $this->configure();
        $this->assertTrue(app(NextcloudIntakeUploadChannel::class)->isAvailable($this->organization));
        $this->assertSame(10, app(NextcloudIntakeUploadChannel::class)->linkLifetimeDays($this->organization));
    }

    public function test_open_creates_folder_and_upload_only_share_then_imports_and_revokes(): void {
        $this->configure();
        app(ModuleRegistry::class)->contribute(IntakeUploadChannel::class, NextcloudIntakeUploadChannel::class);
        $folderHref = '/remote.php/dav/files/wd/WorkDiary/Kundeneingaenge/KE-2026-0042/';
        $factory = $this->fake([
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(200, '{"ocs":{"meta":{"status":"ok","statuscode":200},"data":{"id":77,"url":"https://nextcloud.test/s/Abc123","token":"Abc123"}}}', ['Content-Type' => 'application/json']),
            FakeNextcloudTransportFactory::folder($folderHref, [
                ['path' => $folderHref . 'plakat.pdf', 'fileid' => '501', 'etag' => 'e1', 'mime' => 'application/pdf', 'size' => 17],
            ]),
            FakeNextcloudTransportFactory::response(200, "%PDF-1.7\nPlakat!"),
            FakeNextcloudTransportFactory::response(200, '{"ocs":{"meta":{"status":"ok","statuscode":200},"data":[]}}', ['Content-Type' => 'application/json']),
        ]);
        $channels = app(CustomerIntakeUploadChannels::class);

        $link = $channels->open($this->intake, $this->portalUser);

        $this->assertSame('nextcloud', $link->channel);
        $this->assertSame('77', $link->external_id);
        $this->assertSame('https://nextcloud.test/s/Abc123', $link->url);
        $this->assertSame('WorkDiary/Kundeneingaenge/KE-2026-0042', $link->folder);
        $share = $factory->history[3]['request'];
        $this->assertSame('POST', $share->getMethod());
        $this->assertStringEndsWith('/ocs/v2.php/apps/files_sharing/api/v1/shares', (string) $share->getUri());
        $this->assertSame('true', $share->getHeaderLine('OCS-APIRequest'));
        parse_str((string) $share->getBody(), $form);
        $this->assertSame('/WorkDiary/Kundeneingaenge/KE-2026-0042', $form['path']);
        $this->assertSame('3', $form['shareType']);
        $this->assertSame('4', $form['permissions']);
        $this->assertSame((string) $link->password, $form['password']);
        $this->assertSame($link->expires_at?->toDateString(), $form['expireDate']);
        $this->assertStringStartsWith('Basic ', $share->getHeaderLine('Authorization'));

        $result = $channels->sync($link);
        $this->assertSame(['imported' => 1, 'rejected' => 0, 'failed' => false], $result);
        $this->assertSame(['plakat.pdf'], $this->intake->attachments()->pluck('original_name')->all());
        $this->assertSame(['501:e1'], $link->refresh()->processed_keys);

        $channels->revoke($link, null, 'manual');
        $delete = $factory->history[6]['request'];
        $this->assertSame('DELETE', $delete->getMethod());
        $this->assertStringEndsWith('/shares/77', (string) $delete->getUri());
        $this->assertNotNull($link->refresh()->revoked_at);
        $this->assertSame(1, CustomerIntakeUploadLink::query()->withoutGlobalScopes()->count());
    }

    public function test_failed_share_creation_leaves_no_link(): void {
        $this->configure();
        app(ModuleRegistry::class)->contribute(IntakeUploadChannel::class, NextcloudIntakeUploadChannel::class);
        $this->fake([
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(201),
            FakeNextcloudTransportFactory::response(403, '{"ocs":{"meta":{"status":"failure","statuscode":403,"message":"Public upload disabled"},"data":[]}}', ['Content-Type' => 'application/json']),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(CustomerIntakeUploadChannels::class)->open($this->intake, $this->portalUser);
        } finally {
            $this->assertSame(0, CustomerIntakeUploadLink::query()->withoutGlobalScopes()->count());
        }
    }
}
