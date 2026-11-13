<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileAttacher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Enums\Attachments\{MailFileFailure, MailFileFormat, UploadPurpose};
use App\Exceptions\MailFileUnreadableException;
use App\Models\Attachments\Attachment;
use App\Services\Attachments\Mail\MailFileReader;
use App\Support\Setting;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;

/**
 * Speichert hochgeladene Dateien als polymorphe {@see Attachment} an einem
 * HasAttachments-Träger. Kapselt die einheitlichen Upload-Regeln (Größe,
 * erlaubte Typen) und das Ablegen auf der `local`-Disk, damit Formulare mit
 * eigenem Datei-Feld (z. B. der Wissensartikel-Dialog) Anhänge im selben
 * Request anlegen können — ohne den generischen AttachmentController-Endpoint.
 */
final class FileAttacher {
    /**
     * Positivliste der Datei-Uploads — die eine Stelle für Anhänge, Dokumente,
     * Portal- und Helpdesk-Formulare (Konsolidierungs-Audit 2026-10, k3-7).
     *
     * @var list<string>
     */
    public const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    /**
     * Am Inhalt erkannte MIME-Typen (PHP Fileinfo, nicht der Client-Header).
     *
     * @var list<string>
     */
    public const array ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Mail-Dateien (Feature 204, MVP-1653): nur intern ({@see UploadPurpose::Internal}),
     * und nur wenn Endung und am Inhalt erkannter Typ zusammenpassen
     * ({@see MailFileFormat::detectedMimes()}) und die billige, lineare
     * Vorprüfung des Lesers besteht. Gelesen wird erst im Hintergrund. Bewusst
     * nicht in {@see ALLOWED_EXTENSIONS}/{@see ALLOWED_MIMES}: Stellen, die
     * Endung und Typ getrennt prüfen, nehmen Mails nicht an, und ein Compound
     * File (auch .doc/.xls) wird nie über die flache Liste frei.
     *
     * @var list<string>
     */
    public const array MAIL_EXTENSIONS = ['eml', 'msg'];

    /** Endung und der am Inhalt erkannte Typ stehen beide auf der Positivliste des Zwecks. */
    public static function accepts(UploadedFile $file, UploadPurpose $purpose = UploadPurpose::General): bool {
        return self::rejection($file, $purpose) === null;
    }

    /**
     * Wie {@see accepts()}, aber mit Grund: null = angenommen, sonst die
     * Meldung für die Person („Dateityp nicht erlaubt.“ bzw. bei Mail-Dateien
     * der Grund der Vorprüfung). Eine Mail-Endung außerhalb des internen
     * Zwecks scheitert an der Endung, bevor irgendetwas gelesen wird.
     */
    public static function rejection(UploadedFile $file, UploadPurpose $purpose = UploadPurpose::General): ?string {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        if (! in_array($extension, $purpose->extensions(), true)) {
            return (string) __('Dateityp nicht erlaubt.');
        }

        $mail = MailFileFormat::fromExtension($extension);
        if ($mail !== null) {
            $path = $file->getRealPath();
            if ($path === false) {
                return (string) __('Dateityp nicht erlaubt.');
            }
            // Größe vor dem Lesen: die Vorprüfung hält die ganze Datei im Speicher.
            if (File::size($path) > MailFileReader::limits()['bytes']) {
                return MailFileFailure::TooLarge->message();
            }

            return self::mailRejection($mail, File::read($path));
        }

        return in_array($file->getMimeType() ?? '', $purpose->mimes(), true) ? null : (string) __('Dateityp nicht erlaubt.');
    }

    /**
     * Prüfung für Inhalte ohne Upload (Anhang einer Mail als eigene Datei
     * übernehmen, Download-Freigabe eines Mail-Anhangs): dieselbe Positivliste
     * und Typprüfung am Inhalt wie ein interner Upload; die Größe prüft der
     * Aufrufer gegen {@see maxKb()}.
     */
    public static function contentRejection(string $content, string $originalName): ?string {
        $extension = strtolower(File::extension($originalName));
        if ($content === '' || ! in_array($extension, UploadPurpose::Internal->extensions(), true)) {
            return (string) __('Dateityp nicht erlaubt.');
        }

        $mail = MailFileFormat::fromExtension($extension);
        if ($mail !== null) {
            return self::mailRejection($mail, $content);
        }
        $detected = File::mimeTypeFromContent($content);

        return $detected !== false && in_array($detected, self::ALLOWED_MIMES, true) ? null : (string) __('Dateityp nicht erlaubt.');
    }

    /**
     * Anzeigename für Ablage und Auslieferung: ohne Pfad und Steuerzeichen
     * (Toolkit) und ohne Formatzeichen (Unicode Cf: Bidi-Steuerung,
     * Nullbreite) — sonst erschiene „rechnung‮fdp.exe“ als „rechnungexe.pdf“.
     */
    public static function displayName(string $name, string $fallback = 'file'): string {
        $clean = (string) preg_replace('/\p{Cf}+/u', '', File::sanitizeDisplayName($name, 255, $fallback));

        return trim($clean) !== '' ? $clean : $fallback;
    }

    /**
     * Mail-Datei: Typ am Inhalt passend zur Endung, dann die lineare
     * Vorprüfung des Lesers (Größe, MIME-Aufbau bzw. Compound-File-Kennung).
     * Ein .doc/.xls oder ein unspezifisches Compound File mit Endung .msg
     * scheitert am Typ.
     */
    private static function mailRejection(MailFileFormat $format, string $content): ?string {
        $detected = $content !== '' ? File::mimeTypeFromContent($content) : false;
        if ($detected === false || ! in_array(strtolower($detected), $format->detectedMimes(), true)) {
            return (string) __('Dateityp nicht erlaubt.');
        }
        try {
            app(MailFileReader::class)->precheck($content, $format);
        } catch (MailFileUnreadableException $e) {
            return $e->reason->message();
        }

        return null;
    }

    /**
     * Maximale Dateigröße in KB (Laravel `max:`-Einheit) — eine Wahrheit je
     * Zweck, pro Organisation/System übersteuerbar (Settings
     * `uploads.attachment_kb` bzw. `uploads.print_data_kb`, Defaults aus config/uploads.php).
     */
    public static function maxKb(UploadPurpose $purpose = UploadPurpose::General): int {
        return match ($purpose) {
            UploadPurpose::General, UploadPurpose::Internal => (int) Setting::get('uploads.attachment_kb', 25600),
            UploadPurpose::PrintData => (int) Setting::get('uploads.print_data_kb', 262144),
        };
    }

    /**
     * Größengrenze je Träger in KB: Belege an Ausgaben haben ein eigenes,
     * konfigurierbares Limit (W2.3, config/expenses.php) — für Upload und
     * Übernahme aus einer Mail-Datei gleich.
     */
    public static function maxKbFor(Model $parent): int {
        return $parent instanceof \App\Models\Travel\Expense
            ? min(self::maxKb(), max(1, (int) config('expenses.max_upload_mb', 10)) * 1024)
            : self::maxKb();
    }

    /** Eigene Typliste des Trägers (Belege an Ausgaben): passt der Typ nicht, die Meldung; sonst null. */
    public static function carrierRejection(Model $parent, string $mime): ?string {
        if (! $parent instanceof \App\Models\Travel\Expense) {
            return null;
        }
        $mimes = (array) config('expenses.allowed_mime_types', []);

        return $mimes !== [] && ! in_array($mime, $mimes, true) ? (string) __('Dateityp nicht erlaubt.') : null;
    }

    /** Wie {@see maxKb()}, gerundet auf ganze MB (für UI-Hinweise/Meldungen). */
    public static function maxMb(UploadPurpose $purpose = UploadPurpose::General): int {
        return max(1, (int) round(self::maxKb($purpose) / 1024));
    }

    /**
     * Wirksame Grenze je Datei in KB: die App-Grenze, gedeckelt durch PHP
     * (`upload_max_filesize`/`post_max_size`) — sonst verspräche das Formular
     * mehr, als der Server annimmt.
     */
    public static function effectiveMaxKb(UploadPurpose $purpose = UploadPurpose::General): int {
        return max(1, min(self::maxKb($purpose), (int) floor(UploadedFile::getMaxFilesize() / 1024)));
    }

    /** Wirksame Gesamtgröße je Einreichung in KB (Zweckgrenze, gedeckelt durch `post_max_size`). */
    public static function effectiveTotalKb(UploadPurpose $purpose = UploadPurpose::General): int {
        $post = ini_parse_quantity((string) ini_get('post_max_size'));
        $total = $purpose->maxTotalKb();

        return max(1, $post > 0 ? min($total, intdiv($post, 1024)) : $total);
    }

    /**
     * Laravel-Validierungsregel für ein einzelnes hochgeladenes Datei-Feld
     * (Typ + Größe), abgeleitet aus den erlaubten Endungen.
     *
     * @return list<string>
     */
    public static function rule(): array {
        return ['file', 'max:' . self::maxKb(), 'mimes:' . implode(',', self::ALLOWED_EXTENSIONS)];
    }

    /**
     * Legt die Datei als Anhang am Träger an und liefert das Attachment.
     *
     * @param  array<string, mixed>  $extra  Zusatzspalten (z. B. organization_id,
     *                                       customer_visible) — Vollaudit 2026-07, M46.
     * @param  string|null  $folder  Ablageordner-Präfix (Default attachments/Y/m;
     *                               z. B. 'protocol-photos' für Protokoll-Fotos).
     */
    public function store(Model $parent, UploadedFile $file, ?int $userId, array $extra = [], ?string $folder = null, UploadPurpose $purpose = UploadPurpose::General): Attachment {
        $this->guardQuota($parent, (int) $file->getSize());

        // Endung aus dem SERVER-seitig erkannten Typ, nicht aus dem
        // Client-Namen: eine hochgeladene „rechnung.pdf.php" landete sonst als
        // .php in der Ablage (Sicherheitsaudit 2026-09-17, files-upload-1).
        $ext = strtolower($file->extension() ?: 'bin');
        if (! in_array($ext, $purpose->extensions(), true)) {
            $ext = 'bin';
        }
        $mime = $file->getMimeType() ?? '';
        // Mail-Datei (Feature 204): Ablage und Typ in der einheitlichen Form,
        // aber nur, wenn der Inhalt zur Endung passt (accepts() hat sie gelesen).
        $mail = $purpose === UploadPurpose::Internal
            ? MailFileFormat::fromExtension(strtolower($file->getClientOriginalExtension()))
            : null;
        $realPath = $file->getRealPath();
        $detected = $mail !== null && $realPath !== false ? File::mimeType($realPath) : false;
        if ($mail !== null && $detected !== false && in_array(strtolower($detected), $mail->detectedMimes(), true)) {
            $ext = $mail->value;
            $mime = $mail->canonicalMime();
        }
        $path = $file->storeAs(($folder ?? 'attachments') . '/' . now()->format('Y/m'), Str::uuid()->toString() . '.' . $ext, 'local');

        // Über die generische morphMany-Relation (statt der HasAttachments-
        // Trait-Methode), damit der Service jeden Model-Träger akzeptiert; die
        // Morph-Definition entspricht exakt HasAttachments::attachments().
        /** @var Attachment $attachment */
        $attachment = $parent->morphMany(Attachment::class, 'attachable')->create(array_merge([
            'user_id' => $userId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => self::displayName($file->getClientOriginalName()),
            'mime' => $mime,
            'size' => $file->getSize(),
        ], $extra));

        return $attachment;
    }

    /**
     * Content-Variante (Vollaudit 2026-07, M46): legt bereits vorliegende
     * Roh-Inhalte (z. B. Mail-Intake-Übernahmen) mit demselben Ablage-Rezept
     * ab — gleicher Ordner, UUID-Name, File::sanitizeDisplayName, morphMany-create.
     *
     * @param  string|null  $mime  Angabe des Absenders — nur Hinweis, wenn am Inhalt nichts erkennbar ist
     * @param  array<string, mixed>  $extra
     */
    public function storeContent(Model $parent, string $content, string $originalName, ?string $mime, ?int $userId, array $extra = [], ?string $folder = null): Attachment {
        // Wie store(): auch Inhalte aus Mails und Kopien zählen gegen das Speicherkontingent (Sicherheitsaudit 2026-10-21, pub-2).
        $this->guardQuota($parent, strlen($content));

        // Wie store(): Endung aus dem am INHALT erkannten Typ, nie aus dem Namen —
        // eine Mail-Anlage „rechnung.pdf.php" landete sonst als .php in der Ablage.
        // Leerer Inhalt ist hier legitim; das Toolkit protokollierte ihn als Fehler.
        $detected = $content !== '' ? File::mimeTypeFromContent($content) : false;
        $ext = ($detected !== false ? File::extensionForMimeType($detected) : null) ?? 'bin';
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $ext = 'bin';
        }

        $path = ($folder ?? 'attachments') . '/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.' . $ext;
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $content);

        /** @var Attachment $attachment */
        $attachment = $parent->morphMany(Attachment::class, 'attachable')->create(array_merge([
            'user_id' => $userId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => self::displayName($originalName),
            // Wie store() (getMimeType am Inhalt): der Absender bestimmt weder
            // Endung noch Typ — sonst stünde neben „.bin" ein behauptetes video/*.
            'mime' => $detected !== false ? $detected : ($mime ?? ''),
            'size' => strlen($content),
        ], $extra));

        return $attachment;
    }

    /**
     * Kopiert einen gespeicherten Anhang an einen anderen Träger (MVP-1077:
     * Kundeneingang → Ticket). Die Datei wird auf der Disk kopiert, nicht in
     * den Speicher geladen; Quelle und Kopie haben getrennte Lebenszyklen.
     *
     * @param  array<string, mixed>  $extra
     */
    public function copy(Attachment $source, Model $parent, ?int $userId, array $extra = []): Attachment {
        $disk = \Illuminate\Support\Facades\Storage::disk($source->disk);
        $ext = strtolower(File::extension($source->path)) ?: 'bin';
        $path = 'attachments/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.' . $ext;
        $disk->copy($source->path, $path);

        /** @var Attachment $attachment */
        $attachment = $parent->morphMany(Attachment::class, 'attachable')->create(array_merge([
            'user_id' => $userId,
            'disk' => $source->disk,
            'path' => $path,
            'original_name' => $source->original_name,
            'mime' => $source->mime,
            'size' => $source->size,
        ], $extra));

        return $attachment;
    }

    /**
     * Stream-Variante (MVP-1078: Übernahme aus einem Upload-Kanal): schreibt
     * den Inhalt ohne Umweg über den Speicher auf die Disk, prüft Endung und
     * am Inhalt erkannten Typ gegen den Zweck sowie das Speicherkontingent.
     * Abgelehnte Inhalte werden sofort wieder gelöscht.
     *
     * @param  array<string, mixed>  $extra
     *
     * @throws \Illuminate\Validation\ValidationException Format oder Kontingent
     */
    public function storeStream(Model $parent, StreamInterface $stream, string $originalName, ?int $userId, array $extra = [], ?string $folder = null, UploadPurpose $purpose = UploadPurpose::General): Attachment {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $base = ($folder ?? 'attachments') . '/' . now()->format('Y/m') . '/' . Str::uuid()->toString();
        $resource = $stream->detach() ?? throw new \RuntimeException('Upload stream is not readable.');
        $disk->writeStream($base, $resource);
        if (is_resource($resource)) {
            fclose($resource);
        }

        $name = File::sanitizeDisplayName($originalName);
        try {
            $size = (int) $disk->size($base);
            $this->guardQuota($parent, $size);
            $mime = File::mimeType($disk->path($base)) ?: '';
            if (! in_array(strtolower(File::extension($originalName)), $purpose->extensions(), true) || ! in_array($mime, $purpose->mimes(), true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => (string) __('uploads.error.type', ['name' => '„' . $name . '“'])]);
            }
            $ext = File::extensionForMimeType($mime) ?? 'bin';
            $path = $base . '.' . (in_array($ext, $purpose->extensions(), true) ? $ext : 'bin');
            $disk->move($base, $path);
        } catch (\Throwable $e) {
            $disk->delete($base);

            throw $e;
        }

        /** @var Attachment $attachment */
        $attachment = $parent->morphMany(Attachment::class, 'attachable')->create(array_merge([
            'user_id' => $userId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'mime' => $mime,
            'size' => $size,
        ], $extra));

        return $attachment;
    }

    /**
     * Vollaudit 2026-07 (H8): storage_quota_gb der Lizenz gilt für alle
     * Uploads über dieses Bauteil; als Validierungsfehler gemappt.
     */
    private function guardQuota(Model $parent, int $size): void {
        $orgId = $parent->getAttribute('organization_id');
        if ($orgId === null) {
            return;
        }
        try {
            app(\App\Services\Licensing\LimitGuard::class)->ensureCanStoreAttachment(
                \App\Models\Platform\Organization::query()->withoutGlobalScopes()->findOrFail((int) $orgId),
                $size,
            );
        } catch (\App\Exceptions\LimitExceededException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => $e->getMessage()]);
        }
    }
}
