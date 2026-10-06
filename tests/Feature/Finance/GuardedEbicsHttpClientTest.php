<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardedEbicsHttpClientTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Services\Finance\Ebics\{GuardedEbicsHttpClient, LibraryEbicsGateway};
use EbicsApi\Ebics\Models\Http\Request;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-10-04, sf-1: die Bank-Adresse setzt ein Mandant. Die
 * Antwort wird deshalb ohne Entitätsauflösung gelesen, und verbunden wird nur
 * mit einem öffentlich erreichbaren, beim Verbinden geprüften Ziel.
 */
final class GuardedEbicsHttpClientTest extends TestCase {
    public function test_entities_in_the_bank_response_are_never_resolved(): void {
        $probe = tempnam(sys_get_temp_dir(), 'ebics');
        file_put_contents((string) $probe, 'sonde-inhalt-4711');

        try {
            $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY a SYSTEM "file://' . $probe . '">]><r><ReportText>&a;</ReportText></r>';
            $document = (new GuardedEbicsHttpClient)->parse($xml);

            $this->assertStringNotContainsString('sonde-inhalt-4711', (string) $document->saveXML());
            $this->assertStringNotContainsString('sonde-inhalt-4711', (string) $document->documentElement?->textContent);
        } finally {
            @unlink((string) $probe);
        }
    }

    public function test_non_public_bank_addresses_are_refused_before_any_connection(): void {
        foreach (['https://127.0.0.1/ebics', 'https://10.0.0.5/ebics', 'http://169.254.169.254/latest'] as $url) {
            try {
                (new GuardedEbicsHttpClient)->post($url, new Request);
                $this->fail('Verbindung hätte abgelehnt werden müssen: ' . $url);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('öffentlich', $e->getMessage());
            }
        }
    }

    public function test_library_gateway_uses_the_guarded_client(): void {
        $source = (string) file_get_contents((string) (new ReflectionClass(LibraryEbicsGateway::class))->getFileName());

        $this->assertStringContainsString('setHttpClient(new GuardedEbicsHttpClient)', $source);
    }
}
