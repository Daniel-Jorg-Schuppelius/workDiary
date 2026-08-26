<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDocumentsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{Customer, Invoice, Quote, User};
use Illuminate\Database\Eloquent\Model;

/** Beleg- und Kontoverknüpfungen des Kunden — Zähler + Zeitraum je Familie. */
class CustomerDocumentsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'documents';
    }

    public function title(): string {
        return __('Belege & Konten (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, Customer::class);
        /** @var Customer $c */
        $c = $subject;
        $orgId = (int) $c->organization_id;

        return ['families' => [
            $this->family(
                'invoices',
                __('Rechnungen'),
                Invoice::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'created_at',
            ),
            $this->family(
                'quotes',
                __('Angebote'),
                Quote::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'created_at',
            ),
            $this->family(
                'portal_users',
                __('Portal-Konten'),
                User::query()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'created_at',
            ),
        ]];
    }
}
