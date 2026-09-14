<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Communication;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum CommunicationNoteType: string implements HasLabel {
    use HasOptions;

    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Videocall = 'videocall';
    case Chat = 'chat';
    case Internal = 'internal';
    case Decision = 'decision';
    case Letter = 'letter';
    case Other = 'other';
    // Feature 154: Alltagsarten der zentralen Schnellerfassung.
    case General = 'general';
    case Production = 'production';

    public function label(): string {
        return (string) __('enums.communication.type.' . $this->value);
    }

    /** Material-Symbols-Icon für Listen/Panels. */
    public function icon(): string {
        return match ($this) {
            self::Call => 'call',
            self::Email => 'mail',
            self::Meeting => 'groups',
            self::Videocall => 'videocam',
            self::Chat => 'chat',
            self::Internal => 'forum',
            self::Decision => 'gavel',
            self::Letter => 'markunread_mailbox',
            self::Other => 'more_horiz',
            self::General => 'sticky_note_2',
            self::Production => 'precision_manufacturing',
        };
    }

    /** Arten ohne Außenkontakt — die Schnellerfassung setzt die Richtung „Intern“. */
    public function isInternalByNature(): bool {
        return in_array($this, [self::General, self::Production, self::Internal], true);
    }

    /**
     * Auswahlreihenfolge der Schnellerfassung: Alltagsarten zuerst.
     *
     * @return list<self>
     */
    public static function quickCaptureOrder(): array {
        $first = [self::General, self::Call, self::Production];

        return [...$first, ...array_values(array_filter(self::cases(), static fn(self $type): bool => ! in_array($type, $first, true)))];
    }
}
