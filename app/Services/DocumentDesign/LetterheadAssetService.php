<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LetterheadAssetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{LetterheadAssetStatus, LetterheadPageRole};
use App\Models\DocumentDesign\LetterheadAsset;
use App\Models\{Organization, User};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDFToolkit\Enums\PaperFormat;
use PDFToolkit\Helper\PDFHelper;

/**
 * Sicherer Firmenbogen-Upload (MVP-296): Endung, deklariertes MIME und
 * Dateisignatur müssen übereinstimmen. PDFs werden zu einer nicht
 * interaktiven Rasterseite reduziert (verwirft aktive Inhalte, Formulare,
 * Skripte, externe Referenzen und Anhänge); Rasterbilder werden per GD
 * re-encodiert (entfernt Metadaten, deckt Transparenz auf Weiß ab). Das
 * unveränderte Original bleibt als administrativer Nachweis gespeichert,
 * wird aber nie in Ausgabedokumente übernommen.
 */
class LetterheadAssetService {
    /** A4 Hochformat in Millimetern (MVP-Umfang). */
    private const A4_WIDTH_MM = 210.0;

    private const A4_HEIGHT_MM = 297.0;

    private const SIGNATURES = [
        'pdf' => "%PDF-",
        'png' => "\x89PNG\x0D\x0A\x1A\x0A",
        'jpg' => "\xFF\xD8\xFF",
    ];

    public function store(Organization $organization, UploadedFile $file, LetterheadPageRole $role, string $name, ?User $uploader = null): LetterheadAsset {
        $sourceType = $this->sourceType($file);
        $raw = (string) file_get_contents($file->getRealPath());

        if (strlen($raw) > (int) config('document_design.limits.max_kb') * 1024) {
            throw new InvalidArgumentException(__('Die Datei überschreitet die maximale Größe.'));
        }
        if (! str_starts_with($raw, self::SIGNATURES[$sourceType])) {
            throw new InvalidArgumentException(__('Dateiendung und Dateiinhalt stimmen nicht überein.'));
        }

        $disk = (string) config('document_design.disk');
        $base = sprintf('document-design/%d', $organization->id);
        $token = Str::random(20);
        $originalPath = sprintf('%s/originals/%s.%s', $base, $token, $sourceType);
        Storage::disk($disk)->put($originalPath, $raw);

        $asset = new LetterheadAsset([
            'organization_id' => $organization->id,
            'name' => $name,
            'page_role' => $role,
            'source_type' => $sourceType,
            'disk' => $disk,
            'original_path' => $originalPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => (string) $file->getMimeType(),
            'size' => strlen($raw),
            'original_sha256' => CryptoHelper::hash($raw),
            'status' => LetterheadAssetStatus::ReviewRequired,
            'uploaded_by' => $uploader?->id,
        ]);

        $notes = [];
        $png = $sourceType === 'pdf'
            ? $this->normalizePdf($raw, $notes)
            : $this->normalizeImage($raw, $notes);

        if ($png !== null) {
            $normalizedPath = sprintf('%s/normalized/%s.png', $base, $token);
            Storage::disk($disk)->put($normalizedPath, $png);
            $asset->normalized_path = $normalizedPath;
            $asset->normalized_sha256 = CryptoHelper::hash($png);
            $asset->width_mm = sprintf('%.2f', self::A4_WIDTH_MM);
            $asset->height_mm = sprintf('%.2f', self::A4_HEIGHT_MM);
        }

        $asset->status = ($png !== null && $notes === [])
            ? LetterheadAssetStatus::Ready
            : LetterheadAssetStatus::ReviewRequired;
        $asset->review_notes = $notes === [] ? null : array_values($notes);
        $asset->save();

        return $asset;
    }

    public function archive(LetterheadAsset $asset): void {
        $asset->status = LetterheadAssetStatus::Archived;
        $asset->save();
    }

    private function sourceType(UploadedFile $file): string {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $mime = (string) $file->getMimeType();

        $expected = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => throw new InvalidArgumentException(__('Nur PDF, JPG oder PNG werden unterstützt.')),
        };
        if ($mime !== $expected) {
            throw new InvalidArgumentException(__('Dateiendung und Dateiinhalt stimmen nicht überein.'));
        }

        return $ext;
    }

    /**
     * PDF → sichere Rasterseite. Nicht verarbeitbare Strukturen führen zu
     * „Prüfung erforderlich" statt zu einer stillen Teilverarbeitung.
     *
     * @param array<int, string> $notes
     */
    private function normalizePdf(string $raw, array &$notes): ?string {
        $base = tempnam(sys_get_temp_dir(), 'lh_');
        if ($base === false) {
            $notes[] = (string) __('Temporäre Datei konnte nicht angelegt werden.');

            return null;
        }
        // Das pdf-toolkit prüft die Dateiendung — tempnam liefert keine.
        $tmp = $base . '.pdf';

        try {
            file_put_contents($tmp, $raw);

            if (! PDFHelper::isValidPdf($tmp)) {
                $notes[] = (string) __('Die PDF-Struktur ist ungültig oder beschädigt.');

                return null;
            }
            if (PDFHelper::getPageCount($tmp) !== 1) {
                $notes[] = (string) __('Der Firmenbogen muss genau eine Seite besitzen (getrenntes Asset je Seitenrolle).');

                return null;
            }

            $size = PDFHelper::getPageSize($tmp);
            $format = PDFHelper::detectFormat($tmp);
            if ($format !== PaperFormat::A4 || ($size !== null && $size->isLandscape())) {
                $notes[] = (string) __('Nur A4 Hochformat wird im MVP unterstützt.');

                return null;
            }

            $rendered = PDFHelper::renderPageToImage($tmp, 1, (int) config('document_design.render_dpi'));
            if ($rendered === null) {
                $notes[] = (string) __('Die PDF-Seite konnte nicht sicher gerastert werden.');

                return null;
            }

            try {
                $png = (string) file_get_contents($rendered);
            } finally {
                @unlink($rendered);
            }

            // Auch die Rasterung re-encodieren: deckt Transparenz ab und
            // garantiert ein einheitliches, metadatenfreies PNG.
            return $this->normalizeImage($png, $notes);
        } finally {
            @unlink($tmp);
            @unlink($base);
        }
    }

    /**
     * Rasterbild → farbtreues, deckendes PNG ohne Metadaten.
     *
     * @param array<int, string> $notes
     */
    private function normalizeImage(string $raw, array &$notes): ?string {
        $info = @getimagesizefromstring($raw);
        if ($info === false) {
            $notes[] = (string) __('Das Bild konnte nicht gelesen werden.');

            return null;
        }

        [$width, $height] = $info;
        if ($width * $height > (int) config('document_design.limits.max_pixels')) {
            $notes[] = (string) __('Das Bild überschreitet die Pixel-Obergrenze.');

            return null;
        }
        if ($width < (int) config('document_design.limits.min_width_px')) {
            $notes[] = (string) __('Die Auflösung ist für den Druck zu gering.');

            return null;
        }

        $ratio = $height / max(1, $width);
        $target = self::A4_HEIGHT_MM / self::A4_WIDTH_MM;
        if (abs($ratio - $target) / $target > (float) config('document_design.aspect_tolerance')) {
            $notes[] = (string) __('Das Seitenverhältnis entspricht nicht A4 Hochformat.');

            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            $notes[] = (string) __('Das Bild konnte nicht verarbeitet werden.');

            return null;
        }

        // Ziel: A4 bei konfigurierter DPI, Transparenz auf Weiß abgedeckt.
        $dpi = (int) config('document_design.render_dpi');
        $outWidth = min($width, (int) round(self::A4_WIDTH_MM / 25.4 * $dpi));
        $outHeight = (int) round($outWidth * $target);

        $out = imagecreatetruecolor(max(1, $outWidth), max(1, $outHeight));
        $white = (int) imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $white);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $outWidth, $outHeight, $width, $height);
        imagedestroy($src);

        ob_start();
        imagepng($out, null, 6);
        imagedestroy($out);

        return (string) ob_get_clean();
    }
}
