<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemPhotoService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Enums\Protocol\{ProtocolEventType, ProtocolItemPhotoPhase};
use App\Models\{Attachment, Protocol, ProtocolItem, ProtocolItemPhoto, User};
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Storage};
use InvalidArgumentException;
use RuntimeException;

/**
 * Verwaltet Vorher-/Nachher-Fotos je Protokollpunkt (MVP-023).
 *
 * Verantwortlich fuer Attach/Detach des `Attachment` an einen
 * `protocol_items`-Datensatz inkl. Phase, Caption und Sortierung sowie
 * fuer Audit-Trail (siehe MVP-023 §6) und EXIF-Auswertung
 * (`taken_at`/Geo) nach DSGVO-Setting.
 */
class ProtocolItemPhotoService {
    /** Maximalgroesse je Foto-Upload (10 MB, MVP-023 §4.2). */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Maximale lange Kante nach Resize (MVP-023 Kriterium 8). */
    public const MAX_EDGE_PX = 2560;

    /** Erlaubte Bild-MIMEs (MVP-023 §4.2). */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function __construct(private readonly ProtocolService $protocols) {}

    /**
     * Bequemer Upload-Pfad: legt ein {@see Attachment} an und verbindet es
     * direkt mit dem Protokollpunkt unter der gewuenschten Phase.
     *
     * @param  array{caption?: ?string, sort_order?: ?int, allow_geo?: bool, disk?: string}  $options
     */
    public function upload(
        ProtocolItem $item,
        UploadedFile $file,
        ProtocolItemPhotoPhase $phase,
        User $actor,
        array $options = [],
    ): ProtocolItemPhoto {
        $mime = $file->getMimeType() ?? '';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException(sprintf('MIME-Typ %s ist fuer Protokoll-Fotos nicht erlaubt.', $mime));
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException(sprintf('Foto ueberschreitet Maximalgroesse von %d Bytes.', self::MAX_BYTES));
        }

        $disk = (string) ($options['disk'] ?? 'local');
        $folder = 'protocol-photos/' . now()->format('Y/m');
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs($folder, $filename, $disk);

        // Vollaudit 2026-07 (H5): taken_at/geo VOR dem Stripping sichern —
        // danach bereinigt sanitizePhoto() die Datei (EXIF/GPS weg, Default-on).
        $absolute = Storage::disk($disk)->path((string) $path);
        $options['exif'] = $this->readExifFromPath($absolute, (bool) ($options['allow_geo'] ?? false));
        $this->sanitizePhoto($absolute, $mime);
        clearstatcache(true, $absolute);

        $attachment = Attachment::query()->create([
            'attachable_type' => ProtocolItem::class,
            'attachable_id' => $item->id,
            'user_id' => $actor->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => \App\Support\Filename::sanitize($file->getClientOriginalName()),
            'mime' => $mime,
            'size' => (int) (@filesize($absolute) ?: $file->getSize()),
        ]);

        return $this->attach($item, $attachment, $phase, $actor, $options);
    }

    /**
     * Verknuepft ein bestehendes Attachment mit einem Protokollpunkt
     * unter einer bestimmten Phase.
     *
     * @param  array{caption?: ?string, sort_order?: ?int, allow_geo?: bool, exif?: array<string, mixed>}  $options
     */
    public function attach(
        ProtocolItem $item,
        Attachment $attachment,
        ProtocolItemPhotoPhase $phase,
        User $actor,
        array $options = [],
    ): ProtocolItemPhoto {
        $protocol = $this->protocolFor($item);

        return DB::transaction(function () use ($protocol, $item, $attachment, $phase, $actor, $options): ProtocolItemPhoto {
            $existing = ProtocolItemPhoto::query()
                ->where('protocol_item_id', $item->id)
                ->where('attachment_id', $attachment->id)
                ->first();
            if ($existing !== null) {
                throw new RuntimeException(sprintf(
                    'Attachment %d ist Protokollpunkt %d bereits zugeordnet.',
                    $attachment->id,
                    $item->id,
                ));
            }

            $sort = $options['sort_order']
                ?? ((int) ProtocolItemPhoto::query()
                    ->where('protocol_item_id', $item->id)
                    ->where('phase', $phase->value)
                    ->max('sort_order') + 1);

            // Upload-Pfad liefert die VOR dem Stripping gelesenen EXIF-Werte mit;
            // der direkte attach()-Pfad liest weiterhin aus der Datei.
            /** @var array{taken_at?: \Illuminate\Support\Carbon, geo_lat?: string, geo_lng?: string}|null $preExif */
            $preExif = $options['exif'] ?? null;
            $exif = $preExif ?? $this->extractExif($attachment, (bool) ($options['allow_geo'] ?? false));

            $photo = ProtocolItemPhoto::query()->create([
                'protocol_item_id' => $item->id,
                'attachment_id' => $attachment->id,
                'phase' => $phase->value,
                'caption' => $options['caption'] ?? null,
                'sort_order' => $sort,
                'taken_at' => $exif['taken_at'] ?? null,
                'geo_lat' => $exif['geo_lat'] ?? null,
                'geo_lng' => $exif['geo_lng'] ?? null,
                'captured_by_user_id' => $actor->id,
            ]);

            $this->protocols->logEvent($protocol, ProtocolEventType::ItemPhotoAdded, $actor, [
                'item_id' => $item->id,
                'photo_id' => $photo->id,
                'attachment_id' => $attachment->id,
                'phase' => $phase->value,
                'caption' => $photo->caption,
            ]);

            return $photo;
        });
    }

    public function detach(ProtocolItemPhoto $photo, User $actor): void {
        $item = $photo->item()->firstOrFail();
        $protocol = $this->protocolFor($item);

        DB::transaction(function () use ($photo, $item, $protocol, $actor): void {
            $payload = [
                'item_id' => $item->id,
                'photo_id' => $photo->id,
                'attachment_id' => $photo->attachment_id,
                'phase' => $photo->phase->value,
            ];
            $photo->delete();
            $this->protocols->logEvent($protocol, ProtocolEventType::ItemPhotoRemoved, $actor, $payload);
        });
    }

    public function updateCaption(ProtocolItemPhoto $photo, ?string $caption, User $actor): ProtocolItemPhoto {
        $item = $photo->item()->firstOrFail();
        $protocol = $this->protocolFor($item);

        DB::transaction(function () use ($photo, $caption, $protocol, $item, $actor): void {
            $old = $photo->caption;
            $photo->update(['caption' => $caption]);
            $this->protocols->logEvent($protocol, ProtocolEventType::ItemPhotoUpdatedCaption, $actor, [
                'item_id' => $item->id,
                'photo_id' => $photo->id,
                'old' => $old,
                'new' => $caption,
            ]);
        });

        return $photo->refresh();
    }

    /**
     * Neue Reihenfolge innerhalb einer Phase.
     *
     * @param  list<int>  $orderedPhotoIds
     */
    public function reorder(ProtocolItem $item, ProtocolItemPhotoPhase $phase, array $orderedPhotoIds, User $actor): void {
        $protocol = $this->protocolFor($item);

        DB::transaction(function () use ($item, $phase, $orderedPhotoIds, $protocol, $actor): void {
            $photos = ProtocolItemPhoto::query()
                ->where('protocol_item_id', $item->id)
                ->where('phase', $phase->value)
                ->get()
                ->keyBy('id');

            foreach ($orderedPhotoIds as $position => $photoId) {
                $photo = $photos->get($photoId);
                if ($photo === null) {
                    throw new InvalidArgumentException(sprintf('Foto %d gehoert nicht zu Punkt %d/%s.', $photoId, $item->id, $phase->value));
                }
                $photo->update(['sort_order' => $position + 1]);
            }

            $this->protocols->logEvent($protocol, ProtocolEventType::ItemPhotoReordered, $actor, [
                'item_id' => $item->id,
                'phase' => $phase->value,
                'order' => $orderedPhotoIds,
            ]);
        });
    }

    /**
     * @return array{taken_at?: \Illuminate\Support\Carbon, geo_lat?: string, geo_lng?: string}
     */
    private function extractExif(Attachment $attachment, bool $allowGeo): array {
        try {
            $disk = Storage::disk($attachment->disk);
            if (! $disk->exists($attachment->path)) {
                return [];
            }

            return $this->readExifFromPath($disk->path($attachment->path), $allowGeo);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Bereinigt die gespeicherte Bilddatei (Vollaudit 2026-07, H5; MVP-023 §4):
     * Orientierung einbrennen, lange Kante auf MAX_EDGE_PX begrenzen
     * (Kriterium 8) und ALLE Metadaten-Profile entfernen (EXIF/GPS/XMP) —
     * die Datei ist per Attachment-Download für jeden Protokoll-Berechtigten
     * abrufbar. Das ICC-Farbprofil bleibt erhalten. taken_at/geo sind zu
     * diesem Zeitpunkt bereits in die Pivot-Spalten übernommen (dort schützt
     * Permission::ProtocolItemPhotoViewGeo). Scheitert die Bereinigung, wird
     * der Upload abgelehnt — Datenschutz-Default-on schlägt Komfort.
     * Fachneutral → Klasse-C-Kandidat für php-common-toolkit (mit Nutzer
     * abstimmen, bis dahin bewusst app-lokal).
     */
    private function sanitizePhoto(string $absolutePath, string $mime): void {
        try {
            if (class_exists(\Imagick::class)) {
                $img = new \Imagick($absolutePath);
                $this->bakeOrientation($img);
                if (max($img->getImageWidth(), $img->getImageHeight()) > self::MAX_EDGE_PX) {
                    $img->thumbnailImage(self::MAX_EDGE_PX, self::MAX_EDGE_PX, bestfit: true);
                }
                $icc = null;
                try {
                    $icc = $img->getImageProfile('icc');
                } catch (\ImagickException) {
                    // kein ICC-Profil vorhanden
                }
                $img->stripImage();
                if ($icc !== null && $icc !== '') {
                    $img->profileImage('icc', $icc);
                }
                $img->writeImage($absolutePath);
                $img->clear();

                return;
            }

            // GD-Fallback (ohne Imagick): Re-Encode entfernt EXIF; HEIC/HEIF
            // kann GD nicht — dann lieber ablehnen als GPS-Tags ausliefern.
            $src = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($absolutePath),
                'image/png' => imagecreatefrompng($absolutePath),
                'image/webp' => imagecreatefromwebp($absolutePath),
                default => false,
            };
            if (! $src instanceof \GdImage) {
                throw new \RuntimeException('Kein Bild-Backend für ' . $mime);
            }
            $w = imagesx($src);
            $h = imagesy($src);
            if (max($w, $h) > self::MAX_EDGE_PX) {
                $scale = self::MAX_EDGE_PX / max($w, $h);
                $scaled = imagescale($src, (int) round($w * $scale), (int) round($h * $scale));
                if ($scaled instanceof \GdImage) {
                    $src = $scaled;
                }
            }
            match ($mime) {
                'image/jpeg' => imagejpeg($src, $absolutePath, 90),
                'image/png' => imagepng($src, $absolutePath),
                'image/webp' => imagewebp($src, $absolutePath, 90),
                default => null,
            };
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Foto konnte nicht bereinigt werden (EXIF-Stripping) — Upload abgelehnt.', previous: $e);
        }
    }

    /**
     * Orientierung einbrennen, bevor das EXIF-Tag wegfällt — manuell aus dem
     * Orientation-Tag (autoOrient/autoOrientImage sind je Imagick-Version
     * uneinheitlich vorhanden; getImageOrientation/rotateImage sind stabil).
     */
    private function bakeOrientation(\Imagick $img): void {
        match ($img->getImageOrientation()) {
            \Imagick::ORIENTATION_BOTTOMRIGHT => $img->rotateImage('#000', 180),
            \Imagick::ORIENTATION_RIGHTTOP => $img->rotateImage('#000', 90),
            \Imagick::ORIENTATION_LEFTBOTTOM => $img->rotateImage('#000', -90),
            default => null,
        };
        $img->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * @return array{taken_at?: \Illuminate\Support\Carbon, geo_lat?: string, geo_lng?: string}
     */
    private function readExifFromPath(string $absolute, bool $allowGeo): array {
        if (! function_exists('exif_read_data')) {
            return [];
        }
        try {
            if (! ToolkitFile::isReadable($absolute)) {
                return [];
            }
            $exif = @exif_read_data($absolute);
            if ($exif === false) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        if (! empty($exif['DateTimeOriginal'])) {
            try {
                $out['taken_at'] = Carbon::createFromFormat('Y:m:d H:i:s', (string) $exif['DateTimeOriginal']) ?: null;
                if ($out['taken_at'] === null) {
                    unset($out['taken_at']);
                }
            } catch (\Throwable) {
                // Format ignorieren
            }
        }
        if ($allowGeo && isset($exif['GPSLatitude'], $exif['GPSLongitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitudeRef'])) {
            $lat = $this->gpsToDecimal((array) $exif['GPSLatitude'], (string) $exif['GPSLatitudeRef']);
            $lng = $this->gpsToDecimal((array) $exif['GPSLongitude'], (string) $exif['GPSLongitudeRef']);
            if ($lat !== null && $lng !== null) {
                $out['geo_lat'] = number_format($lat, 6, '.', '');
                $out['geo_lng'] = number_format($lng, 6, '.', '');
            }
        }
        return $out;
    }

    /**
     * @param  list<string>|array<int, string>  $coord
     */
    private function gpsToDecimal(array $coord, string $ref): ?float {
        if (count($coord) < 3) {
            return null;
        }
        $parse = static function (string $rational): float {
            if (str_contains($rational, '/')) {
                [$n, $d] = explode('/', $rational, 2);
                return ((float) $d) !== 0.0 ? ((float) $n) / ((float) $d) : 0.0;
            }
            return (float) $rational;
        };
        $deg = $parse((string) $coord[0]);
        $min = $parse((string) $coord[1]);
        $sec = $parse((string) $coord[2]);
        $value = $deg + ($min / 60) + ($sec / 3600);
        return in_array(strtoupper($ref), ['S', 'W'], true) ? -$value : $value;
    }

    private function protocolFor(ProtocolItem $item): Protocol {
        return $item->protocol()->firstOrFail();
    }
}
