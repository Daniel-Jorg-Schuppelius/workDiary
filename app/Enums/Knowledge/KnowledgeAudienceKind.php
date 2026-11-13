<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeAudienceKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Knowledge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Für wen eine Pflichtlektüre gilt bzw. wer einen Bereich lesen darf
 * (Feature 191, MVP-1666/1667): alle internen Mitglieder, eine Rolle, ein
 * Team oder eine Person. „Alle“ gibt es nur für die Pflichtlektüre — ein
 * Bereich ohne Einschränkung braucht keinen Eintrag.
 */
enum KnowledgeAudienceKind: string implements HasLabel {
    use HasOptions;

    case All = 'all';
    case Role = 'role';
    case Team = 'team';
    case User = 'user';

    public function label(): string {
        return (string) __('enums.knowledge.audience_kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::All => 'groups',
            self::Role => 'badge',
            self::Team => 'diversity_3',
            self::User => 'person',
        };
    }
}
