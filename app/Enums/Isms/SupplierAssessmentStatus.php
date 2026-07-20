<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAssessmentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Bearbeitungsstatus einer Lieferantenbewertung (Feature 044, MVP 2/3):
 * draft (Entwurf) → assessed (bewertet) → approved (freigegeben) bzw.
 * flagged (auffällig, Nachsteuerung nötig). State-Machine im
 * SupplierAssessmentService. „flagged" und „assessed" zählen als ungeprüft/
 * nicht freigegeben und gehen — bei fälliger Review — in die
 * Auditbereitschafts-Kennzahl „ungeprüfte Lieferanten" ein.
 */
enum SupplierAssessmentStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Assessed = 'assessed';
    case Approved = 'approved';
    case Flagged = 'flagged';

    public function label(): string {
        return (string) __('enums.isms.supplier-assessment-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Assessed => 'info',
            self::Approved => 'success',
            self::Flagged => 'error',
        };
    }

    /**
     * Gilt der Lieferant als geprüft UND freigegeben? Nur „approved" zählt
     * als abschließend geprüft; alles andere ist offen (Dashboard-Kennzahl
     * „ungeprüfte Lieferanten").
     */
    public function isApproved(): bool {
        return $this === self::Approved;
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im
     * SupplierAssessmentService): draft → assessed|flagged; assessed →
     * approved|flagged; approved → flagged|assessed (Neubewertung/Rückzug);
     * flagged → assessed|approved (nach Nachsteuerung).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Assessed, self::Flagged],
            self::Assessed => [self::Approved, self::Flagged],
            self::Approved => [self::Flagged, self::Assessed],
            self::Flagged => [self::Assessed, self::Approved],
        };
    }
}
