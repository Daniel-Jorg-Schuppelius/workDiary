<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Bewerbungsakte (Feature 068, MVP-190/191). Ohne
 * Übergangstabelle: innerhalb der Pipeline ist jeder Wechsel frei. Eine
 * Entscheidung ist endgültig — Bearbeitungsstände, Gespräche und Entscheiden
 * setzen die Pipeline voraus; danach bleibt nur die Anonymisierung. Einzige
 * Ausnahme: aus dem Talentpool führt {@see \App\Services\Applications\RecruitingService::readmit()}
 * mit gültiger Einwilligung zurück an den Pipeline-Anfang.
 */
enum JobApplicationStatus: string implements HasLabel {
    use HasOptions;

    case Received = 'received';
    case Screened = 'screened';
    case InterviewPlanned = 'interview_planned';
    case Interviewed = 'interviewed';
    case TaskOpen = 'task_open';
    case Offer = 'offer';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case TalentPool = 'talent_pool';
    case Deleted = 'deleted';

    /**
     * Aktive Pipeline-Status (vor der Entscheidung).
     *
     * @return list<self>
     */
    public static function pipeline(): array {
        return [self::Received, self::Screened, self::InterviewPlanned, self::Interviewed, self::TaskOpen, self::Offer];
    }

    /**
     * Bearbeitungsstände, die sich an der Akte von Hand setzen lassen.
     *
     * @return list<self>
     */
    public static function working(): array {
        return [self::Screened, self::InterviewPlanned, self::Interviewed, self::TaskOpen];
    }

    public function inPipeline(): bool {
        return in_array($this, self::pipeline(), true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Screened => 'info',
            self::InterviewPlanned, self::Interviewed => 'primary',
            self::TaskOpen => 'warning',
            self::Offer => 'accent',
            self::Accepted => 'success',
            self::Rejected => 'error',
            self::TalentPool => 'secondary',
            self::Withdrawn, self::Deleted => 'neutral',
            self::Received => 'ghost',
        };
    }
}
