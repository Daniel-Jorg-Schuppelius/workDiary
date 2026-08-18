<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderDocumentKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Dokumentarten des Rendervertrags (MVP-295; Ausbau Issue #83): die zentrale,
 * typisierte Registrierung aller serverseitig erzeugten PDF-Arten. Jede Art
 * deklariert Bezeichnung, Dokumentfamilie, Seitenformat, Pflichtblöcke und
 * ihre Design-Fähigkeit; {@see \App\Services\DocumentDesign\PdfGeneratorInventory}
 * bindet die Generator-Aufrufstellen an diese Registrierung (Architekturtest).
 * Fachinhalt und Pflichtangaben bleiben im jeweiligen Modul.
 *
 * Nicht verwechseln mit {@see \App\Enums\Billing\DocumentKind}: das ist der
 * fachliche Belegfluss (Vorzeichen/Nummernkreis), hier geht es ums Rendering.
 */
enum RenderDocumentKind: string implements HasLabel {
    use HasOptions;

    case Invoice = 'invoice';
    case PurchaseOrder = 'purchase_order';
    case Protocol = 'protocol';
    case DeliveryNote = 'delivery_note';
    case ManufacturingRecord = 'manufacturing_record';
    case Timesheet = 'timesheet';
    case Form = 'form';
    case Report = 'report';

    // Ausbau Issue #83: eigenständige Vertriebsbeleg-Arten (gemeinsame
    // Designverträge über die Familie, eigene Pflichtblöcke) …
    case Quote = 'quote';
    case OrderConfirmation = 'order_confirmation';
    case CreditNote = 'credit_note';
    case ProformaInvoice = 'proforma_invoice';
    case Dunning = 'dunning';
    // … sowie bislang unregistrierte Nachweis- und Spezialarten.
    case CaseFile = 'case_file';
    case Label = 'label';

    public function label(): string {
        return match ($this) {
            self::Invoice => __('Rechnung'),
            self::PurchaseOrder => __('Bestellung'),
            self::Protocol => __('Protokoll'),
            self::DeliveryNote => __('Lieferschein'),
            self::ManufacturingRecord => __('Fertigungsnachweis'),
            self::Timesheet => __('Stundenzettel'),
            self::Form => __('Formular'),
            self::Report => __('Bericht'),
            self::Quote => __('Angebot'),
            self::OrderConfirmation => __('Auftragsbestätigung'),
            self::CreditNote => __('Gutschrift'),
            self::ProformaInvoice => __('Pro-forma-Rechnung'),
            self::Dunning => __('Mahnung'),
            self::CaseFile => __('Fallakte'),
            self::Label => __('Etikett'),
        };
    }

    /** Dokumentfamilie — Design-Varianten können je Familie gelten (#83). */
    public function family(): RenderDocumentFamily {
        return match ($this) {
            self::Invoice, self::Quote, self::OrderConfirmation,
            self::CreditNote, self::ProformaInvoice, self::Dunning => RenderDocumentFamily::Sales,
            self::PurchaseOrder, self::DeliveryNote => RenderDocumentFamily::Procurement,
            self::Protocol, self::ManufacturingRecord, self::Timesheet,
            self::Form, self::Report, self::CaseFile => RenderDocumentFamily::Evidence,
            self::Label => RenderDocumentFamily::Special,
        };
    }

    /**
     * Seitenformat der Art. Nur `a4_portrait` durchläuft die volle
     * Design-Pipeline (Firmenbogen/Druckbereiche); `flexible` deklariert ein
     * Spezialformat (frei wählbares Papier/Querformat).
     */
    public function pageFormat(): string {
        return match ($this) {
            self::Label => 'flexible',
            default => 'a4_portrait',
        };
    }

    /**
     * Brandfähig = erbt CI-Basisdesign/Varianten. Spezialformate deklarieren
     * ihre Einschränkung hier ausdrücklich statt die Pipeline unbemerkt zu
     * umgehen ({@see capabilityNote()}).
     */
    public function isBrandable(): bool {
        return $this !== self::Label;
    }

    /** Begründung eingeschränkter Design-Fähigkeit (nur Spezialformate). */
    public function capabilityNote(): ?string {
        return match ($this) {
            self::Label => (string) __('Freies Etikettenformat (A7/Querformat, ohne Organisation) — Firmenbogen und Druckbereiche sind nicht anwendbar.'),
            default => null,
        };
    }

    /**
     * Auflösungs-Fallback auf die etablierte Art derselben Familie: solange
     * eine Organisation keine eigene Variante für z. B. Gutschriften pflegt,
     * gilt weiterhin ihr Rechnungs- bzw. Berichtsprofil (kein sichtbarer
     * Regressionssprung durch die feineren Arten, #83).
     */
    public function fallbackKind(): ?self {
        return match ($this) {
            self::Quote, self::OrderConfirmation, self::CreditNote,
            self::ProformaInvoice, self::Dunning => self::Invoice,
            self::CaseFile => self::Report,
            default => null,
        };
    }

    /**
     * Render-Art eines Rechnungsbelegs nach {@see \App\Models\Invoice}-Typ:
     * Gutschrift und Pro-forma tragen eigene Arten, alle übrigen Typen
     * (Storno, Abschlag, Teil-/Schlussrechnung, Retainer) bleiben `invoice`.
     */
    public static function forInvoiceType(string $type): self {
        return match ($type) {
            \App\Models\Invoice::TYPE_CREDIT_NOTE => self::CreditNote,
            \App\Models\Invoice::TYPE_PROFORMA => self::ProformaInvoice,
            default => self::Invoice,
        };
    }

    /**
     * Alle brandfähigen Arten — Prüfumfang eines CI-Basisdesigns (dessen
     * Pflichtblöcke müssen für JEDE erbende Art erfüllbar sein).
     *
     * @return array<int, self>
     */
    public static function brandable(): array {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => $kind->isBrandable()));
    }

    /**
     * Pflichtblöcke je Dokumentart: müssen `dynamic` oder nachweislich
     * `provided_by_letterhead` sein, sonst blockiert der Preflight (MVP-298).
     *
     * @return array<int, InformationBlock>
     */
    public function mandatoryBlocks(): array {
        return match ($this) {
            self::Invoice, self::CreditNote, self::ProformaInvoice => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::TaxIdentity,
                InformationBlock::ItemsTable,
                InformationBlock::Totals,
                InformationBlock::TaxBreakdown,
            ],
            self::PurchaseOrder, self::Quote, self::OrderConfirmation => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::ItemsTable,
                InformationBlock::Totals,
            ],
            self::Dunning => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::Totals,
                InformationBlock::BankDetails,
            ],
            self::DeliveryNote => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::ItemsTable,
            ],
            self::Protocol, self::ManufacturingRecord => [
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
            ],
            self::Timesheet, self::Form, self::Report, self::CaseFile => [
                InformationBlock::DocumentMeta,
            ],
            self::Label => [],
        };
    }
}
