<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImageMetaUploader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verwaltet "meta-typisierte" Bild-Anhänge (Logo, Avatar, ...): pro Eltern-
 * Modell und meta_type existiert höchstens ein aktiver Anhang. Beim Ersetzen
 * wird der Vorgänger inkl. Datei vom Storage entfernt.
 */
class ImageMetaUploader
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Ersetzt das Bild mit dem angegebenen meta_type am Eltern-Modell.
     *
     * @throws ValidationException Bei Größen-/Format-/Bildvalidierungsfehlern
     *                             — Aufrufer kann das ValidationException-Bag
     *                             direkt an Laravel zurückwerfen.
     */
    public function replace(Organization|User $parent, string $meta, UploadedFile $file, int $maxKb, string $fieldName = 'file'): Attachment
    {
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                $fieldName => __('Datei ist größer als das Limit.'),
            ]);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $fieldName => __('Nur JPG, PNG oder WEBP erlaubt.'),
            ]);
        }

        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($serverMime, self::IMAGE_MIMES, true)) {
            throw ValidationException::withMessages([
                $fieldName => __('Nur JPG, PNG oder WEBP erlaubt.'),
            ]);
        }

        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                $fieldName => __('Datei ist kein gültiges Bild.'),
            ]);
        }

        $this->delete($parent, $meta);

        $folder = 'attachments/'.now()->format('Y/m');
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs($folder, $filename, 'local');

        /** @var Attachment $attachment */
        $attachment = $parent->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => self::sanitizeFilename($file->getClientOriginalName()),
            'mime' => $serverMime,
            'size' => $file->getSize(),
            'meta_type' => $meta,
        ]);

        return $attachment;
    }

    /**
     * Entfernt den aktuellen Anhang dieses meta_type (inkl. Datei). No-op,
     * wenn keiner existiert.
     */
    public function delete(Organization|User $parent, string $meta): void
    {
        /** @var Attachment|null $existing */
        $existing = $parent->attachments()->where('meta_type', $meta)->first();
        if ($existing === null) {
            return;
        }
        Storage::disk($existing->disk)->delete($existing->path);
        $existing->delete();
    }

    private static function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = str_replace(['/', '\\'], '_', $name);

        return trim(mb_substr($name, 0, 255));
    }
}
