<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierDocumentsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{PurchaseOrder, Supplier};
use Illuminate\Database\Eloquent\Model;

/** Belegverknüpfungen des Lieferanten — Zähler + Zeitraum. */
class SupplierDocumentsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'documents';
    }

    public function title(): string {
        return __('Belege (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, Supplier::class);
        /** @var Supplier $s */
        $s = $subject;

        return ['families' => [
            $this->family(
                'purchase_orders',
                __('Bestellungen'),
                PurchaseOrder::query()->withoutGlobalScopes()
                    ->where('organization_id', (int) $s->organization_id)
                    ->where('supplier_id', $s->id),
                'created_at',
            ),
        ]];
    }
}
