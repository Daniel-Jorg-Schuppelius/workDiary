<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Knowledge;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Sichtbarkeit eines Wissensartikels (Feature 011).
 *
 * MVP: ausschließlich `internal` (ganze Organisation). `team` ist als
 * Wert vorgesehen, wird aber noch nicht angeboten/ausgewertet — Teams
 * sind m:n an User gebunden (team_user), eine teambezogene Sichtbarkeit
 * bräuchte Artikel↔Team-Bindung plus Scope-/Policy-Anpassung und ist
 * bewusst auf später verschoben (siehe Feature-Doku 011).
 */
enum ArticleVisibility: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case Team = 'team';

    public function label(): string {
        return (string) __('enums.knowledge.visibility.' . $this->value);
    }
}
