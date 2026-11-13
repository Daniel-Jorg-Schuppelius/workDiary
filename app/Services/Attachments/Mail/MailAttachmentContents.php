<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachmentContents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Mail;

use App\Enums\Attachments\MailFileFailure;
use App\Exceptions\MailFileUnreadableException;
use App\Jobs\Attachments\ReadMailAttachmentJob;
use App\Models\Attachments\{Attachment, AttachmentText};
use App\Models\Platform\Organization;
use App\Services\Attachments\Mail\Dto\MailFileMessage;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gelesene Inhalte eines Mail-Anhangs (Feature 204): die Mail wird genau
 * einmal im Hintergrund gelesen ({@see ReadMailAttachmentJob}); dabei entstehen
 * Suchtext, Zusammenfassung (nur Arrays und Skalare) und ein Verzeichnis mit
 * den Anhängen und der bereinigten Originaldarstellung. Ansicht, Download und
 * Übernahme lesen nur noch diese Ergebnisse — keine Anfrage zerlegt die Mail.
 *
 * Zustand in {@see AttachmentText}: die Zeile entsteht beim Anstoßen
 * („wird gelesen“) und verhindert jedes zweite Anstoßen; ein Abbruch
 * (Zeitlimit, Absturz) oder eine zu lange offene Lesung endet als
 * {@see MailFileFailure::Aborted} — nie in einer Schleife.
 */
final class MailAttachmentContents {
    /** Eine Lesung, die so lange offen ist, gilt als abgebrochen. */
    public const int PENDING_MINUTES = 15;

    /** Ablage der extrahierten Teile (Verzeichnis je Anhang, Zeiger in attachment_texts.parts_path). */
    private const string PARTS_ROOT = 'mail-parts';

    public function __construct(private readonly MailFileReader $reader) {}

    /**
     * Lesung anstoßen, falls noch nie geschehen: Zeile „wird gelesen“ anlegen
     * und den Job einreihen. Gibt es die Zeile schon, passiert nichts.
     */
    public function queue(Attachment $attachment): AttachmentText {
        $existing = $this->textOf($attachment);
        if ($existing !== null) {
            return $existing;
        }

        $text = AttachmentText::query()->withoutGlobalScopes()->createOrFirst(
            ['attachment_id' => (int) $attachment->id],
            ['organization_id' => $attachment->organization_id],
        );
        if ($text->wasRecentlyCreated) {
            ReadMailAttachmentJob::dispatch((int) $attachment->id)->afterCommit();
        }

        return $text;
    }

    /**
     * Zustand für die Ansicht; stößt eine nie begonnene Lesung an (etwa für
     * Mail-Anhänge aus dem Mail-Eingang vor diesem Feature).
     *
     * @return array{state: 'pending'|'ready'|'failed', message: ?MailFileMessage, failure: ?MailFileFailure}
     */
    public function state(Attachment $attachment): array {
        $text = $this->queue($attachment);
        if ($text->isPending() && $text->created_at !== null && $text->created_at->lt(now()->subMinutes(self::PENDING_MINUTES))) {
            $this->markFailed($text, MailFileFailure::Aborted);
        }

        if ($text->failure_reason !== null) {
            return ['state' => 'failed', 'message' => null, 'failure' => $text->failure_reason];
        }
        if ($text->isPending()) {
            return ['state' => 'pending', 'message' => null, 'failure' => null];
        }
        $message = $text->message();

        return $message !== null
            ? ['state' => 'ready', 'message' => $message, 'failure' => null]
            : ['state' => 'failed', 'message' => null, 'failure' => MailFileFailure::Malformed];
    }

    /** Gelesene Zusammenfassung oder null (wird gelesen, nicht lesbar). */
    public function message(Attachment $attachment): ?MailFileMessage {
        return $this->textOf($attachment)?->message();
    }

    /**
     * Die eine Lesung (im Job): in der Organisation des Anhangs (ihre
     * Anhangsgrenze gilt), Ergebnisse ablegen, Fehlschlag festhalten.
     */
    public function extract(Attachment $attachment): void {
        $text = $this->textOf($attachment) ?? AttachmentText::query()->withoutGlobalScopes()->createOrFirst(
            ['attachment_id' => (int) $attachment->id],
            ['organization_id' => $attachment->organization_id],
        );
        if (! $text->isPending()) {
            return;
        }

        $organization = $attachment->organization_id !== null
            ? Organization::query()->withoutGlobalScopes()->find($attachment->organization_id)
            : null;
        $read = fn (): MailFileMessage => $this->reader->readAttachment($attachment);

        try {
            $message = $organization instanceof Organization ? OrganizationContext::run($organization, $read) : $read();
        } catch (MailFileUnreadableException $e) {
            $this->markFailed($text, $e->reason);

            return;
        }

        $diskName = (string) $attachment->disk;
        $disk = Storage::disk($diskName);
        $directory = self::PARTS_ROOT . '/' . now()->format('Y/m') . '/' . Str::uuid()->toString();
        try {
            $summary = $this->storeMessage($disk, $directory, $message, '');
        } catch (Throwable $e) {
            $disk->deleteDirectory($directory);
            $this->markFailed($text, MailFileFailure::Malformed);
            report($e);

            return;
        }

        $text->forceFill([
            'text' => $this->reader->searchText($message) ?: null,
            'summary' => $summary,
            'parts_disk' => $diskName,
            'parts_path' => $directory,
            'extracted_at' => now(),
            'failure_reason' => null,
        ])->save();
    }

    /** Abbruch des Jobs (Zeitlimit, Absturz): offene Lesung als abgebrochen festhalten. */
    public function markAborted(int $attachmentId): void {
        $text = AttachmentText::query()->withoutGlobalScopes()->where('attachment_id', $attachmentId)->first();
        if ($text !== null && $text->isPending()) {
            $this->markFailed($text, MailFileFailure::Aborted);
        }
    }

    /** Inhalt eines extrahierten Datei-Anhangs (Pfad wie „1“, „2.0“) oder null. */
    public function partContent(Attachment $attachment, string $path): ?string {
        if (preg_match(MailFileMessage::PART_PATH_PATTERN, $path) !== 1) {
            return null;
        }

        return $this->readStored($attachment, 'part-' . $path . '.bin');
    }

    /** Bereinigte Originaldarstellung der Mail (leerer Pfad) bzw. einer eingebetteten Mail oder null. */
    public function frame(Attachment $attachment, string $path): ?string {
        if ($path !== '' && preg_match(MailFileMessage::PART_PATH_PATTERN, $path) !== 1) {
            return null;
        }

        return $this->readStored($attachment, 'html-' . ($path === '' ? 'mail' : $path) . '.html');
    }

    /** Extrahierte Teile entfernen (Anhang wird gelöscht; die Zeile fällt per Fremdschlüssel). */
    public function forget(Attachment $attachment): void {
        $text = $this->textOf($attachment);
        if ($text !== null && $text->parts_path !== null && $text->parts_path !== '') {
            Storage::disk((string) ($text->parts_disk ?? 'local'))->deleteDirectory($text->parts_path);
        }
    }

    private function textOf(Attachment $attachment): ?AttachmentText {
        return AttachmentText::query()->withoutGlobalScopes()->where('attachment_id', (int) $attachment->id)->first();
    }

    private function markFailed(AttachmentText $text, MailFileFailure $reason): void {
        $text->forceFill(['failure_reason' => $reason, 'extracted_at' => now(), 'summary' => null, 'text' => null])->save();
    }

    private function readStored(Attachment $attachment, string $file): ?string {
        $text = $this->textOf($attachment);
        if ($text === null || $text->failure_reason !== null || $text->parts_path === null || $text->parts_path === '') {
            return null;
        }
        $disk = Storage::disk((string) ($text->parts_disk ?? 'local'));
        $path = $text->parts_path . '/' . $file;

        return $disk->exists($path) ? (string) $disk->get($path) : null;
    }

    /**
     * Teile und Originaldarstellung einer Mail ablegen; liefert die
     * Zusammenfassung (Arrays und Skalare) mit der Verfügbarkeit des Rahmens.
     *
     * @return array<string, mixed>
     */
    private function storeMessage(Filesystem $disk, string $directory, MailFileMessage $message, string $path): array {
        $frame = $message->hasHtml ? $this->reader->frameHtml($message) : null;
        if ($frame !== null) {
            $disk->put($directory . '/html-' . ($path === '' ? 'mail' : $path) . '.html', $frame);
        }

        $summary = $message->withFrame($frame !== null)->toArray();
        foreach ($message->attachments as $index => $part) {
            $partPath = $path === '' ? (string) $index : $path . '.' . $index;
            if ($part->message !== null) {
                $summary['attachments'][$index]['message'] = $this->storeMessage($disk, $directory, $part->message, $partPath);
            } elseif ($part->content !== '') {
                $disk->put($directory . '/part-' . $partPath . '.bin', $part->content);
            }
        }

        return $summary;
    }
}
