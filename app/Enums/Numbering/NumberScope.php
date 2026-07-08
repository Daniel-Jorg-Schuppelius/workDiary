<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Numbering;

enum NumberScope: string {
    case ServiceTicket = 'service_ticket';
    case Asset = 'asset';
    case Article = 'article';
    case ManufacturingOrder = 'manufacturing_order';
    case Serial = 'serial';
    case PurchaseOrder = 'purchase_order';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';
    case Quote = 'quote';
    case Proforma = 'proforma';

    public function label(): string {
        return match ($this) {
            self::ServiceTicket => __('Service-Ticket'),
            self::Asset => __('Asset'),
            self::Article => __('Artikel'),
            self::ManufacturingOrder => __('Fertigungsauftrag'),
            self::Serial => __('Seriennummer'),
            self::PurchaseOrder => __('Bestellung'),
            self::Customer => __('Kunde'),
            self::Supplier => __('Lieferant'),
            self::Invoice => __('Rechnung'),
            self::CreditNote => __('Gutschrift'),
            self::Cancellation => __('Stornorechnung'),
            self::Quote => __('Angebot'),
            self::Proforma => __('Pro-forma-Rechnung'),
        };
    }

    /**
     * Buchhaltungsrelevante Nummernkreise, deren Hoheit an ein externes
     * Buchhaltungssystem (z. B. Lexoffice) delegiert werden kann.
     */
    public function isAccountingRelevant(): bool {
        return match ($this) {
            self::Customer, self::Supplier, self::Invoice, self::CreditNote, self::Cancellation => true,
            self::Quote, self::Proforma => false, // keine steuerliche Belegwirkung
            self::ServiceTicket, self::Asset, self::Article, self::ManufacturingOrder, self::Serial, self::PurchaseOrder => false,
        };
    }
}
