<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeHandoverService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Customer\Contracts\IntakeHandoverTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gemeinsame Übernahmeregeln (MVP-1075): Zieladapter der Fachmodule
 * ({@see IntakeHandoverTarget}), Modul- und Zielrecht, vollständig passende
 * Angebotsannahme, Zeilensperre und Idempotenz — wiederholte oder
 * gleichzeitige Übernahmen liefern denselben Zielvorgang.
 */
class CustomerIntakeHandoverService {
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly CustomerIntakeQuoteService $quotes,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    public function targetFor(IntakeKind $kind): ?IntakeHandoverTarget {
        foreach ($this->modules->extensions(IntakeHandoverTarget::class) as $class) {
            $target = app($class);
            if ($target->kind() === $kind) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Warum die Übernahme (noch) nicht geht — Schlüssel → Meldung, leer = möglich.
     *
     * @return array<string, string>
     */
    public function blockers(CustomerIntake $intake, User $actor): array {
        $blockers = [];
        if (! in_array($intake->status, [IntakeStatus::InProgress, IntakeStatus::AwaitingCustomer], true)) {
            $blockers['status'] = (string) __('customer_intake.handover.blocked.status');
        }

        $target = $this->targetFor($intake->kind);
        $organization = Organization::query()->withoutGlobalScopes()->find($intake->organization_id);
        if ($target === null || $organization === null || ! $target->isAvailable($organization)) {
            $blockers['target'] = (string) __('customer_intake.handover.blocked.target_unavailable');
        } elseif (! $target->canCreate($actor)) {
            $blockers['target'] = (string) __('customer_intake.handover.blocked.target_forbidden');
        }

        $quote = $intake->quote;
        if ($quote === null) {
            $blockers['quote'] = (string) __('customer_intake.handover.blocked.quote_missing');
        } elseif ((int) $quote->customer_id !== (int) $intake->customer_id) {
            $blockers['quote'] = (string) __('customer_intake.error.quote_foreign');
        } elseif (! $quote->status->isWon()) {
            $blockers['quote'] = (string) __('customer_intake.handover.blocked.quote_not_accepted');
        } elseif ($this->quotes->isSuperseded($quote)) {
            $blockers['quote'] = (string) __('customer_intake.handover.blocked.quote_superseded');
        }

        return $blockers;
    }

    /** Teilannahme: Übernahme nur mit dokumentiertem Abgleich des Leistungsumfangs. */
    public function needsScopeNote(CustomerIntake $intake): bool {
        return $intake->quote?->status === QuoteStatus::PartiallyAccepted;
    }

    /** @param  array<string, mixed>  $input  validierte Zusatzfelder des Zieladapters */
    public function handOver(CustomerIntake $intake, User $actor, array $input = [], ?string $scopeNote = null): Model {
        $performed = false;
        $target = DB::transaction(function () use ($intake, $actor, $input, $scopeNote, &$performed): Model {
            /** @var CustomerIntake $locked */
            $locked = CustomerIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if ($locked->target_id !== null) {
                return $locked->target()->withoutGlobalScopes()->firstOrFail();
            }

            $blockers = $this->blockers($locked, $actor);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['handover' => array_values($blockers)]);
            }
            $scopeNote = trim((string) $scopeNote);
            if ($this->needsScopeNote($locked) && $scopeNote === '') {
                throw ValidationException::withMessages(['scope_note' => (string) __('customer_intake.handover.scope_note_required')]);
            }

            $adapter = $this->targetFor($locked->kind) ?? throw ValidationException::withMessages(['handover' => (string) __('customer_intake.handover.blocked.target_unavailable')]);
            $created = $adapter->handOver($locked, $actor, $input);

            $locked->forceFill([
                'target_type' => $created->getMorphClass(),
                'target_id' => $created->getKey(),
                'status' => IntakeStatus::HandedOver,
                'handed_over_at' => now(),
                'handover_user_id' => $actor->id,
                'closed_at' => now(),
            ])->save();
            $locked->record('handed_over', [
                'target' => $adapter->targetLabel($created),
                'quote_id' => $locked->quote_id,
                'scope_note' => $scopeNote !== '' ? $scopeNote : null,
            ], $actor);
            $performed = true;

            return $created;
        });

        $intake->refresh();
        if ($performed) {
            $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::HANDED_OVER);
        }

        return $target;
    }
}
