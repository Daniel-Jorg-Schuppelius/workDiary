<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Vacation\VacationStatus;
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\Licensing\ModuleExtensions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Org\UserOffboardingService;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Vertretungen anlegen und beenden (Feature 202, MVP-1647).
 *
 * Regeln: nur innerhalb einer Organisation, nie sich selbst, nur aktive
 * Konten, Beginn frühestens heute. Eine Person hat zur selben Zeit höchstens
 * eine Vertretung. Keine Kette: wer in dem Zeitraum vertreten wird, kann nicht
 * vertreten, und wer vertritt, wird nicht vertreten. Eine Abwesenheit als
 * Anlass gehört der vertretenen Person (Urlaub genehmigt, Krankmeldung nicht
 * storniert). Wer wen anlegen darf, prüft die Policy.
 *
 * MVP-1648: Die Vertretung wird beim Anlegen benachrichtigt (ohne Anlass);
 * wird der zugrunde liegende Urlaub storniert oder abgelehnt bzw. die
 * Krankmeldung storniert, endet die Vertretung ({@see endForAbsence()}).
 * Die endgültige Übergabe ohne Austritt läuft über die Übergaben der Module
 * ({@see handOverPermanently()}).
 */
final class WorkDelegationService {
    public function __construct(
        private readonly ModuleExtensions $extensions,
        private readonly UserOffboardingService $offboarding,
    ) {}

    public function create(
        User $represented,
        User $deputy,
        CarbonImmutable $from,
        ?CarbonImmutable $until,
        WorkDelegationKind $kind,
        Vacation|SickLeave|null $absence,
        ?string $note,
        User $actor,
    ): WorkDelegation {
        $organizationId = (int) $represented->organization_id;
        $from = $from->startOfDay();
        $until = $until?->startOfDay();
        $this->assertParties($represented, $deputy, $actor);
        if ($from->lessThan($this->today())) {
            throw ValidationException::withMessages(['valid_from' => __('delegation.error.past_start')]);
        }
        if ($until !== null && $until->lessThan($from)) {
            throw ValidationException::withMessages(['valid_until' => __('delegation.error.end_before_start')]);
        }
        if ($absence !== null && ! $this->absenceFits($absence, $represented)) {
            throw ValidationException::withMessages(['absence' => __('delegation.error.absence_mismatch')]);
        }

        $delegation = DB::transaction(function () use ($organizationId, $represented, $deputy, $from, $until, $kind, $absence, $note, $actor): WorkDelegation {
            if ($this->overlaps($organizationId, 'represented_user_id', (int) $represented->id, $from, $until)) {
                throw ValidationException::withMessages(['valid_from' => __('delegation.error.overlap', ['name' => $represented->name])]);
            }
            // Keine Kettenvertretung — in beide Richtungen.
            if ($this->overlaps($organizationId, 'represented_user_id', (int) $deputy->id, $from, $until)) {
                throw ValidationException::withMessages(['deputy_user_id' => __('delegation.error.deputy_absent', ['name' => $deputy->name])]);
            }
            if ($this->overlaps($organizationId, 'deputy_user_id', (int) $represented->id, $from, $until)) {
                throw ValidationException::withMessages(['represented_user_id' => __('delegation.error.represented_is_deputy', ['name' => $represented->name])]);
            }
            $note = trim((string) $note);

            return WorkDelegation::query()->create([
                'organization_id' => $organizationId,
                'represented_user_id' => $represented->id,
                'deputy_user_id' => $deputy->id,
                'valid_from' => $from,
                'valid_until' => $until,
                'kind' => $kind,
                'absence_type' => $absence?->getMorphClass(),
                'absence_id' => $absence?->getKey(),
                'note' => $note !== '' ? $note : null,
                'created_by' => $actor->id,
            ]);
        });
        $this->notifyDeputy($delegation, $represented, $deputy, $actor);

        return $delegation;
    }

    /** „Vertretung beenden“: wirkt sofort, auch vor dem geplanten Ende oder vor dem Beginn. */
    public function end(WorkDelegation $delegation, User $actor): WorkDelegation {
        if ($delegation->isEnded()) {
            throw ValidationException::withMessages(['delegation' => __('delegation.error.already_ended')]);
        }
        $delegation->update(['ended_at' => now(), 'ended_by' => $actor->id]);

        return $delegation;
    }

    /**
     * Die Abwesenheit hinter Vertretungen entfällt (Urlaub storniert oder
     * abgelehnt, Krankmeldung storniert): ihre noch nicht beendeten Vertretungen
     * enden sofort (Audit `work_delegation.ended`). Liefert die Anzahl.
     */
    public function endForAbsence(Vacation|SickLeave $absence, ?User $actor = null): int {
        $delegations = WorkDelegation::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', (int) $absence->organization_id)
            ->where('absence_type', $absence->getMorphClass())
            ->where('absence_id', $absence->getKey())
            ->whereNull('ended_at')
            ->get();
        foreach ($delegations as $delegation) {
            $delegation->update(['ended_at' => now(), 'ended_by' => $actor?->id]);
        }

        return $delegations->count();
    }

    /**
     * Endgültige Übergabe ohne Austritt (MVP-1648): je Bereich an die gewählte
     * Nachfolge über die Übergaben der Module ({@see \App\Services\Org\Contracts\OffboardingHandover},
     * derselbe Weg wie beim Austritt). Die Person bleibt aktiv; Audit
     * `user.workHandedOver` mit der Anzahl je Bereich.
     *
     * @param  array<string, User>  $successors  Bereichsschlüssel → Nachfolge
     * @return array<string, array{successor_user_id: int, count: int}>
     */
    public function handOverPermanently(User $member, array $successors, User $actor): array {
        $organizationId = (int) $member->organization_id;
        if ($organizationId <= 0 || $member->isDeactivated()) {
            throw ValidationException::withMessages(['member' => __('delegation.handover.error.inactive')]);
        }
        foreach ($successors as $key => $successor) {
            if ((int) $successor->organization_id !== $organizationId || $successor->isDeactivated() || (int) $successor->id === (int) $member->id) {
                throw ValidationException::withMessages(['handovers.' . $key => __('delegation.handover.error.successor')]);
            }
        }
        if ($successors === []) {
            throw ValidationException::withMessages(['handovers' => __('delegation.handover.error.none')]);
        }

        $result = DB::transaction(fn (): array => $this->offboarding->handOver($member, $successors, $actor));
        $member->audit('user.workHandedOver', [
            'areas' => $result,
            'count' => array_sum(array_column($result, 'count')),
            'by_user_id' => $actor->id,
        ]);

        return $result;
    }

    /**
     * Vorschlag aus einer Abwesenheit: Zeitraum ab heute bis zum Ende der
     * Abwesenheit, Vertretung aus `users.deputy_user_id` (MVP-523). Null, wenn
     * die Abwesenheit nicht taugt (Urlaub nicht genehmigt, storniert, vorbei).
     *
     * @return array{represented: User, deputy_user_id: int|null, valid_from: CarbonImmutable, valid_until: CarbonImmutable, kind: WorkDelegationKind}|null
     */
    public function suggestionFor(Vacation|SickLeave $absence): ?array {
        $owner = $absence->user;
        if (! $owner instanceof User || ! $this->absenceFits($absence, $owner)) {
            return null;
        }
        $today = $this->today();
        $end = CarbonImmutable::instance($absence->end_date)->startOfDay();
        if ($end->lessThan($today)) {
            return null;
        }
        $start = CarbonImmutable::instance($absence->start_date)->startOfDay();

        return [
            'represented' => $owner,
            'deputy_user_id' => $owner->deputy_user_id !== null ? (int) $owner->deputy_user_id : null,
            'valid_from' => $start->greaterThan($today) ? $start : $today,
            'valid_until' => $end,
            'kind' => $absence instanceof Vacation ? WorkDelegationKind::Vacation : WorkDelegationKind::Sickness,
        ];
    }

    /**
     * Was eine Vertretung übernimmt: delegierbare Bereiche der aktiven Module mit
     * offener Arbeit der Person (nur Anzahlen).
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    public function delegableWork(User $represented): array {
        $out = [];
        foreach ($this->extensions->activeFor(DelegableWork::class, (int) $represented->organization_id) as $class) {
            /** @var DelegableWork $area */
            $area = app($class);
            try {
                $count = $area->count($represented);
            } catch (Throwable $e) {
                // Ein Bereich darf den Dialog nicht kippen; sein Fehler wird gemeldet.
                report($e);

                continue;
            }
            if ($count > 0) {
                $out[] = ['key' => $area->key(), 'label' => $area->label(), 'count' => $count];
            }
        }

        return $out;
    }

    /**
     * Die Vertretung erfährt, wen sie ab wann vertritt — nie den Anlass
     * (Urlaub, Krankheit gehen nur die vertretene Person und die Leitung an).
     */
    private function notifyDeputy(WorkDelegation $delegation, User $represented, User $deputy, User $actor): void {
        if ((int) $deputy->id === (int) $actor->id) {
            return;
        }
        $params = [
            'name' => (string) $represented->name,
            'from' => $delegation->valid_from->format('d.m.Y'),
            'until' => $delegation->valid_until?->format('d.m.Y') ?? '',
        ];
        $key = $delegation->valid_until !== null ? 'delegation.notification.created' : 'delegation.notification.created_open';
        app(NotificationDispatcher::class)->notify(NotificationEvent::DelegationCreated, $delegation, $deputy, [
            'title' => (string) __($key, $params),
            'title_key' => $key,
            'title_params' => $params,
            'url' => route('delegations.index'),
        ]);
    }

    /** Heute in der Anzeige-Zeitzone, 00:00 Uhr. */
    public function today(): CarbonImmutable {
        return CarbonImmutable::now(Tz::current())->startOfDay();
    }

    private function assertParties(User $represented, User $deputy, User $actor): void {
        $organizationId = (int) $represented->organization_id;
        if ($organizationId <= 0 || (int) $deputy->organization_id !== $organizationId || (int) $actor->organization_id !== $organizationId) {
            throw ValidationException::withMessages(['deputy_user_id' => __('delegation.error.foreign_organization')]);
        }
        if ((int) $represented->id === (int) $deputy->id) {
            throw ValidationException::withMessages(['deputy_user_id' => __('delegation.error.self')]);
        }
        if ($represented->isDeactivated() || $deputy->isDeactivated()) {
            throw ValidationException::withMessages(['deputy_user_id' => __('delegation.error.deactivated')]);
        }
    }

    private function absenceFits(Vacation|SickLeave $absence, User $represented): bool {
        if ((int) $absence->user_id !== (int) $represented->id || (int) $absence->organization_id !== (int) $represented->organization_id) {
            return false;
        }

        return $absence instanceof Vacation
            ? $absence->status === VacationStatus::Approved
            : ! $absence->isCancelled();
    }

    /** Gibt es eine nicht beendete Vertretung mit $userId in $column, die den Zeitraum schneidet? */
    private function overlaps(int $organizationId, string $column, int $userId, CarbonImmutable $from, ?CarbonImmutable $until): bool {
        $query = WorkDelegation::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where($column, $userId)
            ->whereNull('ended_at')
            ->where(static fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($from)));
        if ($until !== null) {
            $query->where('valid_from', '<', DateRange::dayAfter($until));
        }

        return $query->exists();
    }
}
