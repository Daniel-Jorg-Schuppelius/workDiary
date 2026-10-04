<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignedAttachmentLinkRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Anhang-Downloads nur signiert verlinken" (Anlass:
 * UI-Vollcrawl 2026-10-03 — Geräteseite, Auftrags-Timeline und globale Suche
 * verlinkten den Download nackt und liefen ins 403).
 *
 * `AttachmentController::download()` verlangt eine gültige Signatur. Richtig
 * ist `URL::signedRoute('attachments.download', …)` bzw.
 * `AttachmentController::downloadUrl()`; die API nennt `api.attachments.download`.
 */
class SignedAttachmentLinkRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_attachment_downloads_are_linked_signed(): void {
        $violations = [];
        $files = array_merge($this->phpFiles('app'), $this->bladeFiles('resources/views'), $this->bladeFiles('app/Plugins'));
        foreach (array_unique($files) as $file) {
            $source = (string) file_get_contents($file);
            // signedRoute( / temporarySignedRoute( tragen ein großes R und fallen nicht darunter.
            if (preg_match_all('/\broute\(\s*[\'"]attachments\.download[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [, $offset]) {
                    $violations[] = $this->relativePath($file) . ':' . $this->lineOf($source, (int) $offset);
                }
            }
        }

        $this->assertSame([], $violations, "Anhang-Download ohne Signatur verlinkt (endet im 403):\n" . implode("\n", $violations));
    }
}
