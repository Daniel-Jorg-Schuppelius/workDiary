<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormConformityStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Konformitätsstatus eines Normprofils je Geltungsbereich (Feature 046):
 * notAssessed → gapAnalysisDone → inProgress → internallyAuditReady →
 * externalAuditPlanned → certified (→ suspended/expired, Wiederaufnahme
 * und Re-Zertifizierung siehe allowedTransitions()).
 *
 * STRIKTE 046-Regel: Der Wechsel auf `certified` ist NUR mit einem
 * hinterlegten, heute gültigen Zertifikat zulässig — zentral erzwungen im
 * {@see \App\Services\Isms\ConformityService}. Ein Reifegrad oder eine
 * vollständige Checkliste löst NIE automatisch `certified` aus.
 */
enum NormConformityStatus: string implements HasLabel {
    use HasOptions;

    case NotAssessed = 'notAssessed';
    case GapAnalysisDone = 'gapAnalysisDone';
    case InProgress = 'inProgress';
    case InternallyAuditReady = 'internallyAuditReady';
    case ExternalAuditPlanned = 'externalAuditPlanned';
    case Certified = 'certified';
    case CertificateSuspended = 'certificateSuspended';
    case CertificateExpired = 'certificateExpired';

    public function label(): string {
        return (string) __('enums.isms.norm-conformity-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Certified => 'success',
            self::CertificateSuspended,
            self::CertificateExpired => 'error',
            self::InternallyAuditReady,
            self::ExternalAuditPlanned => 'info',
            self::InProgress => 'warning',
            self::NotAssessed,
            self::GapAnalysisDone => 'ghost',
        };
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im ConformityService):
     * Vorwärtskette bis certified; certified → ausgesetzt/abgelaufen;
     * ausgesetzt → certified (Wiederaufnahme, erneute Zertifikatsprüfung!)
     * oder abgelaufen; abgelaufen → externes Audit (Re-Zertifizierung);
     * Rücksprung nach inProgress von gapAnalysisDone/internallyAuditReady/
     * externalAuditPlanned.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::NotAssessed => [self::GapAnalysisDone],
            self::GapAnalysisDone => [self::InProgress],
            self::InProgress => [self::InternallyAuditReady],
            self::InternallyAuditReady => [self::ExternalAuditPlanned, self::InProgress],
            self::ExternalAuditPlanned => [self::Certified, self::InProgress],
            self::Certified => [self::CertificateSuspended, self::CertificateExpired],
            self::CertificateSuspended => [self::Certified, self::CertificateExpired],
            self::CertificateExpired => [self::ExternalAuditPlanned],
        };
    }
}
