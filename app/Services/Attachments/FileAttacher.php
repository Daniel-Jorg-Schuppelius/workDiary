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

use App\Models\Attachment;
use App\Support\{Filename, Setting};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Speichert hochgeladene Dateien als polymorphe {@see Attachment} an einem
 * HasAttachments-Träger. Kapselt die einheitlichen Upload-Regeln (Größe,
 * erlaubte Typen) und das Ablegen auf der `local`-Disk, damit Formulare mit
 * eigenem Datei-Feld (z. B. der Wissensartikel-Dialog) Anhänge im selben
 * Request anlegen können — ohne den generischen AttachmentController-Endpoint.
 */
final class FileAttacher {
    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    /**
     * Maximale Dateigröße in KB (Laravel `max:`-Einheit) — eine Wahrheit für
     * alle generischen Anhang-Uploads, pro Organisation/System übersteuerbar
     * (Setting `uploads.attachment_kb`, Default aus config/uploads.php).
     */
    public static function maxKb(): int {
        return (int) Setting::get('uploads.attachment_kb', 25600);
    }

    /** Wie {@see maxKb()}, gerundet auf ganze MB (für UI-Hinweise/Meldungen). */
    public static function maxMb(): int {
        return max(1, (int) round(self::maxKb() / 1024));
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
     */
    public function store(Model $parent, UploadedFile $file, ?int $userId): Attachment {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $path = $file->storeAs('attachments/' . now()->format('Y/m'), Str::uuid()->toString() . '.' . $ext, 'local');

        // Über die generische morphMany-Relation (statt der HasAttachments-
        // Trait-Methode), damit der Service jeden Model-Träger akzeptiert; die
        // Morph-Definition entspricht exakt HasAttachments::attachments().
        /** @var Attachment $attachment */
        $attachment = $parent->morphMany(Attachment::class, 'attachable')->create([
            'user_id' => $userId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Filename::sanitize($file->getClientOriginalName()),
            'mime' => $file->getMimeType() ?? '',
            'size' => $file->getSize(),
        ]);

        return $attachment;
    }
}
