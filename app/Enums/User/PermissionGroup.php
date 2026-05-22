<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Reine UI-Gruppierung für die Permission-Matrix. Hat keinerlei Einfluss
 * auf die Auswertung (`can()`) und ist nicht persistent.
 */
enum PermissionGroup: string implements HasLabel {
    use HasOptions;

    case Access = 'access';
    case Organization = 'organization';
    case Members = 'members';
    case Customers = 'customers';
    case Projects = 'projects';
    case TimeEntries = 'time-entries';
    case Timesheets = 'timesheets';
    case Invoicing = 'invoicing';
    case Diary = 'diary';
    case Scheduling = 'scheduling';
    case Absences = 'absences';
    case Fleet = 'fleet';
    case Reports = 'reports';
    case WorkingTime = 'working-time';
    case MasterData = 'master-data';
    case CustomerPortal = 'customer-portal';

    public function label(): string {
        return (string) __('access.group.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Access => 'admin_panel_settings',
            self::Organization => 'corporate_fare',
            self::Members => 'group',
            self::Customers => 'badge',
            self::Projects => 'folder_special',
            self::TimeEntries => 'schedule',
            self::Timesheets => 'fact_check',
            self::Invoicing => 'receipt_long',
            self::Diary => 'menu_book',
            self::Scheduling => 'event_note',
            self::Absences => 'beach_access',
            self::Fleet => 'local_shipping',
            self::Reports => 'analytics',
            self::WorkingTime => 'punch_clock',
            self::MasterData => 'category',
            self::CustomerPortal => 'support_agent',
        };
    }
}
