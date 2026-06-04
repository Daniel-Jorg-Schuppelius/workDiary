<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmploymentType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Beschäftigungsart eines Mitarbeiters. Werte sind stabil (DB), Labels über
 * `user.employment_type.<value>` übersetzt.
 */
enum EmploymentType: string implements HasLabel {
    use HasOptions;

    case Vollzeit = 'vollzeit';
    case Teilzeit = 'teilzeit';
    case Minijob = 'minijob';
    case Midijob = 'midijob';
    case Kurzfristig = 'kurzfristig';
    case Werkstudent = 'werkstudent';
    case Azubi = 'azubi';

    public function label(): string {
        return (string) __('user.employment_type.' . $this->value);
    }
}
