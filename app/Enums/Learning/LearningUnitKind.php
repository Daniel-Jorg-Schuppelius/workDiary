<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningUnitKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Lerneinheit (Feature 149). Prozedur, Termin und Umfrage zeigen
 * auf vorhandene Module (026/028/090) — das LMS baut sie nicht nach.
 */
enum LearningUnitKind: string implements HasLabel {
    use HasOptions;

    case Content = 'content';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Procedure = 'procedure';
    case Event = 'event';
    case Scorm = 'scorm';
    case Survey = 'survey';
    case External = 'external';

    public function label(): string {
        return (string) __('enums.learning.unit-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Content => 'ghost',
            self::Quiz => 'warning',
            self::Assignment => 'info',
            self::Procedure, self::Event => 'success',
            self::Scorm, self::Survey, self::External => 'neutral',
        };
    }

    /** Online-Pflicht: nie offline bearbeitbar (Manipulationsschutz). */
    public function requiresOnline(): bool {
        return $this === self::Quiz || $this === self::Assignment;
    }
}
