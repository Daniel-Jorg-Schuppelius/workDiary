<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiCallerPopupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Cti;

use App\Models\{CtiConnection, Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 056, MVP-118 (Rang 9): Anrufer-Pop-up. Prüft, dass ein eingehender
 * Anruf auf die Opt-in-Durchwahl eines Mitarbeiters diesem eine In-App-
 * Benachrichtigung schickt (mit Kundenlink, falls bekannt), ohne Opt-in nichts
 * passiert, ausgehende Anrufe kein Pop-up erzeugen und ein erneut zugestelltes
 * Ereignis nicht doppelt benachrichtigt.
 */
final class CtiCallerPopupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $token;

    private User $owner;

    /** Eigene Durchwahl, die der Mitarbeiter hinterlegt (= angerufene Nummer). */
    private const EXTENSION = '+493088990011';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();

        [, $this->token] = CtiConnection::issue($this->organization->id, 'Zentrale', 'generic');
    }

    private function optedInUser(string $extension = self::EXTENSION): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->setCtiExtension($extension);
        $user->save();

        return $user;
    }

    private function customer(string $name = 'Muster GmbH', string $phone = '+493012345678'): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function inbound(string $callId, string $from, string $to = self::EXTENSION): TestResponse {
        return $this->postJson('/api/cti/webhook/' . $this->token, [
            'call_id' => $callId,
            'direction' => 'inbound',
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function test_opted_in_callee_is_notified_for_known_customer_with_link(): void {
        $user = $this->optedInUser();
        $this->customer('Muster GmbH', '+493012345678');

        $this->inbound('c-1', '+493012345678')->assertJsonPath('status', 'recorded');

        $user->refresh();
        $this->assertSame(1, $user->notifications()->count());

        /** @var array<string, mixed> $data */
        $data = $user->notifications()->first()->data;
        $this->assertSame('cti.incomingCall', $data['event']);
        $this->assertStringContainsString('Muster GmbH', (string) $data['title']);
        $this->assertIsString($data['url']);
        $this->assertStringContainsString('/customers/', (string) $data['url']);

        // Der System-Actor (Owner) bekommt kein Pop-up — es geht gezielt an die
        // angerufene Durchwahl.
        $this->assertSame(0, $this->owner->notifications()->count());
    }

    public function test_opted_in_callee_is_notified_even_for_unknown_caller_without_link(): void {
        $user = $this->optedInUser();
        $this->customer('Muster GmbH', '+493012345678'); // anderer Anrufer

        $this->inbound('c-2', '+499999999999')->assertJsonPath('status', 'unmatched');

        $user->refresh();
        $this->assertSame(1, $user->notifications()->count());

        /** @var array<string, mixed> $data */
        $data = $user->notifications()->first()->data;
        $this->assertSame('cti.incomingCall', $data['event']);
        $this->assertNull($data['url']); // unbekannter Anrufer → kein Kundenlink
    }

    public function test_no_optin_means_no_popup(): void {
        // Mitarbeiter ohne hinterlegte Durchwahl.
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer('Muster GmbH', '+493012345678');

        $this->inbound('c-3', '+493012345678')->assertJsonPath('status', 'recorded');

        $user->refresh();
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_outbound_call_does_not_pop_up(): void {
        $user = $this->optedInUser();

        // Ausgehender Anruf, dessen Zielnummer zufällig der Opt-in-Durchwahl
        // gleicht — trotzdem kein Pop-up (nur eingehende Anrufe).
        $this->postJson('/api/cti/webhook/' . $this->token, [
            'call_id' => 'c-4',
            'direction' => 'outbound',
            'from' => self::EXTENSION,
            'to' => self::EXTENSION,
        ]);

        $user->refresh();
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_replayed_event_does_not_notify_twice(): void {
        $user = $this->optedInUser();
        $this->customer('Muster GmbH', '+493012345678');

        $this->inbound('dup', '+493012345678')->assertJsonPath('status', 'recorded');
        $this->inbound('dup', '+493012345678')->assertJsonPath('status', 'skipped');

        $user->refresh();
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_set_cti_extension_clears_optin_on_empty(): void {
        $user = $this->optedInUser();
        $this->assertTrue($user->hasCtiOptIn());

        $user->setCtiExtension('');
        $user->save();

        $this->assertFalse($user->fresh()->hasCtiOptIn());
        $this->assertNull($user->fresh()->cti_extension);
    }
}
