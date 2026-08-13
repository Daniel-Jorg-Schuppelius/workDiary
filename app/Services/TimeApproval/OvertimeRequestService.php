<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename    : OvertimeRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Enums\Notification\NotificationEvent;
use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Models\{ComplianceFinding, OvertimeRequest, User};
use App\Services\Approval\ApprovalFlowService;
use App\Services\Compliance\AttendancePlausibilityScanService;
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Überstunden-Antrag (MVP-519): Einreichen, Entscheiden, Zurückziehen.
 *
 * Die Genehmigung ist bewusst Governance-Schicht: die Zeitkonten rechnen
 * unverändert (FlexCalculator); der genehmigte Antrag dokumentiert die
 * betriebliche Veranlassung, benachrichtigt den Antragsteller und quittiert
 * den passenden offenen Plausibilitäts-Befund („Rahmenzeit überschritten")
 * desselben Tages automatisch.
 */
class OvertimeRequestService {
    /**
     * Antrag einreichen (entsteht direkt im Status submitted).
     */
    public function submit(User $owner, User $actor, CarbonImmutable $scopeDate, int $minutes, string $reason): OvertimeRequest {
        // Doppelanträge für denselben Tag vermeiden (nur offene zählen).
        $open = OvertimeRequest::query()
            ->where('user_id', $owner->getKey())
            ->whereDate('scope_date', $scopeDate->toDateString())
            ->where('status', OvertimeRequestStatus::Submitted->value)
            ->exists();
        if ($open) {
            throw ValidationException::withMessages([
                'scope_date' => __('Für diesen Tag liegt bereits ein offener Überstunden-Antrag vor.'),
            ]);
        }

        $request = OvertimeRequest::query()->create([
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->getKey(),
            'requested_by_user_id' => $actor->getKey(),
            'scope_date' => $scopeDate->toDateString(),
            'minutes' => $minutes,
            'reason' => $reason,
            'status' => OvertimeRequestStatus::Submitted->value,
        ]);

        app(NotificationDispatcher::class)->notify(
            NotificationEvent::OvertimeRequested,
            $request,
            $owner,
            [
                'title' => (string) __('notification.message.overtime_requested_title', [
                    'user' => (string) ($owner->name ?? '–'),
                    'date' => $request->scope_date->format('d.m.Y'),
                    'minutes' => $minutes,
                ]),
                'title_key' => 'notification.message.overtime_requested_title',
                'title_params' => [
                    'user' => (string) ($owner->name ?? '–'),
                    'date' => $request->scope_date->toDateString(),
                    'minutes' => (string) $minutes,
                ],
                'message' => $reason,
                'url' => route('admin.overtime.index'),
            ],
        );

        return $request;
    }

    /**
     * Entscheiden (approve/reject). Genehmigung quittiert den offenen
     * Rahmenzeit-Plausibilitäts-Befund desselben Tages automatisch.
     */
    public function decide(OvertimeRequest $request, User $decider, bool $approved, ?string $note = null): OvertimeRequest {
        if ($request->status !== OvertimeRequestStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => __('Dieser Antrag ist bereits entschieden.'),
            ]);
        }

        // MVP-531: konfigurierbare Stufen — eine Zwischenstufe lässt den
        // Antrag offen (Vier-Augen erzwingt der ApprovalFlowService).
        $flow = app(ApprovalFlowService::class);
        if ($approved) {
            $progress = $flow->approveStage($request, ApprovalFlowService::TYPE_OVERTIME, $decider, $note);
            if (! $progress->isFinal()) {
                $request->audit('overtime.stage_approved', [
                    'actor_user_id' => (int) $decider->getKey(),
                    'stage' => $progress->approved,
                    'required' => $progress->required,
                ]);

                return $request;
            }
        } else {
            $flow->rejectStage($request, $decider, $note);
        }

        return DB::transaction(function () use ($request, $decider, $approved, $note): OvertimeRequest {
            $request->fill([
                'status' => $approved ? OvertimeRequestStatus::Approved : OvertimeRequestStatus::Rejected,
                'decided_at' => Carbon::now(),
                'decided_by_user_id' => $decider->getKey(),
                'decision_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ])->save();

            $request->audit($approved ? 'overtime.approved' : 'overtime.rejected', [
                'actor_user_id' => (int) $decider->getKey(),
                'scope_date' => $request->scope_date->toDateString(),
                'minutes' => $request->minutes,
                'note' => $request->decision_note,
            ]);

            if ($approved) {
                $this->acknowledgeFrameFinding($request, $decider);
            }

            $owner = $request->user;
            if ($owner !== null) {
                app(NotificationDispatcher::class)->notify(
                    NotificationEvent::OvertimeDecided,
                    $request,
                    $owner,
                    [
                        'title' => (string) __('notification.message.overtime_decided_title', [
                            'date' => $request->scope_date->format('d.m.Y'),
                        ]),
                        'title_key' => 'notification.message.overtime_decided_title',
                        'title_params' => ['date' => $request->scope_date->toDateString()],
                        'message' => (string) __(
                            $approved ? 'notification.message.overtime_approved' : 'notification.message.overtime_rejected',
                            ['note' => (string) ($request->decision_note ?? '')],
                        ),
                        'message_key' => $approved ? 'notification.message.overtime_approved' : 'notification.message.overtime_rejected',
                        'message_params' => ['note' => (string) ($request->decision_note ?? '')],
                        'url' => route('overtime.index'),
                    ],
                );
            }

            return $request;
        });
    }

    public function withdraw(OvertimeRequest $request, User $actor): OvertimeRequest {
        if ($request->status !== OvertimeRequestStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => __('Nur offene Anträge können zurückgezogen werden.'),
            ]);
        }

        $request->fill(['status' => OvertimeRequestStatus::Withdrawn])->save();
        $request->audit('overtime.withdrawn', [
            'actor_user_id' => (int) $actor->getKey(),
            'scope_date' => $request->scope_date->toDateString(),
        ]);

        return $request;
    }

    /**
     * Offenen Rahmenzeit-Befund („Ungeklärte Fälle") desselben Nutzers/Tags
     * quittieren — die genehmigten Überstunden SIND die Klärung.
     */
    private function acknowledgeFrameFinding(OvertimeRequest $request, User $decider): void {
        ComplianceFinding::query()
            ->where('organization_id', $request->organization_id)
            ->where('category', AttendancePlausibilityScanService::CATEGORY)
            ->where('rule_code', AttendancePlausibilityScanService::KIND_FRAME_TIME)
            ->where('subject_type', User::class)
            ->where('subject_id', $request->user_id)
            ->whereDate('scope_date', $request->scope_date->toDateString())
            ->whereIn('status', [ComplianceFindingStatus::Open->value, ComplianceFindingStatus::Acknowledged->value])
            ->get()
            ->each(function (ComplianceFinding $finding) use ($request, $decider): void {
                $finding->status = ComplianceFindingStatus::Accepted;
                $finding->acknowledged_by = (int) $decider->getKey();
                $finding->acknowledged_at = Carbon::now();
                $finding->acknowledge_note = (string) __('Überstunden genehmigt (Antrag #:id).', ['id' => (string) $request->getKey()]);
                $finding->save();
                $finding->audit('compliance.finding.accepted', [
                    'actor_user_id' => (int) $decider->getKey(),
                    'from' => ComplianceFindingStatus::Open->value,
                    'to' => ComplianceFindingStatus::Accepted->value,
                    'note' => $finding->acknowledge_note,
                    'overtime_request_id' => (int) $request->getKey(),
                ]);
            });
    }
}
