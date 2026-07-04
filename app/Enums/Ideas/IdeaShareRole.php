<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaShareRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ideas;

/**
 * Rolle einer Karten-Freigabe (Feature 054, MVP-107): Lesen oder Bearbeiten.
 */
enum IdeaShareRole: string {
    case Viewer = 'viewer';
    case Editor = 'editor';

    public function label(): string {
        return __('ideas.share_role.' . $this->value);
    }
}
