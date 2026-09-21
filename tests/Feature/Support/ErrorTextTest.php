<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorTextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Http\Middleware\AssignRequestId;
use App\Support\ErrorText;
use Illuminate\Support\Facades\Log;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * MVP-827: Fachtexte aus eigenem Code bleiben sichtbar, technische Texte aus
 * vendor/ erscheinen nur als Verweis mit Request-ID und landen im Protokoll.
 */
class ErrorTextTest extends TestCase {
    private function thrownIn(Throwable $e, string $file): Throwable {
        (new ReflectionProperty($e, 'file'))->setValue($e, $file);

        return $e;
    }

    public function test_own_domain_text_is_shown_unchanged(): void {
        $e = $this->thrownIn(new RuntimeException('Diese Anfrage ist bereits entschieden.'), app_path('Services/Example.php'));

        $this->assertSame('Diese Anfrage ist bereits entschieden.', ErrorText::for($e));
    }

    public function test_vendor_text_becomes_a_reference_to_the_log(): void {
        Log::spy();
        app()->instance(AssignRequestId::CONTAINER_KEY, '01TESTREQUEST');
        $e = $this->thrownIn(new RuntimeException('cURL error 7: Failed to connect to 10.0.0.5 port 3128'), base_path('vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php'));

        $text = ErrorText::for($e);

        $this->assertStringNotContainsString('10.0.0.5', $text);
        $this->assertStringContainsString('01TESTREQUEST', $text);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_own_wrapper_keeps_its_frame_and_hides_the_foreign_detail(): void {
        $inner = $this->thrownIn(new RuntimeException('ZipArchive::open(): error 19'), base_path('vendor/some/lib/Zip.php'));
        $outer = $this->thrownIn(new RuntimeException('Die ZIP-Datei ist unlesbar: ZipArchive::open(): error 19', 0, $inner), app_path('Services/Import/Example.php'));

        $text = ErrorText::for($outer);

        $this->assertStringStartsWith('Die ZIP-Datei ist unlesbar: ', $text);
        $this->assertStringNotContainsString('ZipArchive', $text);
    }
}
