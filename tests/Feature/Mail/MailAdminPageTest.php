<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAdminPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\Mail\EmailConnection;
use App\Models\Platform\{Organization, User};
use App\Plugins\Msgraph\Enums\MsgraphConnectionStatus;
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Services\Mail\{MailboxCursor, MailboxGateway};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakeMailboxGateway;
use Tests\TestCase;

/**
 * Verwaltung der Postfächer (MVP-1143): Dialoge je Transport, Bearbeiten,
 * Aktivieren, Nachholen und „ein Zugang, mehrere Ordner“ (E19).
 */
final class MailAdminPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->app->instance(MailboxGateway::class, new FakeMailboxGateway());
    }

    /** @param array<string, mixed> $attributes */
    private function imapConnection(array $attributes = []): EmailConnection {
        return EmailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Info',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'info@example.com',
            'password' => 'geheim',
            'folder' => 'INBOX',
            'active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function imapPayload(array $overrides = []): array {
        return $overrides + [
            'name' => 'Rechnungen',
            'transport' => 'imap',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'info@example.com',
            'password' => 'geheim',
            'folder' => 'INBOX/Rechnungen',
            'active' => '1',
            'einvoice_intake' => '1',
        ];
    }

    public function test_index_lists_mailboxes_with_folder_purpose_and_state(): void {
        $this->imapConnection(['einvoice_intake' => true, 'processed_folder' => 'Erledigt']);

        $this->actingAs($this->admin)->get(route('admin.mail.index'))
            ->assertOk()
            ->assertSee('info@example.com')
            ->assertSee('Erledigt')
            ->assertSee(__('mail.purpose.invoices'))
            ->assertSee(route('admin.mail.connection.create', ['transport' => 'imap']), false);
    }

    public function test_dialog_shows_only_the_fields_of_its_transport(): void {
        $this->actingAs($this->admin)->get(route('admin.mail.connection.create', ['transport' => 'imap']))
            ->assertOk()
            ->assertSee('name="host"', false)
            ->assertDontSee('name="mailbox_address"', false);

        $this->pluginSecret('msgraph', ['client_id' => 'cid', 'client_secret' => 'sec']);
        MsgraphMailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'mail-token',
            'status' => MsgraphConnectionStatus::Active,
        ]);
        $this->actingAs($this->admin)->get(route('admin.mail.connection.create', ['transport' => MsgraphPlugin::MAIL_TRANSPORT]))
            ->assertOk()
            ->assertSee('name="mailbox_address"', false)
            ->assertDontSee('name="host"', false)
            ->assertDontSee('name="password"', false)
            ->assertSee('value="INBOX"', false);
    }

    public function test_edit_keeps_password_and_folder_change_restarts_the_cursor(): void {
        $connection = $this->imapConnection();
        $connection->forceFill(['sync_cursor' => MailboxCursor::start($connection)->with(['lastUid' => 99, 'catchingUp' => false])->toArray()])->save();

        $this->actingAs($this->admin)->get(route('admin.mail.connection.edit', $connection))->assertOk()->assertSee('INBOX');
        $this->actingAs($this->admin)->put(route('admin.mail.connection.update', $connection), $this->imapPayload([
            'name' => 'Info',
            'password' => '',
            'folder' => 'INBOX/Rechnungen',
        ]))->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame('geheim', $connection->password);
        $this->assertSame('INBOX/Rechnungen', $connection->folder);
        $cursor = MailboxCursor::fromArray((array) $connection->sync_cursor);
        $this->assertNull($cursor->lastUid);
        $this->assertSame('INBOX/Rechnungen', $cursor->folder);
    }

    public function test_one_account_may_have_several_folders_but_each_only_once(): void {
        $this->imapConnection(['folder' => 'INBOX']);

        $this->actingAs($this->admin)->post(route('admin.mail.connection.store'), $this->imapPayload(['folder' => 'INBOX/Rechnungen']))
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.mail.connection.store'), $this->imapPayload(['name' => 'Doppelt', 'folder' => 'inbox']))
            ->assertSessionHasErrors('folder');

        $this->assertSame(2, EmailConnection::query()->count());
    }

    public function test_chained_folders_produce_a_warning(): void {
        $this->imapConnection(['folder' => 'INBOX', 'processed_folder' => 'Rechnungen']);

        $this->actingAs($this->admin)->post(route('admin.mail.connection.store'), $this->imapPayload(['folder' => 'Rechnungen']))
            ->assertSessionHas('warning');
    }

    public function test_new_imap_mailbox_needs_a_password(): void {
        $this->actingAs($this->admin)->post(route('admin.mail.connection.store'), $this->imapPayload(['password' => '']))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, EmailConnection::query()->count());
    }

    public function test_backfill_moves_the_start_and_restarts_the_cursor(): void {
        Carbon::setTestNow('2026-10-12 09:00:00');
        $connection = $this->imapConnection(['fetch_since' => '2026-10-01']);
        $connection->forceFill(['sync_cursor' => MailboxCursor::start($connection)->with(['lastUid' => 40, 'catchingUp' => false])->toArray()])->save();

        $this->actingAs($this->admin)->get(route('admin.mail.connection.backfill.form', $connection))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.mail.connection.backfill', $connection), ['fetch_since' => '2026-01-01'])
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.mail.connection.backfill', $connection), ['fetch_since' => '2026-12-01'])
            ->assertSessionHasErrors('fetch_since');

        $connection->refresh();
        $this->assertSame('2026-01-01', $connection->fetch_since?->toDateString());
        $cursor = MailboxCursor::fromArray((array) $connection->sync_cursor);
        $this->assertNull($cursor->lastUid);
        $this->assertTrue($cursor->backfill);
        $this->assertTrue($cursor->catchingUp);
        Carbon::setTestNow();
    }

    public function test_deactivated_mailbox_can_be_activated_again(): void {
        $connection = $this->imapConnection(['active' => false]);

        $this->actingAs($this->admin)->post(route('admin.mail.connection.activate', $connection))->assertSessionHas('success');

        $this->assertTrue($connection->refresh()->active);
    }

    public function test_foreign_mailbox_and_non_admins_are_rejected(): void {
        $connection = $this->imapConnection();
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.mail.connection.edit', $connection))->assertForbidden();

        $other = Organization::factory()->create();
        $foreign = EmailConnection::query()->withoutGlobalScopes()->create([
            'organization_id' => $other->id, 'name' => 'Fremd', 'host' => 'h', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'u', 'password' => 'p', 'folder' => 'INBOX', 'active' => true,
        ]);
        $this->actingAs($this->admin)->get(route('admin.mail.connection.edit', $foreign))->assertNotFound();
    }
}
