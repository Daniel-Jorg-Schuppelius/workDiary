<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingMetadataScrubber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

/**
 * Entfernt Metadaten aus Meldeanhängen.
 *
 * Ein Beweisfoto trägt in aller Regel mehr, als der Meldende ahnt: Aufnahmezeit,
 * Gerätemodell, Seriennummer, oft GPS-Koordinaten. Die Meldung ist anonym, das
 * Foto ist es nicht — und der Fallbearbeiter sieht beides
 * (Sicherheitsaudit 2026-09-13, Abschnitt 25 des Konzepts sieht die Bereinigung
 * seit jeher vor, umgesetzt war sie nicht).
 *
 * Bilder werden dafür neu kodiert: Damit verschwindet ALLES außer den
 * Bildpunkten, ohne dass eine Liste bekannter Metadatenfelder gepflegt werden
 * muss (die wäre immer unvollständig). PDF und Office-Dokumente kann dieser
 * Bereiniger nicht — sie bleiben als „nicht bereinigt" gekennzeichnet, damit
 * die Lücke sichtbar bleibt statt still zu sein.
 */
final class WhistleblowingMetadataScrubber {
    /** Bildtypen, die sich verlustfrei genug neu kodieren lassen. */
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function supports(?string $mime): bool {
        return in_array(mb_strtolower(trim((string) $mime)), self::SUPPORTED, true);
    }

    /**
     * Bereinigte Fassung oder null, wenn der Typ nicht unterstützt wird oder
     * die Neukodierung fehlschlägt. Null heißt immer: unverändert lassen.
     */
    public function scrub(string $bytes, ?string $mime): ?string {
        if (! $this->supports($mime) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        try {
            ob_start();
            $ok = match (mb_strtolower(trim((string) $mime))) {
                'image/jpeg' => imagejpeg($image, null, 92),
                'image/png' => imagepng($image),
                'image/webp' => function_exists('imagewebp') && imagewebp($image),
                default => false,
            };
            $out = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        return $ok && $out !== '' ? $out : null;
    }
}
