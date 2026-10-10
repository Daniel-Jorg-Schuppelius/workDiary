<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Status der Entsorgungsakte (Feature 100, MVP-474):
 * angelegt → abgeholt → in Behandlung → an Entsorger übergeben → abgeschlossen.
 * Die Behandlung ist überspringbar (Vorgänge ohne Datenträger); der Abschluss
 * wird zusätzlich fachlich bewacht (DisposalJobService::assertCompletable).
 */
enum DisposalJobStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Draft = 'draft';
    case Collected = 'collected';
    case InTreatment = 'in_treatment';
    case HandedOver = 'handed_over';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('enums.disposal.disposal_job_status.draft'),
            self::Collected => (string) __('enums.disposal.disposal_job_status.collected'),
            self::InTreatment => (string) __('enums.disposal.disposal_job_status.in_treatment'),
            self::HandedOver => (string) __('enums.disposal.disposal_job_status.handed_over'),
            self::Completed => (string) __('enums.disposal.disposal_job_status.completed'),
            self::Cancelled => (string) __('enums.disposal.disposal_job_status.cancelled'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'neutral',
            self::Collected => 'info',
            self::InTreatment => 'warning',
            self::HandedOver => 'secondary',
            self::Completed => 'success',
            self::Cancelled => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Collected, self::Cancelled],
            self::Collected => [self::InTreatment, self::HandedOver, self::Cancelled],
            self::InTreatment => [self::HandedOver, self::Cancelled],
            self::HandedOver => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isOpen(): bool {
        return !in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Positionen/Behandlungen sind nur vor der Entsorger-Übergabe änderbar. */
    public function isEditable(): bool {
        return in_array($this, [self::Draft, self::Collected, self::InTreatment], true);
    }
}
