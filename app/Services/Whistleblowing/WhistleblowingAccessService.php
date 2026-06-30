<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingAccessService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\User;
use App\Models\Whistleblowing\{CaseConflict, CaseSubject, EmergencyGrant, WhistleblowingCase};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Interessenkonflikt-Selbstsperre, benannte Betroffene und Notfallfreigabe
 * (Abschnitt 7.4 / 25).
 */
class WhistleblowingAccessService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
    ) {}

    /**
     * Markiert einen internen Benutzer als Betroffenen/Beschuldigten. Die Person
     * wird damit fuer den Fall gesperrt (keine Zuweisung/Zugriff); bestehende
     * Zuweisungen werden widerrufen.
     */
    public function markSubject(WhistleblowingCase $case, User $user, User $addedBy, ?string $note = null): CaseSubject {
        return DB::transaction(function () use ($case, $user, $addedBy, $note): CaseSubject {
            /** @var CaseSubject $subject */
            $subject = CaseSubject::firstOrNew([
                'case_id' => $case->getKey(),
                'user_id' => $user->getKey(),
            ]);

            if (! $subject->exists) {
                $subject->organization_id = $case->getAttribute('organization_id');
                $subject->added_by = $addedBy->getKey();
                $subject->setRelation('case', $case);
                if ($note !== null && trim($note) !== '') {
                    $subject->note_ciphertext = $note;
                }
                $subject->save();
            }

            $this->revokeAssignmentsOf($case, $user);

            $this->events->record($case, WhistleblowingEventService::CASE_SUBJECT_ADDED, $addedBy, [
                'subject_user_id' => (int) $user->getKey(),
            ]);

            return $subject;
        });
    }

    /**
     * Sperrt eine Person fuer einen Fall (Selbstdeklaration). Bestehende aktive
     * Zuweisungen dieser Person werden widerrufen, damit sie keinen Zugriff
     * mehr hat.
     */
    public function declareConflict(WhistleblowingCase $case, User $user, ?string $reason = null): CaseConflict {
        return DB::transaction(function () use ($case, $user, $reason): CaseConflict {
            /** @var CaseConflict $conflict */
            $conflict = CaseConflict::firstOrNew([
                'case_id' => $case->getKey(),
                'user_id' => $user->getKey(),
            ]);

            if (! $conflict->exists) {
                $conflict->organization_id = $case->getAttribute('organization_id');
                $conflict->setRelation('case', $case); // DEK fuer reason-Cast
                $conflict->declared_at = Carbon::now();
                if ($reason !== null && trim($reason) !== '') {
                    $conflict->reason_ciphertext = $reason;
                }
                $conflict->save();
            }

            $this->revokeAssignmentsOf($case, $user);

            $this->events->record($case, WhistleblowingEventService::CASE_CONFLICT_DECLARED, $user, [
                'user_id' => (int) $user->getKey(),
            ]);

            return $conflict;
        });
    }

    /**
     * Erteilt einer NICHT zugewiesenen Person eine zeitlich begrenzte
     * Notfallfreigabe. Der Genehmiger muss eine ANDERE Person sein; ein
     * konfliktbehafteter Beguenstigter ist ausgeschlossen.
     */
    public function grantEmergencyAccess(
        WhistleblowingCase $case,
        User $grantee,
        User $approver,
        string $reason,
        ?int $ttlMinutes = null,
    ): EmergencyGrant {
        if ((int) $approver->getKey() === (int) $grantee->getKey()) {
            throw new RuntimeException('Notfallfreigabe verlangt einen ANDEREN Zweit-Genehmiger.');
        }
        if ((int) $grantee->organization_id !== (int) $case->getAttribute('organization_id')) {
            throw new RuntimeException('Beguenstigter gehoert nicht zur Organisation des Falls.');
        }
        if ($case->isBlockedFor($grantee)) {
            throw new RuntimeException('Beguenstigter ist fuer diesen Fall gesperrt (Interessenkonflikt oder Betroffener).');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Notfallfreigabe verlangt eine Begründung.');
        }

        $ttl = $ttlMinutes ?? (int) config('whistleblowing.emergency_ttl_minutes', 240);
        $now = Carbon::now();

        $grant = new EmergencyGrant;
        $grant->organization_id = $case->getAttribute('organization_id');
        $grant->case_id = $case->getKey();
        $grant->user_id = $grantee->getKey();
        $grant->granted_by = $approver->getKey();
        $grant->setRelation('case', $case); // DEK fuer reason-Cast
        $grant->reason_ciphertext = $reason;
        $grant->granted_at = $now;
        $grant->expires_at = $now->copy()->addMinutes($ttl);
        $grant->save();

        $this->events->record($case, WhistleblowingEventService::EMERGENCY_ACCESS_GRANTED, $approver, [
            'grantee_user_id' => (int) $grantee->getKey(),
            'expires_at' => $grant->expires_at->toIso8601String(),
        ]);

        return $grant;
    }

    /** Widerruft alle aktiven Zuweisungen einer Person an diesem Fall. */
    private function revokeAssignmentsOf(WhistleblowingCase $case, User $user): void {
        foreach ($case->assignments()->where('user_id', $user->getKey())->whereNull('revoked_at')->get() as $assignment) {
            $assignment->forceFill(['revoked_at' => Carbon::now()])->save();
        }
    }
}
