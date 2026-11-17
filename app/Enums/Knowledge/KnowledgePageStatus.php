<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Knowledge;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Stand einer Wissensseite (Feature 191, MVP-1665). Entwurf: eine neue
 * Fassung wartet auf die Freigabe durch die verantwortliche Person; Leser
 * sehen bis dahin die zuletzt freigegebene Fassung. Gültig: die aktuelle
 * Fassung ist freigegeben. Veraltet: von Hand markiert oder das
 * Gültigkeitsdatum ist abgelaufen.
 */
enum KnowledgePageStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Valid = 'valid';
    case Outdated = 'outdated';

    public function label(): string {
        return (string) __('enums.knowledge.page_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Valid => 'success',
            self::Outdated => 'warning',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            // Freigabe der Fassung.
            self::Draft => [self::Valid],
            // Neue Fassung (Bearbeiten) oder veraltet (von Hand, Gültigkeit abgelaufen).
            self::Valid => [self::Draft, self::Outdated],
            // Überarbeiten oder erneut bestätigen („Stimmt, aktuell“ mit neuer Gültigkeit).
            self::Outdated => [self::Draft, self::Valid],
        };
    }
}
