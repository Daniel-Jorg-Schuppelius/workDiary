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
enum ApiAbility: string {
    case DiaryRead = 'diary:read';
    case DiaryWrite = 'diary:write';
    case TasksRead = 'tasks:read';
    case TasksWrite = 'tasks:write';
    case AttendanceRead = 'attendance:read';
    case AttendanceWrite = 'attendance:write';
    case AssetsRead = 'assets:read';
    case HooksManage = 'hooks:manage';
    case TicketsWrite = 'tickets:write';

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
        };
    }

    /**
     * Alle gültigen Ability-Werte (für Validierung/UI).
     *
     * @return list<string>
     */
    public static function values(): array {
        return array_map(static fn (self $a): string => $a->value, self::cases());
    }
}
