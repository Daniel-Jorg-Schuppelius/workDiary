<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Migration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Finance\{BillingMode, TransferTarget};

/**
 * Buchhaltungssystem als Quelle oder Ziel eines Wechsels (MVP-653).
 *
 * Kapselt alles Richtungsabhängige an EINER Stelle: unter welchem
 * `external_type` das jeweilige Plugin seine Fremd-IDs ablegt, welche
 * Fakturahoheit und welches Übergabeziel dazugehören und welche Datenbereiche
 * sich abgleichen lassen. Dadurch ist der Wechsel zwischen je zwei Systemen in
 * beide Richtungen möglich. Ein neues System braucht einen Case und eine
 * {@see \App\Services\AccountingMigration\Contracts\MigrationSource}, die sein
 * Plugin in {@see \App\Services\AccountingMigration\MigrationSources} einträgt
 * (sevDesk und easybill seit MVP-1506 über den gemeinsamen Belegspiegel).
 */
enum MigrationProvider: string implements HasLabel {
    use HasOptions;

    case Lexoffice = 'lexoffice';
    case OrgaMax = 'orgamax';
    case SevDesk = 'sevdesk';
    case Easybill = 'easybill';

    public function label(): string {
        return match ($this) {
            self::Lexoffice => 'Lexoffice',
            self::OrgaMax => __('enums.migration.migration_provider.orga_max'),
            self::SevDesk => 'sevDesk',
            self::Easybill => 'easybill',
        };
    }

    /**
     * `external_type` der {@see \App\Models\Integration\ExternalReference} dieses
     * Systems je Datenbereich — die Plugins legen ihre Fremd-IDs
     * unterschiedlich ab (Lexoffice führt Kunden und Lieferanten gemeinsam
     * als „contact").
     */
    public function externalTypeFor(MigrationDataArea $area): string {
        return match ($this) {
            self::Lexoffice => match ($area) {
                MigrationDataArea::Customers, MigrationDataArea::Suppliers => 'contact',
                MigrationDataArea::Articles => 'article',
                MigrationDataArea::Documents => 'voucher',
            },
            self::OrgaMax => match ($area) {
                MigrationDataArea::Customers => 'customer',
                MigrationDataArea::Suppliers => 'supplier',
                MigrationDataArea::Articles => 'article',
                MigrationDataArea::Documents => 'orgamax_invoice',
            },
            // Kunden und Lieferanten sind dort Kontakte; Artikel verknüpft workDiary nicht ({@see supportsArea()}).
            self::SevDesk, self::Easybill => match ($area) {
                MigrationDataArea::Customers, MigrationDataArea::Suppliers => 'contact',
                MigrationDataArea::Articles => 'article',
                MigrationDataArea::Documents => $this->value . '_invoice',
            },
        };
    }

    /**
     * Lässt sich der Datenbereich mit diesem System abgleichen? sevDesk und
     * easybill spiegeln ihren Artikelstamm nur (`accounting_articles`), ohne
     * Fremd-ID am lokalen Artikel — ein Abgleich hätte keine Grundlage.
     */
    public function supportsArea(MigrationDataArea $area): bool {
        return match ($this) {
            self::Lexoffice, self::OrgaMax => true,
            self::SevDesk, self::Easybill => $area !== MigrationDataArea::Articles,
        };
    }

    /** Fakturahoheit, die nach der Umschaltung auf dieses System gilt. */
    public function billingMode(): BillingMode {
        return match ($this) {
            self::Lexoffice => BillingMode::Lexoffice,
            self::OrgaMax => BillingMode::OrgaMax,
            self::SevDesk => BillingMode::SevDesk,
            self::Easybill => BillingMode::Easybill,
        };
    }

    /** Übergabeziel, das dieses System bedient (nach dem Stichtag gesperrt, wenn Quelle). */
    public function transferTarget(): TransferTarget {
        return match ($this) {
            self::Lexoffice => TransferTarget::Lexoffice,
            self::OrgaMax => TransferTarget::OrgaMax,
            self::SevDesk => TransferTarget::SevDesk,
            self::Easybill => TransferTarget::Easybill,
        };
    }

    /**
     * Jedes System führt einen lokalen Belegspiegel — Lexoffice
     * `lexoffice_vouchers`, orgaMAX `orgamax_invoices`, sevDesk und easybill
     * `accounting_vouchers`. Dadurch bleibt die Beleghistorie nach dem Wechsel
     * unabhängig von der API lesbar.
     */
    public function hasVoucherMirror(): bool {
        return true;
    }

    /**
     * Belegstatus, die als „ausgeglichen/abgeschlossen" gelten — offene
     * Altbelege blockieren bis dahin den Abschluss des Wechsels.
     *
     * @return list<string>
     */
    public function settledDocumentStates(): array {
        return match ($this) {
            self::Lexoffice => ['paid', 'voided', 'draft'],
            // sevDesk und easybill: normalisierter Zustand des Spiegels (AccountingVoucherState).
            self::OrgaMax, self::SevDesk, self::Easybill => ['paid', 'cancelled', 'draft'],
        };
    }
}
