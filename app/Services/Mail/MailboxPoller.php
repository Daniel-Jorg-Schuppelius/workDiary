<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxPoller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Exceptions\MailboxFolderMissingException;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ein Abruf eines Postfachs (MVP-1139): Sperre je Postfach, Stapel, Abrufstand
 * nach jeder verarbeiteten Nachricht, Verbindungs-Gesundheit. Befehl,
 * Graph-Webhook und „Jetzt abrufen“ laufen nur hierüber, damit dieselbe Mail
 * nie gleichzeitig zweimal verarbeitet wird.
 */
final class MailboxPoller {
    /** Generische Typangaben, bei denen der Inhalt entscheidet (MVP-1142). */
    private const GENERIC_MIMES = ['', 'application/octet-stream', 'application/binary', 'binary/octet-stream'];

    public function __construct(
        private readonly MailboxGateway $gateway,
        private readonly MailIntakeService $intake,
    ) {}

    /** @return array{status: 'ok'|'locked'|'failed', processed: int, created: int, error: ?string} */
    public function poll(Organization $organization, EmailConnection $connection): array {
        $lock = Cache::lock('mail-poll:' . $connection->id, max(60, (int) config('mail_intake.poll.lock_seconds', 600)));
        if (! $lock->get()) {
            return ['status' => 'locked', 'processed' => 0, 'created' => 0, 'error' => null];
        }

        $processed = 0;
        $created = 0;
        try {
            $start = MailboxCursor::of($connection);
            $messages = $this->gateway->fetch($connection, $start, max(1, (int) config('mail_intake.poll.batch', 25)));
            $warnings = [];
            foreach ($messages as $message) {
                $message = $this->withDetectedMimeTypes($message);
                if ($this->intake->intake($organization, $connection, $message) === 'created') {
                    $created++;
                }
                $processed++;
                try {
                    $this->gateway->markProcessed($connection, $message);
                } catch (MailboxFolderMissingException $e) {
                    $warnings[$e->folder] = $e->getMessage();
                }
                if ($message->cursorAfter !== null) {
                    $this->store($connection, $message->cursorAfter, $start, $processed);
                }
            }
            $this->store($connection, $messages->getReturn(), $start, $processed, true);

            if ($warnings !== []) {
                $connection->recordConnectionFailure(implode(' ', $warnings));

                return ['status' => 'failed', 'processed' => $processed, 'created' => $created, 'error' => implode(' ', $warnings)];
            }
            $connection->recordConnectionSuccess();

            return ['status' => 'ok', 'processed' => $processed, 'created' => $created, 'error' => null];
        } catch (Throwable $e) {
            $connection->recordConnectionFailure($e->getMessage());

            return ['status' => 'failed', 'processed' => $processed, 'created' => $created, 'error' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /** Stand ohne Modell-Ereignisse schreiben: Betriebszustand, kein Audit je Mail. */
    private function store(EmailConnection $connection, MailboxCursor $cursor, MailboxCursor $start, int $processed, bool $finished = false): void {
        $attributes = ['sync_cursor' => $cursor->with(['processed' => $start->processed + $processed])->toArray()];
        if ($finished) {
            $attributes['last_polled_at'] = Carbon::now();
        }
        $connection->forceFill($attributes)->saveQuietly();
    }

    /**
     * Manche Absender geben PDFs als `application/octet-stream` an; Ablage und
     * Rechnungsprüfung sehen dann den Typ aus dem Inhalt.
     */
    private function withDetectedMimeTypes(ParsedMessage $message): ParsedMessage {
        $changed = false;
        $attachments = [];
        foreach ($message->attachments as $attachment) {
            if (in_array(mb_strtolower(trim($attachment->mime)), self::GENERIC_MIMES, true)) {
                $detected = File::mimeTypeFromContent($attachment->content);
                if (is_string($detected) && $detected !== '' && ! in_array($detected, self::GENERIC_MIMES, true)) {
                    $attachment = new MailAttachment($attachment->filename, $detected, $attachment->content, $attachment->isInline);
                    $changed = true;
                }
            }
            $attachments[] = $attachment;
        }

        return $changed ? $message->withAttachments($attachments) : $message;
    }
}
