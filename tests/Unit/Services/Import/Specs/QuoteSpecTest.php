<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Models\{Article, Customer, Organization, Quote};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\QuoteSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class QuoteSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1', 'name' => 'Kunde']);
    }

    /** @return array<string, string> */
    private function row(array $overrides = []): array {
        return array_merge([
            'number' => 'A-2024-7',
            'customer_number' => 'K-1',
            'status' => 'Sent',
            'valid_until' => '30.06.2024',
            'position' => '1',
            'description' => 'Beratung',
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '100,00',
            'tax_rate' => '19',
        ], $overrides);
    }

    public function test_normalize_casts_position_amounts_and_status(): void {
        $row = (new QuoteSpec())->normalize($this->row(['optional' => 'ja']));

        $this->assertSame(1, $row['position']);
        $this->assertSame('100.00', $row['unit_price']);
        $this->assertSame('sent', $row['status']);
        $this->assertTrue($row['optional']);
        $this->assertNull($row['version']);
    }

    public function test_validate_row_rejects_unknown_status_customer_and_article(): void {
        $spec = new QuoteSpec();

        $issues = $spec->validateRow($spec->normalize($this->row([
            'status' => 'partially_accepted',
            'customer_number' => 'K-9',
            'article_number' => 'SKU-X',
            'valid_until' => 'irgendwann',
        ])), $this->organization);

        $fields = array_map(static fn($i) => $i->field, $issues);
        $this->assertContains('status', $fields);
        $this->assertContains('customer_number', $fields);
        $this->assertContains('article_number', $fields);
        $this->assertContains('valid_until', $fields);
    }

    public function test_upsert_builds_head_and_positions_across_rows_and_stays_idempotent(): void {
        $spec = new QuoteSpec();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'SKU-1']);

        [$o1] = $spec->upsert($spec->normalize($this->row()), $this->organization);
        [$o2] = $spec->upsert($spec->normalize($this->row([
            'position' => '2', 'description' => 'Lizenz', 'quantity' => '1', 'unit_price' => '50', 'article_number' => 'SKU-1',
        ])), $this->organization);
        $this->assertSame(ImportOutcome::Created, $o1);
        $this->assertSame(ImportOutcome::Updated, $o2);

        $quote = Quote::query()->where('number', 'A-2024-7')->firstOrFail();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('2024-06-30', $quote->valid_until?->toDateString());
        $this->assertSame(2, $quote->items()->count());
        $this->assertSame((int) $article->id, (int) $quote->items()->where('position', 2)->value('article_id'));
        $this->assertSame('250.00', $quote->subtotal?->getAmount());
        $this->assertSame('297.50', $quote->total?->getAmount());
        $this->assertNull($quote->acceptance_token_hash);

        // Wiederholimport derselben Zeile: keine Dublette, Position aktualisiert.
        [$o3] = $spec->upsert($spec->normalize($this->row(['unit_price' => '120'])), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $o3);
        $this->assertSame(1, Quote::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(2, $quote->items()->count());
        $this->assertSame('290.00', $quote->fresh()?->subtotal?->getAmount());
    }

    public function test_article_lookup_is_tenant_scoped(): void {
        $other = Organization::factory()->create();
        Article::factory()->create(['organization_id' => $other->id, 'number' => 'SKU-F']);
        $spec = new QuoteSpec();

        [$outcome, $issue] = $spec->upsert($spec->normalize($this->row(['article_number' => 'SKU-F'])), $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(ImportErrorCode::FkMissing, $issue?->code);
        $this->assertSame(0, Quote::query()->count());
    }
}
