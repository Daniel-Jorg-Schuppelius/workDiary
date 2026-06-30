<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\{DataSubjectRequestStatus, DataSubjectRequestType};
use App\Models\{Organization, User};
use App\Models\Privacy\DataSubjectRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workflow der Betroffenenanfragen: Anlegen (per-Fall-Krypto), Identitaetspruefung,
 * Zuweisung, Entscheidung und Crypto-Shredding nach Aufbewahrung. Jeder Schritt
 * schreibt ein Ereignis in die Hash-Kette ({@see PrivacyEventService}).
 */
class DataSubjectRequestService {
    public function __construct(private readonly PrivacyEventService $events) {}

    /** Neue Anfrage anlegen: verschluesselt Identitaet/Anliegen, setzt die Frist (Art. 12). */
    public function open(
        Organization $organization,
        DataSubjectRequestType $type,
        string $subject,
        string $content,
        ?string $channel = null,
        ?User $actor = null,
    ): DataSubjectRequest {
        return DB::transaction(function () use ($organization, $type, $subject, $content, $channel, $actor): DataSubjectRequest {
            $now = Carbon::now();
            $days = (int) config('dataprotection.dsr_deadline_days', 30);

            $dsr = new DataSubjectRequest;
            $dsr->organization_id = $organization->id;
            $dsr->request_number = $this->nextNumber($organization, $now);
            $dsr->type = $type;
            $dsr->status = DataSubjectRequestStatus::Intake;
            $dsr->channel = $channel;
            $dsr->received_at = $now;
            $dsr->deadline_at = $now->copy()->addDays($days);
            $dsr->setAttribute('created_by', $actor?->id);
            $dsr->initializeDek();
            $dsr->subject_ciphertext = $subject;
            $dsr->content_ciphertext = $content;
            $dsr->save();

            $this->events->record($dsr, 'opened', $actor, ['type' => $type->value, 'channel' => $channel]);

            return $dsr;
        });
    }

    public function verifyIdentity(DataSubjectRequest $request, ?User $actor = null): DataSubjectRequest {
        $request->forceFill([
            'identity_verified_at' => Carbon::now(),
            'status' => DataSubjectRequestStatus::InProgress,
        ])->save();
        $this->events->record($request, 'identity_verified', $actor);

        return $request;
    }

    public function assign(DataSubjectRequest $request, User $assignee, ?User $actor = null): DataSubjectRequest {
        $request->forceFill(['assigned_user_id' => $assignee->id])->save();
        $this->events->record($request, 'assigned', $actor, ['assignee_id' => $assignee->id]);

        return $request;
    }

    /** Entscheidung dokumentieren und Fall abschliessen. */
    public function decide(
        DataSubjectRequest $request,
        string $decision,
        string $note,
        ?User $actor = null,
    ): DataSubjectRequest {
        $status = $decision === 'rejected'
            ? DataSubjectRequestStatus::Rejected
            : DataSubjectRequestStatus::Completed;

        $now = Carbon::now();
        $request->decision = $decision;
        $request->decision_note_ciphertext = $note; // verschluesselt via Cast
        $request->forceFill([
            'status' => $status,
            'decided_at' => $now,
            'closed_at' => $now,
        ]);
        $request->save();
        $this->events->record($request, 'decided', $actor, ['decision' => $decision]);

        return $request;
    }

    /** Crypto-Shredding nach Ablauf der Aufbewahrung – Inhalte unwiederbringlich. */
    public function shred(DataSubjectRequest $request, ?User $actor = null): DataSubjectRequest {
        $request->shredDek();
        $this->events->record($request, 'shredded', $actor);

        return $request;
    }

    private function nextNumber(Organization $organization, Carbon $now): string {
        $year = $now->year;
        $count = DataSubjectRequest::query()
            ->where('organization_id', $organization->id)
            ->whereYear('received_at', $year)
            ->count();

        return sprintf('DSR-%d-%04d', $year, $count + 1);
    }
}
