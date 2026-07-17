<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeColor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ideas;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Semantische Farbpalette der Knoten (Feature 054, MVP-105/106). Bewusst
 * begrenzt; Farbe ist nie einziger Informationsträger (038) — der Editor
 * zeigt Status zusätzlich als Text/Icon.
 */
enum IdeaNodeColor: string implements HasLabel {
    use HasOptions;

    case Default = 'default';
    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
    case Info = 'info';

    public function label(): string {
        return __('ideas.color.' . $this->value);
    }
}
