<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingMetadataScrubberTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Whistleblowing;

use App\Services\Whistleblowing\WhistleblowingMetadataScrubber;
use PHPUnit\Framework\TestCase;

/** Vollscan 2026-09-15 (MVP-796-Nebenbefund): der Bereiniger entfernt Metadaten aus Bildern und lässt Fremdes unverändert. */
final class WhistleblowingMetadataScrubberTest extends TestCase {
    public function test_png_text_chunks_are_removed_and_the_image_survives(): void {
        if (! function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD fehlt.');
        }
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        $withAuthor = self::insertTextChunk($png, 'Author', 'Max Muster, Abteilung 4');
        self::assertStringContainsString('Max Muster', $withAuthor);

        $scrubbed = (new WhistleblowingMetadataScrubber)->scrub($withAuthor, 'image/png');

        self::assertNotNull($scrubbed);
        self::assertStringNotContainsString('Max Muster', $scrubbed);
        self::assertNotFalse(@imagecreatefromstring($scrubbed));
    }

    public function test_unsupported_types_and_broken_images_stay_untouched(): void {
        $scrubber = new WhistleblowingMetadataScrubber;

        self::assertNull($scrubber->scrub('%PDF-1.4 Autor: Max', 'application/pdf'));
        self::assertNull($scrubber->scrub('kein Bild', 'image/jpeg'));
        self::assertFalse($scrubber->supports('application/msword'));
        self::assertTrue($scrubber->supports(' IMAGE/JPEG '));
    }

    /** tEXt-Chunk vor IEND einfügen (Länge, Typ, Daten, CRC über Typ und Daten). */
    private static function insertTextChunk(string $png, string $keyword, string $text): string {
        $data = $keyword . "\0" . $text;
        $chunk = pack('N', strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));
        $iend = strrpos($png, 'IEND') - 4;

        return substr($png, 0, $iend) . $chunk . substr($png, $iend);
    }
}
