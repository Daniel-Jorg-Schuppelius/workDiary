<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSupportingDocumentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Invoicing;

use App\Enums\Invoicing\SupportingDocumentKind;
use App\Models\Invoicing\{Invoice, InvoiceSupportingDocument};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceSupportingDocument> */
class InvoiceSupportingDocumentFactory extends Factory {
    protected $model = InvoiceSupportingDocument::class;

    public function definition(): array {
        return [
            'invoice_id' => Invoice::factory(),
            'organization_id' => fn (array $attributes): int => (int) Invoice::query()->withoutGlobalScopes()->whereKey($attributes['invoice_id'])->value('organization_id'),
            'position' => 1,
            'kind' => SupportingDocumentKind::Custom,
            'reference' => 'LN-' . $this->faker->unique()->numberBetween(1, 99999),
            'description' => null,
            'external_url' => null,
            'filename' => 'nachweis-' . $this->faker->unique()->numberBetween(1, 99999) . '.pdf',
            'mime' => 'application/pdf',
            'size' => 0,
            'is_embedded' => true,
        ];
    }

    public function link(string $url = 'https://rechnung.example.org/anlage/1'): static {
        return $this->state(['external_url' => $url, 'filename' => null, 'mime' => null, 'is_embedded' => false]);
    }
}
