<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Whistleblowing;

/**
 * Rolle einer zugewiesenen Person an einem konkreten Fall (nicht zu verwechseln
 * mit der globalen spatie-Rolle).
 */
enum CaseRole: string {
    case Owner = 'owner';
    case Processor = 'processor';
    case Reviewer = 'reviewer';
}
