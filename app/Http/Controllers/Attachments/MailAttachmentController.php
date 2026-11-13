<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Controller;
use App\Models\Attachments\Attachment;
use App\Models\Contracts\GuardsAttachments;
use App\Services\Attachments\FileAttacher;
use App\Services\Attachments\Mail\Dto\{MailFileMessage, MailFilePart};
use App\Services\Attachments\Mail\MailAttachmentContents;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Gate, URL};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Ansicht „Mail“ für abgelegte `.eml`/`.msg`-Dateien (Feature 204, MVP-1653).
 *
 * Die Mail ist ein Anhang und erbt die Sichtbarkeit ihres Trägers: jeder
 * Endpunkt prüft die AttachmentPolicy (Organisation + Policy des Trägers), der
 * Anhang-Pfad adressiert nur innerhalb dieser einen Datei. Keine Anfrage
 * zerlegt die Mail: Zusammenfassung, Anhänge und bereinigte
 * Originaldarstellung stammen aus der einen Lesung im Hintergrund
 * ({@see MailAttachmentContents}). Original-HTML und Anhänge laufen über
 * signierte, kurzlebige Links wie der Download.
 *
 * - HTML nur auf Wunsch und nur im `iframe sandbox` (ohne allow-*) mit eigener
 *   CSP {@see FRAME_CSP}; bereinigt über den HTML5-Parser.
 * - Anhänge: PDF und Bilder (am Inhalt erkannt) inline in der Vorschau mit der
 *   Dokument-CSP der SecurityHeaders, alles andere als Download mit
 *   `Content-Disposition: attachment` und {@see DOWNLOAD_CSP}; Typen außerhalb
 *   der Positivliste nur als `.bin`.
 */
class MailAttachmentController extends Controller {
    /** CSP der HTML-Ansicht; `sandbox` gilt auch, wenn jemand den Link direkt öffnet. */
    public const string FRAME_CSP = "sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'";

    /** CSP eines heruntergeladenen Anhangs, falls ein Browser ihn doch darstellt. */
    public const string DOWNLOAD_CSP = "sandbox; default-src 'none'";

    /** Laufzeit der signierten Links (wie der Anhang-Download). */
    private const int LINK_MINUTES = 15;

    public function __construct(private readonly MailAttachmentContents $contents) {}

    public function show(Request $request, Attachment $attachment): View {
        Gate::authorize('view', $attachment);
        $format = $attachment->mailFormat();
        abort_if($format === null, 404);

        $state = $this->contents->state($attachment);
        $message = $state['message'];

        // Vorschau eines Anhangs (PDF, Bild) und HTML-Rahmen nur auf Wunsch:
        // `vorschau=<Pfad>`, `html=mail` (die Mail) bzw. `html=<Pfad>` (eingebettete Mail).
        $previewPath = $request->query('vorschau');
        $preview = $message !== null && is_string($previewPath) && $previewPath !== '' ? $message->part($previewPath) : null;
        $htmlParam = $request->query('html');
        $htmlPath = is_string($htmlParam) && $htmlParam !== '' ? ($htmlParam === 'mail' ? '' : $htmlParam) : null;
        $htmlMessage = $message !== null && $htmlPath !== null ? $message->messageAt($htmlPath) : null;

        $carrier = $attachment->attachable;

        return view('attachments.mail.show', [
            'attachment' => $attachment,
            'format' => $format,
            'state' => $state['state'],
            'message' => $message,
            'failure' => $state['failure'],
            'carrier' => $carrier,
            'canAdopt' => $message !== null && $carrier instanceof Model && $this->mayAdopt($carrier),
            'preview' => $preview !== null && $preview->isPreviewable() ? ['path' => (string) $previewPath, 'part' => $preview] : null,
            'htmlFrame' => $htmlMessage !== null && $htmlMessage->frameAvailable ? ['path' => (string) $htmlPath, 'message' => $htmlMessage] : null,
            'downloadUrl' => AttachmentController::downloadUrl($attachment),
        ]);
    }

    /** Bereinigte Originaldarstellung einer Mail (oder einer eingebetteten über `m`) für den abgeschotteten Rahmen. */
    public function html(Request $request, Attachment $attachment): Response {
        abort_unless($request->hasValidSignature(), 403);
        Gate::authorize('view', $attachment);
        abort_if($attachment->mailFormat() === null, 404);

        $path = $request->query('m');
        $frame = $this->contents->frame($attachment, is_string($path) ? $path : '');
        abort_if($frame === null, 404);

        return response($frame, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => self::FRAME_CSP,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-DNS-Prefetch-Control' => 'off',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Anhang der Mail: PDF/Bild mit `inline=1` in der Vorschau, sonst als Download. */
    public function part(Request $request, Attachment $attachment, string $part): Response {
        abort_unless($request->hasValidSignature(), 403);
        Gate::authorize('view', $attachment);
        [$item, $content] = $this->partOf($attachment, $part);

        $inline = $request->boolean('inline') && $item->isPreviewable();
        $name = $inline ? $item->name : $item->downloadName();
        $headers = [
            'Content-Type' => $inline ? $item->mime : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $name, self::asciiFallback($name)),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ];
        if (! $inline) {
            // PDF und Bilder bekommen die Dokument-CSP der SecurityHeaders (eingebauter PDF-Viewer).
            $headers['Content-Security-Policy'] = self::DOWNLOAD_CSP;
        }

        return response($content, 200, $headers);
    }

    /**
     * Anhang der Mail als eigene Datei am selben Träger übernehmen — nur an
     * Trägern des Upload-Endpunkts, mit denselben Rechten (Bearbeiten am
     * Träger), derselben Größen- und Typprüfung am Inhalt wie ein Upload und
     * bereinigtem Dateinamen.
     */
    public function adopt(Attachment $attachment, string $part): RedirectResponse {
        Gate::authorize('view', $attachment);
        $carrier = $attachment->attachable;
        abort_unless($carrier instanceof Model && in_array($carrier::class, AttachmentController::TYPE_MAP, true), 404);
        abort_unless($this->mayAdopt($carrier), 403);
        [$item, $content] = $this->partOf($attachment, $part);

        $back = redirect()->route('attachments.mail.show', $attachment);
        $maxKb = FileAttacher::maxKbFor($carrier);
        if (strlen($content) > $maxKb * 1024) {
            return $back->withErrors(['file' => (string) __('mail_file.error.adopt_too_large', ['name' => $item->name, 'size' => NumberHelper::formatBytes($maxKb * 1024, 0)])]);
        }
        $problem = FileAttacher::contentRejection($content, $item->name)
            ?? FileAttacher::carrierRejection($carrier, $item->mime);
        if ($problem !== null) {
            return $back->withErrors(['file' => $item->name . ': ' . $problem]);
        }

        $user = $this->authUser();
        $copy = app(FileAttacher::class)->storeContent($carrier, $content, $item->name, $item->mime, (int) $user->id);
        if ($carrier instanceof GuardsAttachments) {
            $carrier->attachmentAdded($copy, $user);
        }

        return $back->with('success', (string) __('mail_file.flash.adopted', ['name' => $copy->original_name]));
    }

    /** Signierter Link auf die Originaldarstellung (`$path` = eingebettete Mail, leer = die Mail selbst). */
    public static function htmlUrl(Attachment $attachment, string $path = ''): string {
        return URL::temporarySignedRoute('attachments.mail.html', now()->addMinutes(self::LINK_MINUTES), array_filter(['attachment' => $attachment, 'm' => $path], static fn (mixed $v): bool => $v !== ''));
    }

    /** Signierter Link auf einen Anhang der Mail; `$inline` für die Vorschau. */
    public static function partUrl(Attachment $attachment, string $path, bool $inline = false): string {
        return URL::temporarySignedRoute('attachments.mail.part', now()->addMinutes(self::LINK_MINUTES), array_filter(['attachment' => $attachment, 'part' => $path, 'inline' => $inline ? 1 : null], static fn (mixed $v): bool => $v !== null));
    }

    /** Wie ein Upload: Anhänge anlegen, den Träger bearbeiten, und der Träger nimmt noch etwas an. */
    private function mayAdopt(Model $carrier): bool {
        return in_array($carrier::class, AttachmentController::TYPE_MAP, true)
            && Gate::allows('create', Attachment::class)
            && Gate::allows('update', $carrier)
            && ! ($carrier instanceof GuardsAttachments && $carrier->attachmentsLocked());
    }

    /**
     * Datei-Anhang über seinen Pfad aus der gelesenen Mail samt Inhalt;
     * eingebettete Mails, unbekannte und ungültige Pfade, ungelesene Mails → 404.
     *
     * @return array{0: MailFilePart, 1: string}
     */
    private function partOf(Attachment $attachment, string $path): array {
        abort_if($attachment->mailFormat() === null || preg_match(MailFileMessage::PART_PATH_PATTERN, $path) !== 1, 404);
        $item = $this->contents->message($attachment)?->part($path);
        abort_if($item === null || $item->isMessage(), 404);
        $content = $this->contents->partContent($attachment, $path);
        abort_if($content === null, 404);

        return [$item, $content];
    }

    /** ASCII-Ersatzname für `filename=` (der volle Name steht in `filename*`). */
    private static function asciiFallback(string $name): string {
        $ascii = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($name));

        return trim($ascii, ' .') !== '' ? $ascii : 'anhang';
    }
}
