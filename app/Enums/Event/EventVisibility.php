<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Event;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * - Internal: nur Mitglieder der eigenen Organisation sehen das Event.
 * - External: zusätzlich der verknüpfte externe Verantwortliche
 *   (Customer-User, sofern später ein Portal angebunden wird).
 * - Public: erscheint zusätzlich im gemeinsamen ICS-Feed der eigenen
 *   Organisation ({@see \App\Services\Event\IcsFeedService::feedPublic()}).
 *   NICHT mandantenübergreifend: der Feed lief bis 2026-09-13 anonym über alle
 *   Mandanten, was hier als „jeder eingeloggte User org-übergreifend"
 *   beschrieben war — umgesetzt war davon nur die anonyme Ausgabe.
 */
enum EventVisibility: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';
    case Public = 'public';

    public function label(): string {
        return (string) __('enums.event.visibility.' . $this->value);
    }
}
