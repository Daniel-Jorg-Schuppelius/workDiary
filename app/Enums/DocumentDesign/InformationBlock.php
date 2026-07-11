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

/**
 * Fachlich benannte Informationsblöcke (MVP-298). Admins wählen keine
 * Datenbankfelder, sondern deklarieren je Block genau einen Zustand
 * (dynamic / provided_by_letterhead / not_applicable). Dynamische Werte wie
 * Empfänger, Nummern, Beträge oder Seitenbezug dürfen nie als statischer
 * Firmenbogen-Inhalt deklariert werden.
 */
enum InformationBlock: string {
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
            self::SenderLine => __('Absenderzeile'),
            self::RecipientAddress => __('Empfängeranschrift'),
            self::DocumentMeta => __('Dokumenttitel, Nummer, Datum & Referenzen'),
            self::ContactPerson => __('Ansprechpartner & Kontaktdaten'),
            self::CompanyIdentity => __('Unternehmensanschrift, Rechtsform & Register'),
            self::TaxIdentity => __('Steuer-/Umsatzsteuerangaben'),
            self::BankDetails => __('Bankverbindung & Zahlungsinformationen'),
            self::IntroText => __('Einleitungstext'),
            self::ItemsTable => __('Positionstabelle'),
            self::Totals => __('Summenbereich'),
            self::TaxBreakdown => __('Steueraufschlüsselung'),
            self::ClosingText => __('Schlusstext'),
            self::PageMeta => __('Seitenzahl & Dokumentkennung'),
            self::Confidentiality => __('Vertraulichkeitskennzeichnung'),
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
