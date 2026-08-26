<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HrDocumentCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Hr;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Document\DocumentType;

/**
 * Kategorie eines Personalakten-Dokuments (Feature 141, MVP-708).
 *
 * BEWUSST OHNE Gesundheitskategorie: Vorsorge-Nachweise bleiben im
 * Arbeitsschutz-Register (Feature 132, ohne Diagnosen); Lohnabrechnungen
 * selbst liegen nicht in der Akte — `PayrollReference` sind nur
 * Verweisdokumente (z. B. Lohnsteuer-Bescheinigung, Pfändungsverfügung).
 *
 * Die Fristen in {@see retentionYearsAfterExit()} sind Voreinstellungen
 * (Anker: Austritt) und keine Rechtsberatung — sie orientieren sich an der
 * Regelverjährung (§ 195 BGB, 3 J.) bzw. am Lohnsteuer-/Handelsrechts-Bezug
 * (§ 41 EStG / § 257 HGB, 6 J.) und an der Praxis für Abmahnungen (2 J.).
 */
enum HrDocumentCategory: string implements HasLabel {
    use HasOptions;

    case Contract = 'contract';
    case Amendment = 'amendment';
    case Certificate = 'certificate';
    case Training = 'training';
    case Warning = 'warning';
    case IdDocument = 'idDocument';
    case PayrollReference = 'payrollReference';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.hr_document_category.' . $this->value);
    }

    /** Aufbewahrung in Jahren ab Austritt (users.left_at). */
    public function retentionYearsAfterExit(): int {
        return match ($this) {
            self::PayrollReference => 6,
            self::Warning => 2,
            self::Contract, self::Amendment, self::Certificate,
            self::Training, self::IdDocument, self::Other => 3,
        };
    }

    /** Dokumenttyp des DMS für Listen/Filter (die Akte bleibt HR-kategorisiert). */
    public function documentType(): DocumentType {
        return match ($this) {
            self::Contract, self::Amendment => DocumentType::Contract,
            self::Certificate, self::Training => DocumentType::Certificate,
            default => DocumentType::Other,
        };
    }

    /** Material-Symbols-Icon für Listen. */
    public function icon(): string {
        return match ($this) {
            self::Contract => 'history_edu',
            self::Amendment => 'edit_document',
            self::Certificate => 'workspace_premium',
            self::Training => 'school',
            self::Warning => 'gavel',
            self::IdDocument => 'badge',
            self::PayrollReference => 'payments',
            self::Other => 'draft',
        };
    }
}
