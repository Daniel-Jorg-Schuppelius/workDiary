<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Diary\Contracts\AssignmentQualificationGuard;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Zuständigkeit eines Auftrags ändern (Feature 202, MVP-1648): Mehrfachauswahl
 * „Zuständigkeit ändern“ der Auftragsliste und endgültige Übergabe. Wie im
 * Bearbeiten-Dialog gilt die Qualifikationssperre (MVP-1514) zum geplanten
 * Beginn; ein gesperrter Auftrag bleibt bei der bisherigen Person. Der Wechsel
 * steht im Audit des Auftrags.
 */
final class OrderAssignment {
    public function __construct(private readonly AssignmentQualificationGuard $qualifications) {}

    /** Neue zuständige Person setzen; false, wenn die Qualifikationssperre greift oder die Person schon zuständig ist. */
    public function reassign(DiaryEntry $entry, User $assignee): bool {
        if ((int) $entry->assigned_user_id === (int) $assignee->id || (int) $entry->organization_id !== (int) $assignee->organization_id) {
            return false;
        }
        try {
            $this->qualifications->assertEntryAssignable($entry, (int) $assignee->id, $entry->start_at !== null ? CarbonImmutable::instance($entry->start_at) : null);
        } catch (ValidationException) {
            return false;
        }
        $entry->update(['assigned_user_id' => $assignee->id]);

        return true;
    }
}
