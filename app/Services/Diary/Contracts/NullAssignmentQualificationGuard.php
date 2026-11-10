<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullAssignmentQualificationGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Contracts;

use App\Models\Diary\DiaryEntry;
use Carbon\CarbonInterface;

final class NullAssignmentQualificationGuard implements AssignmentQualificationGuard {
    public function assertEntryAssignable(DiaryEntry $entry, int $userId, ?CarbonInterface $date = null, string $field = 'assigned_user_id'): void {}

    public function entryHint(DiaryEntry $entry, int $userId, ?CarbonInterface $date = null): ?string {
        return null;
    }
}
