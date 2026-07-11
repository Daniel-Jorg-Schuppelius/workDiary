<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/** RMA-/Rückläuferstatus (MVP-250). */
enum ClaimRmaStatus: string {
    case Announced = 'announced';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case Completed = 'completed';

    public function label(): string {
        return match ($this) {
            self::Announced => (string) __('Angekündigt'),
            self::Received => (string) __('Wareneingang erfasst'),
            self::Inspecting => (string) __('In Prüfung'),
            self::Completed => (string) __('Abgeschlossen'),
        };
    }
}
