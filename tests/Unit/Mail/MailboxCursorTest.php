<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxCursorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Models\Mail\EmailConnection;
use App\Services\Mail\MailboxCursor;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Abrufstand (MVP-1139): gilt nur für Ordner und Postfachadresse, für die er
 * entstand; ein verlorener Stand beginnt am letzten Abruf minus einem Tag,
 * nie vor dem Startpunkt.
 */
final class MailboxCursorTest extends TestCase {
    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): EmailConnection {
        $connection = new EmailConnection();
        $connection->setRawAttributes($attributes + ['folder' => 'INBOX', 'mailbox_address' => null, 'fetch_since' => '2026-01-01', 'last_polled_at' => null]);

        return $connection;
    }

    public function test_round_trip_keeps_all_fields(): void {
        $cursor = new MailboxCursor('INBOX', 'rechnungen@firma.de', 7, 42, 'f1', 'https://graph/link', '2026-05-01', true, true, 12);

        $this->assertEquals($cursor, MailboxCursor::fromArray($cursor->toArray()));
    }

    public function test_cursor_belongs_only_to_its_folder_and_mailbox(): void {
        $connection = $this->connection(['mailbox_address' => 'Rechnungen@Firma.de']);
        $cursor = MailboxCursor::start($connection)->with(['lastUid' => 5]);
        $connection->setAttribute('sync_cursor', $cursor->toArray());

        $this->assertSame(5, MailboxCursor::of($connection)->lastUid);

        $connection->setAttribute('folder', 'Rechnungen');
        $this->assertNull(MailboxCursor::of($connection)->lastUid);
        $this->assertTrue(MailboxCursor::of($connection)->catchingUp);
    }

    public function test_with_clears_fields_given_as_null_and_keeps_the_rest(): void {
        $cursor = new MailboxCursor('INBOX', null, 7, 42, 'f1', 'link');

        $changed = $cursor->with(['link' => null]);

        $this->assertNull($changed->link);
        $this->assertSame(42, $changed->lastUid);
        $this->assertSame('f1', $changed->folderId);
    }

    public function test_restart_resumes_one_day_before_the_last_poll_but_never_before_the_start(): void {
        $connection = $this->connection(['last_polled_at' => '2026-09-10 12:00:00']);
        $restarted = (new MailboxCursor('INBOX', null, 7, 42, null, 'link'))->restarted($connection);

        $this->assertNull($restarted->lastUid);
        $this->assertNull($restarted->link);
        $this->assertTrue($restarted->catchingUp);
        $this->assertSame('2026-09-09', $restarted->since($connection)?->toDateString());

        $late = $this->connection(['fetch_since' => '2026-09-20', 'last_polled_at' => '2026-09-10 12:00:00']);
        $this->assertSame('2026-09-20', $restarted->since($late)?->toDateString());
    }

    public function test_since_without_resume_is_the_start_date(): void {
        $this->assertSame('2026-01-01', MailboxCursor::start($this->connection())->since($this->connection())?->toDateString());
        $this->assertInstanceOf(Carbon::class, MailboxCursor::start($this->connection())->since($this->connection()));
    }
}
