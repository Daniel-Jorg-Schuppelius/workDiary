<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ideas;

/**
 * Sichtbarkeit einer Ideenlandkarte (Feature 054, MVP-104). `private` ist
 * Default; `shared` ist ABGELEITET: gesetzt, solange mindestens eine aktive
 * Freigabe existiert (MVP-107). Org-Zugehörigkeit allein gewährt nie
 * Inhaltszugriff.
 */
enum IdeaMapVisibility: string {
    case Private = 'private';
    case Shared = 'shared';

    public function label(): string {
        return __('ideas.visibility.' . $this->value);
    }
}
