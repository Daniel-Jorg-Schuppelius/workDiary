<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Damage;

use App\Enums\Damage\DamageCaseStatus;
use App\Enums\Numbering\NumberScope;
use App\Models\Contracts\DamageCaseSubject;
use App\Models\Damage\DamageCase;
use App\Models\Platform\User;
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Einzige Schreibstelle für Schadensfälle (MVP-919): Anlage am Träger,
 * Pflege der Angaben, Statuswechsel nach dem Statusvertrag. Jede Änderung
 * steht im Journal des Falls.
 */
final class DamageCaseService {
    use AssertsStatusTransition;

    /** Felder, die ohne Statuswechsel gepflegt werden. */
    public const EDITABLE = [
        'kind', 'title', 'description', 'occurred_at', 'reported_at', 'insurer_name', 'policy_number',
        'claim_number', 'estimated_amount', 'deductible_amount', 'currency', 'responsible_user_id',
    ];

    /** Aktenarten mit Schadensfällen (MVP-920), Reihenfolge für Filter und Anzeige. */
    public const SUBJECTS = [
        \App\Models\Rental\RentalCase::class,
        \App\Models\AssetFinance\AssetFinanceContract::class,
        \App\Models\Claims\ClaimCase::class,
        \App\Models\Fleet\Vehicle::class,
    ];

    public function __construct(private readonly NumberSequenceService $numbers) {}

    /** @param  array<string, mixed>  $data */
    public function open(Model&DamageCaseSubject $subject, array $data, User $actor): DamageCase {
        $organizationId = (int) $subject->getAttribute('organization_id');

        return DB::transaction(function () use ($subject, $data, $actor, $organizationId): DamageCase {
            $case = DamageCase::query()->create(array_intersect_key($data, array_flip(self::EDITABLE)) + [
                'organization_id' => $organizationId,
                'number' => $this->numbers->next($organizationId, NumberScope::Damage),
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'kind' => $subject->damageDefaultKind()->value,
                'status' => DamageCaseStatus::Reported->value,
                'reported_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $case->record('opened', ['subject' => $subject->damageSubjectLabel()], $actor);

            return $case;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(DamageCase $case, array $data, User $actor): DamageCase {
        $case->fill(array_intersect_key($data, array_flip(self::EDITABLE)) + ['updated_by' => $actor->id]);
        $changed = array_values(array_diff(array_keys($case->getDirty()), ['updated_by']));
        if ($changed === []) {
            return $case;
        }

        return DB::transaction(function () use ($case, $changed, $actor): DamageCase {
            $case->save();
            $case->record('updated', ['fields' => $changed], $actor);

            return $case;
        });
    }

    /**
     * Statuswechsel; „reguliert“ verlangt den regulierten Betrag.
     *
     * @param  array{settled_amount?: string|null, claim_number?: string|null, note?: string|null}  $data
     */
    public function transition(DamageCase $case, DamageCaseStatus $to, User $actor, array $data = []): DamageCase {
        $this->assertStatusTransition($case->status, $to);
        $settled = $data['settled_amount'] ?? $case->settled_amount;
        if ($to === DamageCaseStatus::Settled && ($settled === null || $settled === '')) {
            throw new RuntimeException((string) __('damage.error.settled_amount_required'));
        }

        return DB::transaction(function () use ($case, $to, $actor, $data, $settled): DamageCase {
            $from = $case->status;
            $case->forceFill(array_filter([
                'status' => $to->value,
                'settled_amount' => $to === DamageCaseStatus::Settled ? $settled : null,
                'claim_number' => $data['claim_number'] ?? null,
                'updated_by' => $actor->id,
            ], static fn (mixed $v): bool => $v !== null))->save();
            $case->record('status', array_filter([
                'from' => $from->value,
                'to' => $to->value,
                'settled_amount' => $to === DamageCaseStatus::Settled ? (string) $settled : null,
                'note' => $data['note'] ?? null,
            ], static fn (mixed $v): bool => $v !== null && $v !== ''), $actor);

            return $case;
        });
    }
}
