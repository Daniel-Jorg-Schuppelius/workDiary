<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessorRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Rolle eines Vertragspartners im Datenschutz-Verhältnis. */
enum ProcessorRole: string {
    case Controller = 'controller';
    case JointController = 'joint_controller';
    case Processor = 'processor';
    case Subprocessor = 'subprocessor';

    public function label(): string {
        return match ($this) {
            self::Controller => __('Verantwortlicher'),
            self::JointController => __('Gemeinsam Verantwortlicher'),
            self::Processor => __('Auftragsverarbeiter'),
            self::Subprocessor => __('Unterauftragsverarbeiter'),
        };
    }
}
