<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingRequirementSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Training;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zielgruppe einer Pflichtzuordnung (Feature 145): Rolle (UserRole-Slug)
 * oder Tätigkeitsbereich = Arbeits-Team. Beide lösen sich beim Abgleich in
 * konkrete Mitarbeitende auf; mehr Dimensionen (Skill-Level, Kompetenz-
 * matrix) bleiben bewusst außen vor.
 */
enum TrainingRequirementSubject: string implements HasLabel {
    use HasOptions;

    case Role = 'role';
    case Team = 'team';

    public function label(): string {
        return (string) __('enums.training.requirement-subject.' . $this->value);
    }
}
