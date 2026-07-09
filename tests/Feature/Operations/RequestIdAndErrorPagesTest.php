<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequestIdAndErrorPagesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdAndErrorPagesTest extends TestCase {
    use RefreshDatabase;

    public function test_every_response_carries_a_request_id_header(): void {
        $response = $this->get('/login');

        $this->assertNotSame('', (string) $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_valid_incoming_request_id_is_kept(): void {
        $response = $this->withHeaders([AssignRequestId::HEADER => 'lb-abc-12345678'])->get('/login');

        $this->assertSame('lb-abc-12345678', $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_invalid_incoming_request_id_is_replaced(): void {
        $response = $this->withHeaders([AssignRequestId::HEADER => '<script>x'])->get('/login');

        $this->assertNotSame('<script>x', $response->headers->get(AssignRequestId::HEADER));
        $this->assertNotSame('', (string) $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_404_page_shows_request_id(): void {
        $response = $this->get('/definitiv-nicht-vorhanden-' . uniqid());

        $response->assertNotFound()
            ->assertSee(__('errors.404.title'))
            ->assertSee(__('errors.request_id'));
    }
}
