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
    case Teams = 'teams';
    case Customers = 'customers';
    case Projects = 'projects';
    case TimeEntries = 'time-entries';
    case Timesheets = 'timesheets';
    case Invoicing = 'invoicing';
    case Finance = 'finance';
    case Diary = 'diary';
    case Scheduling = 'scheduling';
    case Absences = 'absences';
    case Fleet = 'fleet';
    case Reports = 'reports';
    case WorkingTime = 'working-time';
    case MasterData = 'master-data';
    case OpenIssues = 'open-issues';
    case Communication = 'communication';
    case Documents = 'documents';
    case Knowledge = 'knowledge';
    case Ideas = 'ideas';
    case Isms = 'isms';
    case Forms = 'forms';
    case Protocols = 'protocols';
    case Procedures = 'procedures';
    case Safety = 'safety';
    case CustomerPortal = 'customer-portal';
    case Applications = 'applications';
    case Crisis = 'crisis';
    case Sustainability = 'sustainability';
    case Claims = 'claims';
    case Domains = 'domains';
    case Rental = 'rental';
    case Disposal = 'disposal';
    case AssetFinance = 'asset-finance';
    case AssetCompliance = 'asset-compliance';
    case Contracts = 'contracts';
    case Platform = 'platform';

    public function label(): string {
        return (string) __('access.group.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Access => 'admin_panel_settings',
            self::Organization => 'corporate_fare',
            self::Members => 'group',
            self::Teams => 'groups',
            self::Customers => 'badge',
            self::Projects => 'folder_special',
            self::TimeEntries => 'schedule',
            self::Timesheets => 'fact_check',
            self::Invoicing => 'receipt_long',
            self::Finance => 'account_balance',
            self::Diary => 'menu_book',
            self::Scheduling => 'event_note',
            self::Absences => 'beach_access',
            self::Fleet => 'local_shipping',
            self::Reports => 'analytics',
            self::WorkingTime => 'punch_clock',
            self::MasterData => 'category',
            self::OpenIssues => 'task_alt',
            self::Communication => 'forum',
            self::Documents => 'folder_open',
            self::Knowledge => 'school',
            self::Ideas => 'emoji_objects',
            self::Isms => 'verified_user',
            self::Forms => 'assignment',
            self::Protocols => 'description',
            self::Procedures => 'rule',
            self::Safety => 'health_and_safety',
            self::CustomerPortal => 'support_agent',
            self::Applications => 'work_history',
            self::Crisis => 'emergency_home',
            self::Sustainability => 'eco',
            self::Claims => 'assignment_return',
            self::Domains => 'dns',
            self::Rental => 'forklift',
            self::Disposal => 'recycling',
            self::AssetFinance => 'request_quote',
            self::AssetCompliance => 'rule_settings',
            self::Contracts => 'contract',
            self::Platform => 'memory',
        };
    }
}
