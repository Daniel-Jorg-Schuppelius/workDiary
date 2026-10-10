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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum NumberScope: string implements HasLabel {
    use HasOptions;

    case ServiceTicket = 'service_ticket';
    case ProblemReport = 'problem_report';
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
    case Claim = 'claim';
    case Rma = 'rma';
    case Rental = 'rental';
    case AssetFinance = 'asset_finance';
    case Contract = 'contract';
    case PrivacyIncident = 'privacy_incident';
    case DataSubjectRequest = 'data_subject_request';
    case Disposal = 'disposal';
    // Zertifikate der Lernplattform (Feature 149, MVP-740): je
    // Organisation lückenlos, weil ein Auftraggeber sie prüfen können muss.
    case Certificate = 'certificate';
    // Schadensfälle und Rückrufaktionen (MVP-919/921).
    case Damage = 'damage';
    case Recall = 'recall';
    // Kundeneingang aus dem Portal (MVP-1074): Vorgangsnummer der Eingangsbestätigung.
    case CustomerIntake = 'customer_intake';

    public function label(): string {
        return match ($this) {
            self::ServiceTicket => __('enums.numbering.number_scope.service_ticket'),
            self::ProblemReport => __('enums.numbering.number_scope.problem_report'),
            self::Asset => __('enums.numbering.number_scope.asset'),
            self::Article => __('enums.numbering.number_scope.article'),
            self::ManufacturingOrder => __('enums.numbering.number_scope.manufacturing_order'),
            self::Serial => __('enums.numbering.number_scope.serial'),
            self::PurchaseOrder => __('enums.numbering.number_scope.purchase_order'),
            self::Customer => __('enums.numbering.number_scope.customer'),
            self::Supplier => __('enums.numbering.number_scope.supplier'),
            self::Invoice => __('enums.numbering.number_scope.invoice'),
            self::CreditNote => __('enums.numbering.number_scope.credit_note'),
            self::Cancellation => __('enums.numbering.number_scope.cancellation'),
            self::Quote => __('enums.numbering.number_scope.quote'),
            self::Proforma => __('enums.numbering.number_scope.proforma'),
            self::Claim => __('enums.numbering.number_scope.claim'),
            self::Rma => __('enums.numbering.number_scope.rma'),
            self::Rental => __('enums.numbering.number_scope.rental'),
            self::AssetFinance => __('enums.numbering.number_scope.asset_finance'),
            self::Contract => __('enums.numbering.number_scope.contract'),
            self::PrivacyIncident => __('enums.numbering.number_scope.privacy_incident'),
            self::DataSubjectRequest => __('enums.numbering.number_scope.data_subject_request'),
            self::Disposal => __('enums.numbering.number_scope.disposal'),
            self::Certificate => __('enums.numbering.number_scope.certificate'),
            self::Damage => __('enums.numbering.number_scope.damage'),
            self::Recall => __('enums.numbering.number_scope.recall'),
            self::CustomerIntake => __('customer_intake.title_single'),
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
            self::ServiceTicket, self::Asset, self::Article, self::ManufacturingOrder, self::Serial, self::PurchaseOrder, self::ProblemReport => false,
            self::Claim, self::Rma => false, // Fallakten/Logistik, keine Belegwirkung
            self::Rental, self::AssetFinance => false, // Fallakten, keine Belegwirkung
            self::Contract => false, // Vertragsakte, keine Belegwirkung
            self::PrivacyIncident, self::DataSubjectRequest => false, // Datenschutz-Fallakten, keine Belegwirkung
            self::Disposal => false, // Entsorgungs-Fallakte, keine Belegwirkung
            self::Certificate => false, // Lernnachweis, keine Belegwirkung
            self::Damage, self::Recall => false, // Fallakten, keine Belegwirkung
            self::CustomerIntake => false, // Eingangsakte, keine Belegwirkung
        };
    }
}
