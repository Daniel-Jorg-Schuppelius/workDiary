<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Demo;

use App\Models\Attachments\Attachment;
use App\Models\Platform\Organization;
use App\Services\Attachments\FileAttacher;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Facades\Storage;

/**
 * Abgelegte Mail zur Vorführung (Feature 204): eine erfundene Rückfrage als
 * `.eml` am Hauptauftrag der Demo — mit Text, HTML-Teil und kleinem
 * Textanhang, damit Ansicht „Mail“, Originaldarstellung und Übernahme
 * sofort etwas zeigen. Absender und Empfänger liegen unter example.org.
 */
final class MailFileDemoBlock implements DemoBlock {
    /** Name der Demo-Datei. */
    public const string FILE_NAME = 'Demo-Rueckfrage.eml';

    /**
     * Ablageordner nur für diese Demo-Datei — das Kennzeichen fürs Aufräumen.
     * Uploads landen nie hier (attachments/Y/m), ein gleichnamiger echter
     * Anhang bleibt also unberührt.
     */
    public const string FOLDER = 'demo/mail-files';

    public function __construct(private readonly FileAttacher $attacher) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->mainDiary !== null;
    }

    public function seed(DemoSeedContext $context): array {
        $diary = $context->mainDiary;
        if ($diary === null) {
            return [];
        }

        $this->attacher->storeContent($diary, self::mail(), self::FILE_NAME, 'message/rfc822', (int) $context->actor->id, folder: self::FOLDER);

        return ['mail_files' => 1];
    }

    public function purge(Organization $organization): void {
        Attachment::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereLikeEscaped('path', self::FOLDER . '/', 'prefix')
            ->get()
            ->each(static function (Attachment $attachment): void {
                Storage::disk((string) $attachment->disk)->delete((string) $attachment->path);
                $attachment->delete();
            });
    }

    /** Erfundene Mail in der Sprache der Oberfläche (Kopfzeilen RFC 2047, Körper quoted-printable). */
    private static function mail(): string {
        $subject = (string) __('mail_file.demo.subject');
        $body = (string) __('mail_file.demo.body');
        $html = '<p>' . nl2br(e($body), false) . '</p>';
        $note = (string) __('mail_file.demo.attachment');

        return implode("\r\n", [
            'From: ' . mb_encode_mimeheader((string) __('mail_file.demo.sender'), 'UTF-8', 'Q') . ' <kundin@example.org>',
            'To: service@example.org',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'Q'),
            'Date: ' . now()->subDay()->toRfc2822String(),
            'Message-ID: <demo-rueckfrage@example.org>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="demo-mix"',
            '',
            '--demo-mix',
            'Content-Type: multipart/alternative; boundary="demo-alt"',
            '',
            '--demo-alt',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($body),
            '--demo-alt',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($html),
            '--demo-alt--',
            '--demo-mix',
            'Content-Type: text/plain; charset=UTF-8; name="notiz.txt"',
            'Content-Disposition: attachment; filename="notiz.txt"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($note),
            '--demo-mix--',
            '',
        ]);
    }
}
