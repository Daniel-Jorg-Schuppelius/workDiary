<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubParticipationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Wer die Teilnahme erfasst hat (MVP-843): Verwaltung, Gruppenleitung,
 * Vertretung, das Mitglied selbst, spontan durch die Leitung oder per
 * Einladung. Relevant für Nachweis und Fristen.
 */
enum ClubParticipationSource: string implements HasLabel {
    use HasOptions;

    case Admin = 'admin';
    case Leader = 'leader';
    case Guardian = 'guardian';
    case Self = 'self';
    case Spontaneous = 'spontaneous';
    case Invitation = 'invitation';

    public function label(): string {
        return (string) __('enums.club.participation-source.' . $this->value);
    }
}
