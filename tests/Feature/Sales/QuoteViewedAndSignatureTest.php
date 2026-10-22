<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteViewedAndSignatureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\QuoteStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sales\Quote;
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Angebot „angesehen“ und Annahme mit Unterschrift (MVP-1281). */
final class QuoteViewedAndSignatureTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** 1×1 Pixel, PNG. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @return array{0: Quote, 1: string, 2: User} */
    private function sentQuote(): array {
        $this->setUpOrganization();
        Storage::fake('local');
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Kunde', 'currency' => 'EUR', 'created_by' => $user->id]);
        $service = app(QuoteService::class);
        $quote = $service->create(['customer_id' => $customer->id, 'valid_until' => now()->addWeeks(2)->toDateString()], [
            ['description' => 'Leistung', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => '19.00'],
        ], $user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($service->approve($quote, $user), $user);

        return [$quote, $token, $user];
    }

    public function test_first_view_is_stamped_once_and_signature_is_kept_with_the_decision(): void {
        [$quote, $token, $user] = $this->sentQuote();
        $show = route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token]);

        $this->get($show)->assertOk()->assertSee(__('quotes.signature.pad'));
        $viewedAt = $quote->fresh()->viewed_at;
        $this->assertNotNull($viewedAt);
        $this->travel(5)->minutes();
        $this->get($show)->assertOk();
        $this->assertSame($viewedAt->toIso8601String(), $quote->fresh()->viewed_at?->toIso8601String());
        $this->assertSame(1, AuditLog::query()->where('event', 'quote.viewed')->count());

        $this->post(route('quotes.portal.decide', ['quote' => $quote->getRouteKey()]), [
            'token' => $token, 'decision' => 'accept', 'signer_name' => 'Erika Muster', 'signature' => self::PNG,
        ])->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $signature = $quote->decision_snapshot['signature'];
        $this->assertSame('Erika Muster', $signature['name']);
        $attachment = $quote->attachments()->findOrFail($signature['attachment_id']);
        $this->assertSame('image/png', $attachment->mime);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($user)->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee(__('quotes.viewed', ['date' => $viewedAt->fdatetime()]))
            ->assertSee(__('quotes.signature.signed_by', ['name' => 'Erika Muster']));
    }

    public function test_acceptance_without_signature_stays_possible(): void {
        [$quote, $token] = $this->sentQuote();

        $this->post(route('quotes.portal.decide', ['quote' => $quote->getRouteKey()]), ['token' => $token, 'decision' => 'accept'])->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $this->assertNull($quote->decision_snapshot['signature']);
        $this->assertSame(0, $quote->attachments()->count());
    }
}
