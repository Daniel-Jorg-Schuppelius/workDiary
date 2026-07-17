<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControllerRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Verantwortungsrolle bei einer Verarbeitung (DSGVO Art. 4/26/28). */
enum ControllerRole: string implements HasLabel {
    use HasOptions;

    case Controller = 'controller';            // Verantwortlicher
    case JointController = 'joint_controller'; // gemeinsam Verantwortlicher (Art. 26)
    case Processor = 'processor';              // Auftragsverarbeiter (Art. 28)

    public function label(): string {
        return match ($this) {
            self::Controller => __('Verantwortlicher'),
            self::JointController => __('Gemeinsam Verantwortlicher'),
            self::Processor => __('Auftragsverarbeiter'),
        };
    }
}
