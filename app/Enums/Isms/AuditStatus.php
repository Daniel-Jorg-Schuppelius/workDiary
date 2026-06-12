<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Audits (Feature 046, Inkrement C): planned → inPreparation
 * → inProgress → reportIssued → closed; Rücksprung reportIssued →
 * inProgress erlaubt (Berichtskorrektur/Nacherhebung). Übergänge laufen
 * AUSSCHLIESSLICH über {@see \App\Services\Isms\AuditService::transitionAudit()}
 * — reportIssued erfordert dort Durchführungszeitraum + Zusammenfassung.
 */
enum AuditStatus: string implements HasLabel {
    use HasOptions;

    case Planned = 'planned';
    case InPreparation = 'inPreparation';
    case InProgress = 'inProgress';
    case ReportIssued = 'reportIssued';
    case Closed = 'closed';

    public function label(): string {
        return (string) __('enums.isms.audit-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Planned => 'ghost',
            self::InPreparation => 'info',
            self::InProgress => 'warning',
            self::ReportIssued => 'primary',
            self::Closed => 'success',
        };
    }

    /**
     * Erlaubte Folge-Status (State-Machine, validiert im AuditService).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Planned => [self::InPreparation],
            self::InPreparation => [self::InProgress],
            self::InProgress => [self::ReportIssued],
            self::ReportIssued => [self::Closed, self::InProgress],
            self::Closed => [],
        };
    }

    /** Dürfen in diesem Status Feststellungen erfasst werden? */
    public function allowsFindings(): bool {
        return in_array($this, [self::InProgress, self::ReportIssued], true);
    }
}
