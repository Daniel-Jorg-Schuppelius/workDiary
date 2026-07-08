<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachmentStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persistiert E-Mail-Anhänge beim Intake temporär (Feature 056, MVP-117 →
 * Rang 7) und setzt dabei die Größen-/MIME-Policy aus `config/mail_intake.php`
 * durch. Gespeichert wird auf der Disk `local` unter `mail-intake/{org}/…`; die
 * Metadaten (auch abgelehnter Anhänge, mit Grund) wandern in den
 * `remote_snapshot` des Inbox-Eintrags.
 *
 * **Nie Auto-Import:** Persistiert wird nur für die spätere menschliche
 * Auflösung — die eigentliche Übernahme (an die Kommunikationsnotiz oder ins
 * DMS) erfolgt erst dort ({@see MailInboxResolutionService}).
 */
class MailAttachmentStore {
    public const DISK = 'local';

    /**
     * @return list<array<string, mixed>>  Metadaten je Anhang (`stored` = true|false)
     */
    public function persistFromMessage(Organization $organization, ParsedMessage $message): array {
        $out = [];
        foreach ($message->attachments as $index => $attachment) {
            $out[] = $this->persistOne($organization, $index, $attachment);
        }

        return $out;
    }

    /**
     * null = akzeptiert; sonst Ablehnungsgrund (`size` | `mime`).
     */
    public function rejectionReason(MailAttachment $attachment): ?string {
        $maxBytes = (int) config('mail_intake.attachments.max_bytes', 25 * 1024 * 1024);
        if ($attachment->size() > $maxBytes) {
            return 'size';
        }

        /** @var list<string> $allowed */
        $allowed = (array) config('mail_intake.attachments.allowed_mimes', []);
        $allowed = array_map('strtolower', $allowed);
        if (! in_array(strtolower(trim($attachment->mime)), $allowed, true)) {
            return 'mime';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function persistOne(Organization $organization, int $index, MailAttachment $attachment): array {
        $meta = [
            'index' => $index,
            'original_name' => $attachment->filename,
            'mime' => $attachment->mime,
            'size' => $attachment->size(),
        ];

        $reason = $this->rejectionReason($attachment);
        if ($reason !== null) {
            return $meta + ['stored' => false, 'rejected_reason' => $reason];
        }

        $ext = strtolower(pathinfo($attachment->filename, PATHINFO_EXTENSION));
        $path = 'mail-intake/' . $organization->id . '/' . Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
        Storage::disk(self::DISK)->put($path, $attachment->content);

        return $meta + ['stored' => true, 'disk' => self::DISK, 'stored_path' => $path];
    }
}
