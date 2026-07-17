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

    public function label(): string {
        return match ($this) {
            self::DiaryRead => (string) __('Aufträge lesen'),
            self::DiaryWrite => (string) __('Aufträge anlegen/ändern'),
            self::TasksRead => (string) __('Aufgaben lesen'),
            self::TasksWrite => (string) __('Aufgaben anlegen/ändern'),
            self::AttendanceRead => (string) __('Anwesenheit lesen'),
            self::AttendanceWrite => (string) __('Anwesenheit stempeln'),
            self::AssetsRead => (string) __('Assets lesen'),
            self::HooksManage => (string) __('Automatisierungs-Hooks verwalten'),
            self::TicketsWrite => (string) __('Tickets anlegen'),
            self::CommentsWrite => (string) __('Kommentare schreiben'),
            self::AttachmentsRead => (string) __('Anhänge herunterladen'),
            self::AttachmentsWrite => (string) __('Anhänge hochladen/löschen'),
            self::TagsRead => (string) __('Tags lesen'),
            self::TagsWrite => (string) __('Tags anlegen/ändern'),
            self::ShiftsRead => (string) __('Bereitschaften lesen'),
            self::AssignmentsRead => (string) __('Einsätze lesen'),
            self::DashboardRead => (string) __('Dashboard lesen'),
            self::PushWrite => (string) __('Push-Abo verwalten'),
            self::TimesheetsRead => (string) __('Stundenzettel lesen'),
            self::TimesheetsWrite => (string) __('Stundenzettel anlegen/ändern'),
            self::MaterialsRead => (string) __('Materialien lesen'),
            self::StopwatchRead => (string) __('Stoppuhr lesen'),
            self::StopwatchWrite => (string) __('Stoppuhr steuern'),
            self::FlexRead => (string) __('Arbeitszeitkonto lesen'),
            self::LocationWrite => (string) __('Standort stempeln'),
            self::CustomersRead => (string) __('Kunden lesen'),
            self::CustomersWrite => (string) __('Kunden anlegen/ändern'),
            self::ProjectsRead => (string) __('Projekte lesen'),
            self::ProjectsWrite => (string) __('Projekte anlegen/ändern'),
        };
    }
}
