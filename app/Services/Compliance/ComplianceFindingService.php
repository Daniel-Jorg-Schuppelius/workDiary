<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Models\{ComplianceFinding, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Acknowledge-Workflow für persistierte Compliance-Verstöße (Feature 006,
 * Welle D) — Vorbild {@see \App\Services\ServiceTicket\SlaViolationService}
 * (SLA-Verletzung quittieren) und {@see \App\Services\Isms\CorrectiveActionService}
 * (Pflicht-Notiz bei Wirksamkeitsentscheid).
 *
 * Geschäftsregeln:
 *  - quittierbar nur `open`/`acknowledged` (resolved ist auto-terminal,
 *    accepted eine finale Entscheidung);
 *  - `accepted` (bewusst akzeptiert) erfordert eine Pflicht-Begründung;
 *  - der Statuswechsel wird über die Audit-Hash-Kette geloggt
 *    (compliance.finding.acknowledged / .accepted).
 */
class ComplianceFindingService {
    /**
     * @throws ValidationException
     */
    public function acknowledge(
        ComplianceFinding $finding,
        ComplianceFindingStatus $target,
        User $actor,
        ?string $note = null,
    ): ComplianceFinding {
        if (! in_array($target, [ComplianceFindingStatus::Acknowledged, ComplianceFindingStatus::Accepted], true)) {
            throw ValidationException::withMessages([
                'status' => __('compliance.history.error.invalid_status'),
            ]);
        }

        if (! $finding->status->isAcknowledgeable()) {
            throw ValidationException::withMessages([
                'status' => __('compliance.history.error.not_acknowledgeable'),
            ]);
        }

        $note = $note !== null ? trim($note) : null;
        if ($target === ComplianceFindingStatus::Accepted && ($note === null || $note === '')) {
            throw ValidationException::withMessages([
                'acknowledge_note' => __('compliance.history.error.note_required'),
            ]);
        }

        return DB::transaction(function () use ($finding, $target, $actor, $note): ComplianceFinding {
            $from = $finding->status;

            $finding->status = $target;
            $finding->acknowledged_by = (int) $actor->getKey();
            $finding->acknowledged_at = Carbon::now();
            $finding->acknowledge_note = $note !== '' ? $note : null;
            $finding->save();

            $event = $target === ComplianceFindingStatus::Accepted
                ? 'compliance.finding.accepted'
                : 'compliance.finding.acknowledged';

            $finding->audit($event, [
                'actor_user_id' => (int) $actor->getKey(),
                'from' => $from->value,
                'to' => $target->value,
                'note' => $finding->acknowledge_note,
            ]);

            return $finding;
        });
    }
}
