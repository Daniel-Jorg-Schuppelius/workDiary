<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiAbility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Api;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fein granulierte Fähigkeiten (Scopes) eines API-Tokens (Feature 008 → Rang 60).
 * Konvention `ressource:aktion`. Der Katalog enthält bewusst NUR Abilities, die
 * über eine Sanctum-`ability:`-Middleware in `routes/api.php` auch tatsächlich
 * erzwungen werden — sonst wären sie irreführend.
 *
 * Bestandstokens haben die Wildcard `*` (Sanctum-Default) und behalten damit
 * Vollzugriff; die Token-UI weist darauf hin, dass ein neu ausgestellter Token
 * gezielt eingeschränkt werden kann.
 */
enum ApiAbility: string implements HasLabel {
    use HasOptions;

    case DiaryRead = 'diary:read';
    case DiaryWrite = 'diary:write';
    case TasksRead = 'tasks:read';
    case TasksWrite = 'tasks:write';
    case AttendanceRead = 'attendance:read';
    case AttendanceWrite = 'attendance:write';
    case AssetsRead = 'assets:read';
    case HooksManage = 'hooks:manage';
    case TicketsWrite = 'tickets:write';
    // Sweep 2026-07-10: bisher ungescopte Familien nachgezogen, damit ein
    // eingeschränkter Token wirklich eingeschränkt ist (nicht nur die 9 oben).
    case CommentsWrite = 'comments:write';
    case AttachmentsRead = 'attachments:read';
    case AttachmentsWrite = 'attachments:write';
    case TagsRead = 'tags:read';
    case TagsWrite = 'tags:write';
    case ShiftsRead = 'shifts:read';
    case AssignmentsRead = 'assignments:read';
    case DashboardRead = 'dashboard:read';
    case PushWrite = 'push:write';
    case TimesheetsRead = 'timesheets:read';
    case TimesheetsWrite = 'timesheets:write';
    case MaterialsRead = 'materials:read';
    case StopwatchRead = 'stopwatch:read';
    case StopwatchWrite = 'stopwatch:write';
    case FlexRead = 'flex:read';
    case LocationWrite = 'location:write';
    case CustomersRead = 'customers:read';
    case CustomersWrite = 'customers:write';
    case ProjectsRead = 'projects:read';
    case ProjectsWrite = 'projects:write';
    // Vollaudit 2026-07 (M3): Kernobjekte Abwesenheiten/Spesen/Rechnungen/
    // Schichtplan — read-first (Feature 008 MVP).
    case AbsencesRead = 'absences:read';
    case ExpensesRead = 'expenses:read';
    case InvoicesRead = 'invoices:read';
    case ScheduledShiftsRead = 'scheduled-shifts:read';
    // MVP-718 (Vollscan J11): Read-only-Resources der Kernentitäten Artikel/
    // Bestände/Bestellungen/Lieferanten/Protokolle/Fahrzeuge.
    case ArticlesRead = 'articles:read';
    case InventoryRead = 'inventory:read';
    case PurchaseOrdersRead = 'purchase_orders:read';
    case SuppliersRead = 'suppliers:read';
    case ProtocolsRead = 'protocols:read';
    case VehiclesRead = 'vehicles:read';
    // Lernplattform (Feature 149, MVP-791): lesen + Selbsteinschreibung.
    case LearningRead = 'learning:read';
    case LearningWrite = 'learning:write';
    // MCP-Server für KI-Assistenten (MVP-1063/1064): lesen bzw. Entwürfe anlegen.
    case McpRead = 'mcp:read';
    case McpWrite = 'mcp:write';

    public function label(): string {
        return match ($this) {
            self::DiaryRead => (string) __('enums.api.api_ability.diary_read'),
            self::DiaryWrite => (string) __('enums.api.api_ability.diary_write'),
            self::TasksRead => (string) __('enums.api.api_ability.tasks_read'),
            self::TasksWrite => (string) __('enums.api.api_ability.tasks_write'),
            self::AttendanceRead => (string) __('enums.api.api_ability.attendance_read'),
            self::AttendanceWrite => (string) __('enums.api.api_ability.attendance_write'),
            self::AssetsRead => (string) __('enums.api.api_ability.assets_read'),
            self::HooksManage => (string) __('enums.api.api_ability.hooks_manage'),
            self::TicketsWrite => (string) __('enums.api.api_ability.tickets_write'),
            self::CommentsWrite => (string) __('enums.api.api_ability.comments_write'),
            self::AttachmentsRead => (string) __('enums.api.api_ability.attachments_read'),
            self::AttachmentsWrite => (string) __('enums.api.api_ability.attachments_write'),
            self::TagsRead => (string) __('enums.api.api_ability.tags_read'),
            self::TagsWrite => (string) __('enums.api.api_ability.tags_write'),
            self::ShiftsRead => (string) __('enums.api.api_ability.shifts_read'),
            self::AssignmentsRead => (string) __('enums.api.api_ability.assignments_read'),
            self::DashboardRead => (string) __('enums.api.api_ability.dashboard_read'),
            self::PushWrite => (string) __('enums.api.api_ability.push_write'),
            self::TimesheetsRead => (string) __('enums.api.api_ability.timesheets_read'),
            self::TimesheetsWrite => (string) __('enums.api.api_ability.timesheets_write'),
            self::MaterialsRead => (string) __('enums.api.api_ability.materials_read'),
            self::StopwatchRead => (string) __('enums.api.api_ability.stopwatch_read'),
            self::StopwatchWrite => (string) __('enums.api.api_ability.stopwatch_write'),
            self::FlexRead => (string) __('enums.api.api_ability.flex_read'),
            self::LocationWrite => (string) __('enums.api.api_ability.location_write'),
            self::CustomersRead => (string) __('enums.api.api_ability.customers_read'),
            self::CustomersWrite => (string) __('enums.api.api_ability.customers_write'),
            self::ProjectsRead => (string) __('enums.api.api_ability.projects_read'),
            self::ProjectsWrite => (string) __('enums.api.api_ability.projects_write'),
            self::AbsencesRead => (string) __('enums.api.api_ability.absences_read'),
            self::ExpensesRead => (string) __('enums.api.api_ability.expenses_read'),
            self::InvoicesRead => (string) __('enums.api.api_ability.invoices_read'),
            self::ScheduledShiftsRead => (string) __('enums.api.api_ability.scheduled_shifts_read'),
            self::ArticlesRead => (string) __('enums.api.api_ability.articles_read'),
            self::InventoryRead => (string) __('enums.api.api_ability.inventory_read'),
            self::PurchaseOrdersRead => (string) __('enums.api.api_ability.purchase_orders_read'),
            self::SuppliersRead => (string) __('enums.api.api_ability.suppliers_read'),
            self::ProtocolsRead => (string) __('enums.api.api_ability.protocols_read'),
            self::VehiclesRead => (string) __('enums.api.api_ability.vehicles_read'),
            self::LearningRead => (string) __('enums.api.api_ability.learning_read'),
            self::LearningWrite => (string) __('enums.api.api_ability.learning_write'),
            self::McpRead => (string) __('enums.api.api_ability.mcp_read'),
            self::McpWrite => (string) __('enums.api.api_ability.mcp_write'),
        };
    }
}
