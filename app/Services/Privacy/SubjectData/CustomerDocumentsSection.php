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

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleSubscription};
use App\Models\Sales\Quote;
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
                columns: ['issued_on' => __('Belegdatum'), 'number' => __('Nummer'), 'status' => __('Status'), 'total' => __('Betrag'), 'currency' => __('Währung')],
            ),
            $this->family(
                'quotes',
                __('Angebote'),
                Quote::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'created_at',
                columns: ['created_at' => __('Datum'), 'number' => __('Nummer'), 'status' => __('Status'), 'total' => __('Betrag')],
            ),
            // Lizenzverkäufe (MVP-1024): nur Zuordnung und Datum, nie Schlüssel.
            $this->family(
                'resale_license_assignments',
                __('resale.license.dsar.family'),
                ResaleLicenseAssignment::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'sold_on',
                columns: ['sold_on' => __('resale.license.field.sold_on'), 'invoice_reference' => __('resale.license.field.invoice_reference'), 'ended_at' => __('resale.license.field.ended')],
            ),
            // Abos des Kunden und der Endkunden, die er als Partner hält: Produkt, Menge, Laufzeit.
            $this->family(
                'resale_subscriptions',
                __('resale.dsar.subscriptions'),
                ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $orgId)
                    ->where(static fn($q) => $q->where('customer_id', $c->id)
                        ->orWhereIn('foreign_customer_id', ForeignCustomer::query()->withoutGlobalScopes()->where('customer_id', $c->id)->select('id'))),
                'starts_on',
                columns: ['starts_on' => __('resale.field.starts_on'), 'label' => __('resale.field.label'), 'quantity' => __('resale.field.quantity'), 'status' => __('Status'), 'ends_on' => __('resale.field.ends_on')],
            ),
            // Kundeneingänge aus dem Portal (Feature 162): Nummer, Betreff, Stand.
            $this->family(
                'customer_intakes',
                __('customer_intake.title'),
                \App\Models\Customer\CustomerIntake::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('customer_id', $c->id),
                'created_at',
                columns: ['created_at' => __('Datum'), 'number' => __('Nummer'), 'subject' => __('customer_intake.field.subject'), 'status' => __('Status')],
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
