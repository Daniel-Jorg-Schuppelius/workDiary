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
 * Dokumentarten des Rendervertrags (MVP-295). Jede Art entspricht einem
 * bestehenden PDF-Generator; das Designprofil wird pro Art zugewiesen.
 * Fachinhalt und Pflichtangaben bleiben im jeweiligen Modul — hier wird nur
 * definiert, welche Informationsblöcke eine Art mindestens benötigt.
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
        };
    }

    /**
     * Pflichtblöcke je Dokumentart: müssen `dynamic` oder nachweislich
     * `provided_by_letterhead` sein, sonst blockiert der Preflight (MVP-298).
     *
     * @return array<int, InformationBlock>
     */
    public function mandatoryBlocks(): array {
        return match ($this) {
            self::Invoice => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::TaxIdentity,
                InformationBlock::ItemsTable,
                InformationBlock::Totals,
                InformationBlock::TaxBreakdown,
            ],
            self::PurchaseOrder => [
                InformationBlock::RecipientAddress,
                InformationBlock::DocumentMeta,
                InformationBlock::CompanyIdentity,
                InformationBlock::ItemsTable,
                InformationBlock::Totals,
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
            self::Timesheet, self::Form, self::Report => [
                InformationBlock::DocumentMeta,
            ],
        };
    }
}
