<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrderIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\B2bCatalog;

use App\Models\Article;
use App\Models\B2b\{B2bCatalogAccess, B2bOrder};
use App\Models\{Customer, DiaryEntry};
use App\Services\B2bCatalog\{B2bOrderGroupBooker, B2bOrderIntakeService};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\{Order, OrderLine, Party};
use ERechnungToolkit\Enums\UnitCode;
use ERechnungToolkit\Generators\OpenTransOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-458 — openTRANS-2.1-ORDER-Auftragseingang: Inbox-First-Spiegelung,
 * Idempotenz (ORDER-ID + Käufer), Positionszuordnung über die
 * Punchout-Artikelnummer und Buchung → Auftrag (DiaryEntry). Das Test-XML
 * entsteht per Round-Trip über den Toolkit-Generator (v0.4.4, Entscheidung E2).
 */
class B2bOrderIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function orderXml(string $orderId = 'PO-2026-0815', string $buyerVat = 'DE123456789'): string {
        $currency = CurrencyCode::Euro;
        $order = new Order(
            id: $orderId,
            issueDate: new \DateTimeImmutable('2026-07-29'),
            buyer: new Party(name: 'ACME Industrie GmbH', vatId: $buyerVat),
            seller: new Party(name: 'workDiary Lieferant'),
            currency: $currency,
        );
        $order->addLine(new OrderLine(
            id: '1',
            quantity: 3.0,
            unitCode: UnitCode::PIECE,
            netAmount: Money::of('52.50', $currency),
            itemName: 'Katalogartikel',
            unitPrice: Money::of('17.50', $currency),
            sellersItemId: 'WD-1001',
        ));
        $order->addLine(new OrderLine(
            id: '2',
            quantity: 1.0,
            unitCode: UnitCode::PIECE,
            netAmount: Money::of('9.99', $currency),
            itemName: 'Unbekannte Position',
            unitPrice: Money::of('9.99', $currency),
            sellersItemId: 'GIBT-ES-NICHT',
        ));

        return (new OpenTransOrderGenerator)->generateOrder($order);
    }

    private function accessForCustomer(string $vat = 'DE123456789'): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'vat_id' => $vat]);
        B2bCatalogAccess::issue((int) $this->organization->id, (int) $customer->id, 'ACME', 'einkauf-acme');

        return $customer;
    }

    public function test_intake_mirrors_order_inbox_first_and_matches_articles_and_customer(): void {
        $customer = $this->accessForCustomer();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'WD-1001']);

        $result = app(B2bOrderIntakeService::class)->intake($this->organization, $this->orderXml(), B2bOrder::SOURCE_UPLOAD);

        $this->assertSame('created', $result['status']);
        $order = $result['order'];
        $this->assertSame(B2bOrder::STATUS_OPEN, $order->status);
        $this->assertSame('PO-2026-0815', $order->external_order_id);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertNull($order->diary_entry_id);

        $lines = $order->lines;
        $this->assertCount(2, $lines);
        $this->assertSame($article->id, $lines[0]['article_id']);
        $this->assertNull($lines[1]['article_id']);
        $this->assertSame('62.49', $order->total_net?->getAmount());

        // Kein Blind-Import: kein Auftrag vor der Buchung.
        $this->assertSame(0, DiaryEntry::query()->count());

        // Die Bestellung erscheint als Inbox-Gruppe der Drehscheibe.
        $groups = app(B2bOrderGroupBooker::class)->groups($this->organization);
        $this->assertCount(1, $groups);
        $this->assertSame('b2b_order', $groups[0]['form']);
        $this->assertSame('PO-2026-0815', $groups[0]['order_id']);
        $this->assertSame(1, $groups[0]['unmatched']);
    }

    public function test_intake_is_idempotent_per_order_id_and_buyer(): void {
        $this->accessForCustomer();
        $service = app(B2bOrderIntakeService::class);

        $first = $service->intake($this->organization, $this->orderXml(), B2bOrder::SOURCE_UPLOAD);
        $second = $service->intake($this->organization, $this->orderXml(), B2bOrder::SOURCE_MAIL);

        $this->assertSame('created', $first['status']);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame(1, B2bOrder::query()->count());

        // Gleiche ORDER-ID eines ANDEREN Käufers ist keine Dublette.
        $other = $service->intake($this->organization, $this->orderXml(buyerVat: 'DE999999999'), B2bOrder::SOURCE_UPLOAD);
        $this->assertSame('created', $other['status']);
        $this->assertSame(2, B2bOrder::query()->count());
    }

    public function test_non_opentrans_content_throws(): void {
        $this->expectException(\RuntimeException::class);

        app(B2bOrderIntakeService::class)->intake($this->organization, '<invoice>kein openTRANS</invoice>', B2bOrder::SOURCE_UPLOAD);
    }

    public function test_booking_creates_diary_entry_once_and_dismiss_closes(): void {
        $customer = $this->accessForCustomer();
        Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'WD-1001']);
        $service = app(B2bOrderIntakeService::class);
        $actor = $this->orgAdmin();

        $order = $service->intake($this->organization, $this->orderXml(), B2bOrder::SOURCE_UPLOAD)['order'];

        $entry = $service->book($order, $customer, $actor);
        $order->refresh();

        $this->assertSame(B2bOrder::STATUS_BOOKED, $order->status);
        $this->assertSame($entry->id, $order->diary_entry_id);
        $this->assertSame($customer->id, $entry->customer_id);
        $this->assertStringContainsString('PO-2026-0815', (string) $entry->title);
        $this->assertStringContainsString('WD-1001', (string) $entry->content);

        // Idempotent: zweite Buchung erzeugt keinen zweiten Auftrag.
        $again = $service->book($order->refresh(), $customer, $actor);
        $this->assertSame($entry->id, $again->id);
        $this->assertSame(1, DiaryEntry::query()->count());

        // Verwerfen einer weiteren offenen Bestellung.
        $other = $service->intake($this->organization, $this->orderXml('PO-2026-0816'), B2bOrder::SOURCE_UPLOAD)['order'];
        $service->dismiss($other);
        $this->assertSame(B2bOrder::STATUS_DISMISSED, $other->refresh()->status);
        $this->assertSame(1, DiaryEntry::query()->count());
    }

    public function test_mail_channel_routes_opentrans_before_einvoice_pipeline(): void {
        $admin = $this->orgAdmin();
        $this->accessForCustomer();

        $connection = \App\Models\EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestellungen',
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'bestellung@example.test',
            'password' => 'geheim',
            'folder' => 'INBOX',
            'active' => true,
            'einvoice_intake' => true,
            'created_by' => $admin->id,
        ]);

        $message = new \App\Services\Mail\ParsedMessage(
            messageId: '<order-1@example.test>',
            uid: 4711,
            fromEmail: 'einkauf@acme.test',
            fromName: 'ACME Einkauf',
            subject: 'Bestellung PO-2026-0815',
            body: 'Anbei unsere Bestellung.',
            receivedAt: \Illuminate\Support\Carbon::now(),
            attachmentCount: 1,
            attachments: [new \App\Services\Mail\MailAttachment('order.xml', 'application/xml', $this->orderXml())],
        );

        $result = app(\App\Services\Mail\MailIntakeService::class)->intake($this->organization, $connection, $message);

        $this->assertSame('b2b_order', $result);
        $this->assertSame(1, B2bOrder::query()->where('source', B2bOrder::SOURCE_MAIL)->count());

        // Wiederanlieferung derselben Bestellung: Dublette, kein zweiter Spiegel.
        $again = app(\App\Services\Mail\MailIntakeService::class)->intake($this->organization, $connection, $message);
        $this->assertSame('skipped', $again);
        $this->assertSame(1, B2bOrder::query()->count());
    }

    public function test_admin_upload_channel_requires_module(): void {
        $admin = $this->orgAdmin();

        config(['license.feature_overrides' => ['module.b2b_katalog' => false]]);
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();

        $this->actingAs($admin)->get('/admin/b2b-katalog')->assertStatus(423);
    }

    public function test_admin_upload_channel_creates_open_order(): void {
        $admin = $this->orgAdmin();
        $this->accessForCustomer();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('order.xml', $this->orderXml());

        $this->actingAs($admin)
            ->post('/admin/b2b-katalog/bestellungen/upload', ['order_file' => $file])
            ->assertRedirect();

        $this->assertSame(1, B2bOrder::query()->where('source', B2bOrder::SOURCE_UPLOAD)->count());
    }
}
