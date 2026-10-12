<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxPollerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Exceptions\MailboxFolderMissingException;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Mail\EmailConnection;
use App\Services\Mail\{MailAttachment, MailIntakeService, MailboxCursor, MailboxGateway, MailboxPoller, ParsedMessage};
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeMailboxGateway;
use Tests\TestCase;

/**
 * Abruf nach Stand (MVP-1139): Stapel, Abrufstand, Sperre je Postfach,
 * Meldungen am Postfach und der MIME-Typ aus dem Inhalt (MVP-1142).
 */
final class MailboxPollerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['mail_intake.poll.batch' => 25]);
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): EmailConnection {
        return EmailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
        ]);
    }

    /** @param list<MailAttachment> $attachments */
    private function message(int $uid, array $attachments = []): ParsedMessage {
        return new ParsedMessage(
            messageId: '<m' . $uid . '@x>',
            uid: $uid,
            fromEmail: 'absender' . $uid . '@extern.test',
            fromName: 'Absender',
            subject: 'Mail ' . $uid,
            body: 'Text',
            receivedAt: Carbon::parse('2026-10-01 09:00:00'),
            attachments: $attachments,
        );
    }

    private function poller(MailboxGateway $gateway): MailboxPoller {
        return new MailboxPoller($gateway, app(MailIntakeService::class));
    }

    public function test_messages_are_processed_in_batches_along_the_cursor(): void {
        $connection = $this->connection();
        $gateway = new FakeMailboxGateway(array_map(fn (int $uid): ParsedMessage => $this->message($uid), range(1, 60)));
        $poller = $this->poller($gateway);

        $first = $poller->poll($this->organization, $connection);
        $this->assertSame(['ok', 25], [$first['status'], $first['processed']]);
        $cursor = MailboxCursor::of($connection->refresh());
        $this->assertSame(25, $cursor->lastUid);
        $this->assertTrue($cursor->catchingUp);

        $poller->poll($this->organization, $connection->refresh());
        $third = $poller->poll($this->organization, $connection->refresh());

        $this->assertSame(10, $third['processed']);
        $cursor = MailboxCursor::of($connection->refresh());
        $this->assertSame(60, $cursor->lastUid);
        $this->assertFalse($cursor->catchingUp);
        $this->assertSame(60, $cursor->processed);
        $this->assertSame(60, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
        $this->assertCount(60, $gateway->processedUids);
        $this->assertNotNull($connection->refresh()->last_polled_at);

        $again = $poller->poll($this->organization, $connection->refresh());
        $this->assertSame(0, $again['processed']);
    }

    public function test_a_running_poll_blocks_a_second_one(): void {
        $connection = $this->connection();
        $lock = Cache::lock('mail-poll:' . $connection->id, 60);
        $lock->get();

        try {
            $result = $this->poller(new FakeMailboxGateway([$this->message(1)]))->poll($this->organization, $connection);
        } finally {
            $lock->release();
        }

        $this->assertSame('locked', $result['status']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    public function test_missing_processed_folder_keeps_processing_and_is_reported(): void {
        $connection = $this->connection(['processed_folder' => 'Erledigt']);
        $gateway = new FakeMailboxGateway([$this->message(1), $this->message(2)]);
        $gateway->missingProcessedFolder = 'Erledigt';

        $result = $this->poller($gateway)->poll($this->organization, $connection);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(2, MailboxCursor::of($connection->refresh())->lastUid);
        $this->assertStringContainsString('Erledigt', (string) $connection->refresh()->last_error);
    }

    public function test_missing_fetch_folder_aborts_and_is_reported(): void {
        $connection = $this->connection(['folder' => 'Rechnugen']);
        $gateway = new class implements MailboxGateway {
            public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator {
                throw new MailboxFolderMissingException((string) $connection->folder);
                yield; // @phpstan-ignore deadCode.unreachable
            }

            public function markProcessed(EmailConnection $connection, ParsedMessage $message): void {}

            public function folders(EmailConnection $connection): array {
                return [];
            }
        };

        $result = $this->poller($gateway)->poll($this->organization, $connection);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Rechnugen', (string) $connection->refresh()->last_error);
        $this->assertNull($connection->refresh()->last_polled_at);
    }

    public function test_changed_folder_starts_a_new_pass(): void {
        $connection = $this->connection();
        $connection->forceFill(['sync_cursor' => MailboxCursor::start($connection)->with(['lastUid' => 50, 'catchingUp' => false])->toArray()])->save();
        $connection->forceFill(['folder' => 'Rechnungen'])->save();
        $gateway = new FakeMailboxGateway([$this->message(3)]);

        $this->poller($gateway)->poll($this->organization, $connection->refresh());

        $this->assertNull($gateway->cursors[0]->lastUid);
        $this->assertSame('Rechnungen', $gateway->cursors[0]->folder);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
    }

    public function test_generic_mime_type_is_taken_from_the_content(): void {
        Storage::fake('local');
        $connection = $this->connection();
        $pdf = new MailAttachment('rechnung.pdf', 'application/octet-stream', "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        $this->poller(new FakeMailboxGateway([$this->message(1, [$pdf])]))->poll($this->organization, $connection);

        $item = IntegrationInboxItem::query()->firstOrFail();
        $stored = (array) (((array) $item->remote_snapshot)['attachments'][0] ?? []);
        $this->assertSame('application/pdf', $stored['mime'] ?? null);
        $this->assertTrue((bool) ($stored['stored'] ?? false));
    }

    public function test_new_connection_starts_today_and_poll_command_uses_the_poller(): void {
        Carbon::setTestNow('2026-10-12 08:00:00');
        $connection = $this->connection();
        $this->assertSame('2026-10-12', $connection->fetch_since?->toDateString());

        $fake = new FakeMailboxGateway([$this->message(42)]);
        $this->app->instance(MailboxGateway::class, $fake);

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('mail:poll', ['--organization' => (string) $this->organization->id]));
        $this->assertSame([42], $fake->processedUids);
        Carbon::setTestNow();
    }
}
