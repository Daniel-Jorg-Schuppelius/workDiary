<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InformationBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fachlich benannte Informationsblöcke (MVP-298). Admins wählen keine
 * Datenbankfelder, sondern deklarieren je Block genau einen Zustand
 * (dynamic / provided_by_letterhead / not_applicable). Dynamische Werte wie
 * Empfänger, Nummern, Beträge oder Seitenbezug dürfen nie als statischer
 * Firmenbogen-Inhalt deklariert werden.
 */
enum InformationBlock: string implements HasLabel {
    use HasOptions;

    case SenderLine = 'sender_line';
    case RecipientAddress = 'recipient_address';
    case DocumentMeta = 'document_meta';
    case ContactPerson = 'contact_person';
    case CompanyIdentity = 'company_identity';
    case TaxIdentity = 'tax_identity';
    case BankDetails = 'bank_details';
    case IntroText = 'intro_text';
    case ItemsTable = 'items_table';
    case Totals = 'totals';
    case TaxBreakdown = 'tax_breakdown';
    case ClosingText = 'closing_text';
    case PageMeta = 'page_meta';
    case Confidentiality = 'confidentiality';

    public function label(): string {
        return match ($this) {
            self::SenderLine => __('enums.document_design.information_block.sender_line'),
            self::RecipientAddress => __('enums.document_design.information_block.recipient_address'),
            self::DocumentMeta => __('enums.document_design.information_block.document_meta'),
            self::ContactPerson => __('enums.document_design.information_block.contact_person'),
            self::CompanyIdentity => __('enums.document_design.information_block.company_identity'),
            self::TaxIdentity => __('enums.document_design.information_block.tax_identity'),
            self::BankDetails => __('enums.document_design.information_block.bank_details'),
            self::IntroText => __('enums.document_design.information_block.intro_text'),
            self::ItemsTable => __('enums.document_design.information_block.items_table'),
            self::Totals => __('enums.document_design.information_block.totals'),
            self::TaxBreakdown => __('enums.document_design.information_block.tax_breakdown'),
            self::ClosingText => __('enums.document_design.information_block.closing_text'),
            self::PageMeta => __('enums.document_design.information_block.page_meta'),
            self::Confidentiality => __('enums.document_design.information_block.confidentiality'),
        };
    }

    /**
     * Blöcke mit veränderlichem Inhalt: `provided_by_letterhead` ist für sie
     * ausgeschlossen — ein Briefbogen kann keine Belegnummern, Beträge oder
     * Seitenzahlen unveränderlich enthalten.
     */
    public function dynamicOnly(): bool {
        return match ($this) {
            self::RecipientAddress,
            self::DocumentMeta,
            self::ItemsTable,
            self::Totals,
            self::TaxBreakdown,
            self::PageMeta => true,
            default => false,
        };
    }

    public function defaultState(): InformationBlockState {
        return $this === self::Confidentiality
            ? InformationBlockState::NotApplicable
            : InformationBlockState::Dynamic;
    }
}
