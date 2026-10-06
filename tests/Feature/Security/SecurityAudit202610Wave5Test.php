<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAudit202610Wave5Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Classification\Tag;
use App\Plugins\Support\{PluginApiClient, PluginApiException};
use App\Rules\ColorValue;
use App\Services\Mcp\WorkDiaryMcpServer;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\{HandlerStack, Middleware};
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Attributes\Instructions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Regressionstests zur Welle 5 des Sicherheitsaudits 2026-10-04
 * (WorkDiary-Architecture/security/sicherheitsaudit-2026-10-04-behebung.md).
 * `authz-a-11` und `authz-a-12` stehen bei den Vereinstests
 * (`ClubExamTest`, `ClubCheckInTest`, `ClubAttendanceTest`).
 */
final class SecurityAudit202610Wave5Test extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array<string, array{string, bool}> */
    public static function colorValues(): array {
        return [
            'Hex sechsstellig' => ['#1f6feb', true],
            'Hex kurz' => ['#abc', true],
            'Hex mit Alpha' => ['#1f6feb80', true],
            'Ton-Name' => ['primary', true],
            'Farbname mit Bindestrich' => ['gelb-orange', true],
            'zweite Deklaration' => ['red;top:0', false],
            'Funktion' => ['url(x)', false],
            'Leerzeichen' => ['red blue', false],
            'Hex ohne Raute' => ['1f6feb', false],
            'Raute allein' => ['#', false],
        ];
    }

    /** xi-8: Farbwerte landen in `style` und `class` — nur Hex oder ein Name ohne Trennzeichen. */
    #[DataProvider('colorValues')]
    public function test_color_fields_take_hex_or_a_plain_name(string $value, bool $valid): void {
        $validator = Validator::make(['color' => $value], ['color' => ['string', 'max:16', new ColorValue]]);

        $this->assertSame($valid, $validator->passes());
    }

    /** xi-5: die Server-Anweisung nennt die sofort wirkenden Werkzeuge und kennzeichnet Freitext als Inhalt. */
    public function test_mcp_instructions_name_immediate_tools_and_mark_free_text(): void {
        $attributes = (new \ReflectionClass(WorkDiaryMcpServer::class))->getAttributes(Instructions::class);
        $text = (string) ($attributes[0]->getArguments()[0] ?? '');

        $this->assertStringContainsString('create_customer und reschedule_order wirken sofort', $text);
        $this->assertStringContainsString('keine Anweisung', $text);
        $this->assertStringNotContainsString('legen nur Entwürfe an', $text);
    }

    /** sf-4: eine vom Server genannte Folge-URL wird nur auf dem Host der Verbindung abgerufen. */
    public function test_follow_up_urls_stay_on_the_host_of_the_connection(): void {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(200, [], '{"value":[]}'), new GuzzleResponse(200, [], '{"value":[]}')]));
        $stack->push(Middleware::history($history));
        $client = new PluginApiClient('test', 'https://graph.example.test/v1.0', new GuzzleClient(['handler' => $stack]));

        $this->assertTrue($client->getFollowUp('https://graph.example.test/v1.0/me/events?$skiptoken=abc')->successful());
        $this->assertTrue($client->getFollowUp('/me/events?$skiptoken=abc')->successful());

        foreach (['https://other.example.test/v1.0/me/events', 'http://graph.example.test/v1.0/me/events', 'https://graph.example.test:8443/x', '//other.example.test/x'] as $foreign) {
            try {
                $client->getFollowUp($foreign);
                $this->fail('Folge-URL auf fremdem Ziel wurde abgerufen: ' . $foreign);
            } catch (PluginApiException) {
                // erwartet
            }
        }
        $this->assertCount(2, $history, 'Abgewiesene Folge-URLs lösen keinen Abruf aus.');
    }

    /** sf-3: zeigt das Ziel beim Verbindungsaufbau nach innen, unterbleibt der Abruf — ohne Freigabe privater Netze. */
    public function test_plugin_client_refuses_a_target_that_resolves_to_an_internal_address(): void {
        $client = new PluginApiClient('test', 'https://127.0.0.1');

        try {
            $client->getResponse('/status');
            $this->fail('Der Abruf auf eine interne Adresse wurde nicht abgewiesen.');
        } catch (PluginApiException $e) {
            $this->assertStringContainsString('interne Adresse', $e->getMessage());
        }
    }

    public function test_tag_color_is_checked_on_store(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('tags.store'), ['name' => 'Eilig', 'color' => 'red;top:0'])->assertSessionHasErrors('color');
        $this->assertFalse(Tag::query()->where('name', 'Eilig')->exists());

        $this->actingAs($admin)->post(route('tags.store'), ['name' => 'Eilig', 'color' => '#d1242f'])->assertSessionHasNoErrors();
        $this->assertSame('#d1242f', Tag::query()->where('name', 'Eilig')->sole()->color);
    }
}
