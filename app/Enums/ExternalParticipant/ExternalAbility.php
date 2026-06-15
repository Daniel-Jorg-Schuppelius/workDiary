<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalAbility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ExternalParticipant;

/**
 * Begrenzte Rechte eines externen Beteiligten (Feature 033). Die Flags werden
 * je Einladung als abilities-JSON gespeichert und serverseitig STRIKT
 * durchgesetzt: Wer nur `View` hat, darf weder kommentieren noch hochladen
 * noch bestätigen (Public-Controller wirft sonst 403).
 *
 * `View` ist implizit für jeden gültigen Token (Read-Only-Seite); die übrigen
 * Flags sind additive Aktionsrechte.
 */
enum ExternalAbility: string {
    case View = 'view';
    case Comment = 'comment';
    case Upload = 'upload';
    case Confirm = 'confirm';

    public function label(): string {
        return __('external.ability.' . $this->value);
    }

    /** Per-Einladung wählbare (zusätzliche) Aktionsrechte ohne das implizite View. */
    /** @return list<self> */
    public static function selectable(): array {
        return [self::Comment, self::Upload, self::Confirm];
    }

    /** @return list<string> */
    public static function values(): array {
        return array_map(static fn(self $a): string => $a->value, self::cases());
    }
}
