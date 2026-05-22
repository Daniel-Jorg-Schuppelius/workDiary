<?php
/*
 * Created on   : Wed May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Single Source of Truth für Spatie-Permission Role-Slugs.
 * Werte stimmen 1:1 mit den Einträgen in der `roles.name`-Spalte überein.
 */
enum UserRole: string implements HasLabel {
    use HasOptions;

    case Admin = 'admin';
    case Geschaeftsfuehrung = 'geschaeftsfuehrung';
    case Teamleitung = 'teamleitung';
    case Buchhaltung = 'buchhaltung';
    case User = 'user';
    case Aussendienst = 'aussendienst';
    case Callcenter = 'callcenter';
    case Support = 'support';
    case TrainingManager = 'training_manager';

    public function label(): string {
        return (string) __('user.role.' . $this->value);
    }
}
