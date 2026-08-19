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
 * Fakturahoheit und welches Übergabeziel dazugehören und woher die
 * Beleghistorie kommt. Dadurch ist der Wechsel in beide Richtungen möglich
 * (Lexoffice → orgaMAX und orgaMAX → Lexoffice); neue Systeme brauchen nur
 * einen weiteren Case.
 */
enum MigrationProvider: string implements HasLabel {
    use HasOptions;

    case Lexoffice = 'lexoffice';
    case OrgaMax = 'orgamax';

    public function label(): string {
        return match ($this) {
            self::Lexoffice => 'Lexoffice',
            self::OrgaMax => __('orgaMAX Buchhaltung'),
        };
    }

    /**
     * `external_type` der {@see \App\Models\ExternalReference} dieses
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
        };
    }

    /** Fakturahoheit, die nach der Umschaltung auf dieses System gilt. */
    public function billingMode(): BillingMode {
        return match ($this) {
            self::Lexoffice => BillingMode::Lexoffice,
            self::OrgaMax => BillingMode::OrgaMax,
        };
    }

    /** Übergabeziel, das dieses System bedient (nach dem Stichtag gesperrt, wenn Quelle). */
    public function transferTarget(): TransferTarget {
        return match ($this) {
            self::Lexoffice => TransferTarget::Lexoffice,
            self::OrgaMax => TransferTarget::OrgaMax,
        };
    }

    /**
     * Beide Systeme führen einen lokalen Belegspiegel — Lexoffice
     * `lexoffice_vouchers`, orgaMAX `orgamax_invoices`. Dadurch bleibt die
     * Beleghistorie nach dem Wechsel unabhängig von der API lesbar.
     */
    public function hasVoucherMirror(): bool {
        return true;
    }

    /**
     * Belegstatus, die als „ausgeglichen/abgeschlossen" gelten — offene
     * Altbelege blockieren bis dahin den Abschluss des Wechsels.
     *
     * @return array<int, string>
     */
    public function settledDocumentStates(): array {
        return match ($this) {
            self::Lexoffice => ['paid', 'voided', 'draft'],
            self::OrgaMax => ['paid', 'cancelled', 'draft'],
        };
    }
}
