<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Models\{Attendance, TimeCorrectionItem, TimeCorrectionRequest, TimeEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Statusmaschine für Zeit-Korrekturanträge (MVP-017, ../WorkDiary-Architecture/zeit-korrekturen.md).
 *
 * Übergänge:
 *  draft → submitted → approved → applied
 *                   ↘ rejected
 *                   ↘ withdrawn (durch Antragsteller)
 *
 * `apply()` ist idempotent: ein bereits angewandter Antrag wird unverändert
 * zurückgegeben. Zudem prüft `apply()` über
 * {@see MonthClosureService::isPeriodLockedForUser()}, dass der Quell-Tag
 * nicht in einem `submitted|approved|locked` Monat liegt.
 */
class TimeCorrectionService {
    public const REASON_MIN_LENGTH = 20;

    /** @var list<string> Erlaubte Target-Typen für Items. */
    public const ALLOWED_TARGETS = [TimeEntry::class, Attendance::class];

    public function __construct(
        private readonly MonthClosureService $monthClosures,
    ) {}

    /**
     * Legt einen neuen Draft-Antrag mit ≥1 Item an (atomar).
     *
     * @param  list<array{target_type:string, target_id:?int, action:string, before?:array<string,mixed>|null, after?:array<string,mixed>|null}>  $items
     */
    public function createDraft(
        User $owner,
        CarbonImmutable $scopeDate,
        string $reason,
        array $items,
        ?User $requestedBy = null,
    ): TimeCorrectionRequest {
        $this->assertReason($reason);
        if ($items === []) {
            throw new TimeCorrectionWorkflowException(
                'noItems',
                __('Ein Korrekturantrag benötigt mindestens ein Änderungs-Item.'),
            );
        }
        foreach ($items as $i => $item) {
            $this->assertItemShape($item, $i);
        }

        $requestedById = $requestedBy instanceof User ? (int) $requestedBy->id : ((int) (Auth::id() ?? $owner->id));

        return DB::transaction(function () use ($owner, $scopeDate, $reason, $items, $requestedById): TimeCorrectionRequest {
            /** @var TimeCorrectionRequest $request */
            $request = TimeCorrectionRequest::query()->create([
                'organization_id' => $owner->organization_id,
                'user_id' => $owner->id,
                'requested_by_user_id' => $requestedById,
                'scope_date' => $scopeDate->toDateString(),
                'status' => TimeCorrectionStatus::Draft,
                'reason' => $reason,
            ]);

            foreach ($items as $item) {
                $request->items()->create([
                    'target_type' => $item['target_type'],
                    'target_id' => $item['target_id'] ?? null,
                    'action' => $item['action'],
                    'before' => $item['before'] ?? null,
                    'after' => $item['after'] ?? null,
                ]);
            }

            return $request->refresh()->load('items');
        });
    }

    /** draft → submitted. */
    public function submit(TimeCorrectionRequest $request, ?User $actor = null): TimeCorrectionRequest {
        $this->assertStatus($request, [TimeCorrectionStatus::Draft]);
        if ($request->items()->count() === 0) {
            throw new TimeCorrectionWorkflowException(
                'noItems',
                __('Der Antrag enthält keine Änderungen und kann nicht eingereicht werden.'),
            );
        }

        $request->fill(['status' => TimeCorrectionStatus::Submitted])->save();
        unset($actor); // Audit erfolgt via Auditable-Trait.

        $request = $request->refresh();

        // Benachrichtigung (MVP-018): Entscheider informieren (Default-Regel Teamleitung, nicht der Antragsteller).
        // Selbstkorrektur-Orgs überspringen — dort greift direkt selfApply().
        if ($this->selfApplicable($request)) {
            return $request;
        }

        $owner = $request->user;
        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::TimeCorrectionRequested,
            $request,
            $owner,
            [
                'title' => (string) __('notification.message.correction_requested_title', [
                    'user' => (string) ($owner->name ?? '–'),
                    'date' => $request->scope_date->format('d.m.Y'),
                ]),
                'title_key' => 'notification.message.correction_requested_title',
                'title_params' => [
                    'user' => (string) ($owner->name ?? '–'),
                    'date' => $request->scope_date->toDateString(),
                ],
                'message' => (string) $request->reason,
                'url' => route('admin.corrections.show', $request),
            ],
        );

        return $request;
    }

    /** submitted → withdrawn (nur durch Antragsteller). */
    public function withdraw(TimeCorrectionRequest $request, User $actor): TimeCorrectionRequest {
        $this->assertStatus($request, [TimeCorrectionStatus::Submitted, TimeCorrectionStatus::Draft]);
        if ((int) $actor->id !== (int) $request->requested_by_user_id) {
            throw new TimeCorrectionWorkflowException(
                'notRequester',
                __('Nur der Antragsteller darf einen Antrag zurückziehen.'),
            );
        }

        $request->fill(['status' => TimeCorrectionStatus::Withdrawn])->save();

        return $request->refresh();
    }

    /** submitted → approved. */
    public function approve(TimeCorrectionRequest $request, User $actor, ?string $note = null): TimeCorrectionRequest {
        $this->assertStatus($request, [TimeCorrectionStatus::Submitted]);

        $request->fill([
            'status' => TimeCorrectionStatus::Approved,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => $actor->id,
            'decision_note' => $note,
        ])->save();

        $request = $request->refresh();
        $this->notifyDecided($request);

        return $request;
    }

    /** Benachrichtigung „Korrekturantrag entschieden" an den Antragsteller (MVP-018, additiv). */
    private function notifyDecided(TimeCorrectionRequest $request): void {
        $owner = $request->user;
        if ($owner === null) {
            return;
        }

        $decisionKey = $request->status === TimeCorrectionStatus::Approved
            ? 'correction_approved'
            : 'correction_rejected';

        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::TimeCorrectionDecided,
            $request,
            $owner,
            [
                'title' => (string) __('notification.message.correction_decided_title', [
                    'date' => $request->scope_date->format('d.m.Y'),
                ]),
                'title_key' => 'notification.message.correction_decided_title',
                'title_params' => ['date' => $request->scope_date->toDateString()],
                'message' => (string) __('notification.message.' . $decisionKey, [
                    'note' => (string) ($request->decision_note ?? ''),
                ]),
                'message_key' => 'notification.message.' . $decisionKey,
                'message_params' => ['note' => (string) ($request->decision_note ?? '')],
                'url' => route('corrections.show', $request),
            ],
        );
    }

    /**
     * Darf dieser Antrag per Selbstkorrektur direkt angewendet werden? Nur wenn
     * (a) die Organisation den 'self'-Modus aktiviert hat UND (b) es eine
     * Eigenkorrektur ist (Antragsteller == betroffener Nutzer). Anträge im Namen
     * anderer brauchen immer eine Genehmigung.
     */
    public function selfApplicable(TimeCorrectionRequest $request): bool {
        if ((int) $request->requested_by_user_id !== (int) $request->user_id) {
            return false;
        }
        $mode = data_get(
            $request->organization?->settings,
            'attendance.self_correction',
            (string) config('attendance.self_correction', 'request'),
        );

        return $mode === 'self';
    }

    /**
     * submitted → approved → applied in einem Schritt: der Mitarbeiter trägt eine
     * vergessene Stempelung selbst nach (Firmen-Einstellung). Markiert
     * self_applied; formale Genehmigung durch den Antragsteller selbst.
     */
    public function selfApply(TimeCorrectionRequest $request): TimeCorrectionRequest {
        $this->assertStatus($request, [TimeCorrectionStatus::Submitted]);

        $request->fill([
            'status' => TimeCorrectionStatus::Approved,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => $request->user_id,
            'decision_note' => __('Selbstkorrektur gemäß Organisations-Einstellung'),
            'self_applied' => true,
        ])->save();

        return $this->apply($request);
    }

    /** submitted → rejected. Pflicht-Begründung. */
    public function reject(TimeCorrectionRequest $request, User $actor, string $reason): TimeCorrectionRequest {
        $this->assertStatus($request, [TimeCorrectionStatus::Submitted]);
        $this->assertReason($reason);

        $request->fill([
            'status' => TimeCorrectionStatus::Rejected,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => $actor->id,
            'decision_note' => $reason,
        ])->save();

        $request = $request->refresh();
        $this->notifyDecided($request);

        return $request;
    }

    /**
     * approved → applied. Idempotent: bereits angewandte Anträge werden
     * unverändert zurückgegeben.
     *
     * Wirft `monthLocked`, wenn der scope_date in einem gesperrten Monat liegt.
     */
    public function apply(TimeCorrectionRequest $request, ?User $actor = null): TimeCorrectionRequest {
        if ($request->status === TimeCorrectionStatus::Applied) {
            return $request;
        }
        $this->assertStatus($request, [TimeCorrectionStatus::Approved]);

        $owner = $request->user;
        if (! $owner instanceof User) {
            throw new TimeCorrectionWorkflowException(
                'ownerMissing',
                __('Antrag ohne gültigen Betroffenen.'),
                ['request_id' => $request->id],
            );
        }

        $scope = CarbonImmutable::parse($request->scope_date->toDateString());
        if ($this->monthClosures->isPeriodLockedForUser($owner, $scope)) {
            throw new TimeCorrectionWorkflowException(
                'monthLocked',
                __('Der Quell-Tag liegt in einem gesperrten Monat — bitte erst :action.', ['action' => __('Monat wieder öffnen')]),
                ['scope_date' => $scope->toDateString()],
            );
        }

        unset($actor); // Audit-Spuren entstehen pro Item-Apply via Auditable.

        return DB::transaction(function () use ($request): TimeCorrectionRequest {
            foreach ($request->items()->get() as $item) {
                /** @var TimeCorrectionItem $item */
                $this->applyItem($item);
            }

            $request->fill([
                'status' => TimeCorrectionStatus::Applied,
                'applied_at' => CarbonImmutable::now(),
            ])->save();

            return $request->refresh();
        });
    }

    // ── intern ─────────────────────────────────────────────────────────

    private function applyItem(TimeCorrectionItem $item): void {
        $targetType = $item->target_type;
        if (! in_array($targetType, self::ALLOWED_TARGETS, true)) {
            throw new TimeCorrectionWorkflowException(
                'unsupportedTarget',
                __('Target-Typ :type wird nicht unterstützt.', ['type' => $targetType]),
                ['target_type' => $targetType],
            );
        }

        // Übergabe-Guard (Feature 045): bereits an die Fakturierung übergebene Zeiteinträge (exported) dürfen nicht
        // mehr still korrigiert werden — Korrekturen erfordern eine Storno-/Differenzübergabe im führenden System.
        if (
            $targetType === TimeEntry::class
            && in_array($item->action, ['update', 'delete'], true)
            && $item->target_id !== null
        ) {
            $existing = TimeEntry::query()->find((int) $item->target_id);
            if ($existing !== null && $existing->exported) {
                throw new TimeCorrectionWorkflowException(
                    'sourceTransferred',
                    __('finance.error.entry_already_transferred'),
                    ['target_id' => (int) $item->target_id],
                );
            }
        }

        $after = $item->after ?? [];

        // Jede aus einer Korrektur geschriebene Stempelung ist per Definition
        // manuell (kein echter Stempel) → Quelle erzwingen (Nachvollziehbarkeit).
        if ($item->target_type === Attendance::class && in_array($item->action, ['create', 'update'], true)) {
            $after['source'] = AttendanceSource::Manual->value;
        }

        match ($item->action) {
            'create' => $this->applyCreate($targetType, $after),
            'update' => $this->applyUpdate($targetType, (int) $item->target_id, $after),
            'delete' => $this->applyDelete($targetType, (int) $item->target_id),
            default => throw new TimeCorrectionWorkflowException(
                'unsupportedAction',
                __('Aktion :action wird nicht unterstützt.', ['action' => $item->action]),
                ['action' => $item->action],
            ),
        };
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<string, mixed>  $attrs
     */
    private function applyCreate(string $modelClass, array $attrs): void {
        $modelClass::query()->create($attrs);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<string, mixed>  $attrs
     */
    private function applyUpdate(string $modelClass, int $id, array $attrs): void {
        /** @var \Illuminate\Database\Eloquent\Model|null $model */
        $model = $modelClass::query()->find($id);
        if ($model === null) {
            throw new TimeCorrectionWorkflowException(
                'targetMissing',
                __('Quell-Datensatz :id existiert nicht mehr.', ['id' => $id]),
                ['target_id' => $id, 'target_type' => $modelClass],
            );
        }
        $model->fill($attrs)->save();
    }

    /** @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass */
    private function applyDelete(string $modelClass, int $id): void {
        $modelClass::query()->whereKey($id)->delete();
    }

    /** @param  list<TimeCorrectionStatus>  $allowed */
    private function assertStatus(TimeCorrectionRequest $request, array $allowed): void {
        if (! in_array($request->status, $allowed, true)) {
            throw new TimeCorrectionWorkflowException(
                'illegalTransition',
                __('Aktion nicht erlaubt: Antragsstatus ist :status.', ['status' => $request->status->value]),
                ['from' => $request->status->value, 'allowed' => array_map(fn(TimeCorrectionStatus $s) => $s->value, $allowed)],
            );
        }
    }

    private function assertReason(string $reason): void {
        if (mb_strlen(trim($reason)) < self::REASON_MIN_LENGTH) {
            throw new TimeCorrectionWorkflowException(
                'reasonTooShort',
                __('Eine Begründung von mindestens :n Zeichen ist erforderlich.', ['n' => self::REASON_MIN_LENGTH]),
                ['min' => self::REASON_MIN_LENGTH],
            );
        }
    }

    /** @param  array<string, mixed>  $item */
    private function assertItemShape(array $item, int $index): void {
        foreach (['target_type', 'action'] as $required) {
            if (! isset($item[$required]) || ! is_string($item[$required]) || $item[$required] === '') {
                throw new TimeCorrectionWorkflowException(
                    'itemShape',
                    __('Item :i: Feld :field fehlt.', ['i' => $index, 'field' => $required]),
                    ['index' => $index, 'field' => $required],
                );
            }
        }
        if (! in_array($item['action'], ['create', 'update', 'delete'], true)) {
            throw new TimeCorrectionWorkflowException(
                'itemShape',
                __('Item :i: ungültige Aktion :action.', ['i' => $index, 'action' => $item['action']]),
                ['index' => $index, 'action' => $item['action']],
            );
        }
    }
}
