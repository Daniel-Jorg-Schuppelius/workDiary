<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberSequenceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\{NumberFormat, NumberSequence, Organization};
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Zentrale Vergabe konfigurierbarer, mandantenspezifischer Nummernkreise
 * (ServiceTicket/Asset/Customer/Invoice/CreditNote).
 *
 * `next()` MUSS innerhalb einer DB-Transaktion robust gegen parallele
 * Aufrufe sein: Wir holen die Sequence-Row per `lockForUpdate()` (oder
 * legen sie atomar an) und inkrementieren `last_value` in derselben
 * Transaktion. So bleibt die Unique-Constraint
 * `(organization_id, <number-spalte>)` der Ziel-Tabelle geschützt.
 */
class NumberSequenceService {
    public function next(Organization|int $organization, NumberScope $scope, ?CarbonInterface $when = null): string {
        $orgId = $organization instanceof Organization ? (int) $organization->id : (int) $organization;
        $when ??= Carbon::now();
        $format = $this->resolveFormat($orgId, $scope);
        $period = $format->reset_per_year ? $when->format('Y') : null;

        return DB::transaction(function () use ($orgId, $scope, $period, $format, $when): string {
            $sequence = $this->lockOrCreateSequence($orgId, $scope, $period, (int) $format->starts_at);
            $sequence->last_value++;
            $sequence->save();

            return $this->compose($format, $when, (int) $sequence->last_value);
        });
    }

    public function peekNext(Organization|int $organization, NumberScope $scope, ?CarbonInterface $when = null): string {
        $orgId = $organization instanceof Organization ? (int) $organization->id : (int) $organization;
        $when ??= Carbon::now();
        $format = $this->resolveFormat($orgId, $scope);
        $period = $format->reset_per_year ? $when->format('Y') : null;

        /** @var NumberSequence|null $sequence */
        $sequence = NumberSequence::query()
            ->where('organization_id', $orgId)
            ->where('scope', $scope->value)
            ->where(function ($q) use ($period): void {
                if ($period === null) {
                    $q->whereNull('period');
                } else {
                    $q->where('period', $period);
                }
            })
            ->first();

        $current = $sequence !== null ? (int) $sequence->last_value : (int) $format->starts_at;

        return $this->compose($format, $when, $current + 1);
    }

    /** @param array<string, mixed> $attributes */
    public function setFormat(Organization|int $organization, NumberScope $scope, array $attributes): NumberFormat {
        $orgId = $organization instanceof Organization ? (int) $organization->id : (int) $organization;
        $defaults = $this->defaultsFor($scope);
        $payload = array_merge($defaults, $attributes, [
            'organization_id' => $orgId,
            'scope' => $scope->value,
        ]);

        /** @var NumberFormat $format */
        $format = NumberFormat::query()->updateOrCreate(
            ['organization_id' => $orgId, 'scope' => $scope->value],
            $payload,
        );

        return $format;
    }

    public function resolveFormat(int $orgId, NumberScope $scope): NumberFormat {
        /** @var NumberFormat|null $format */
        $format = NumberFormat::query()
            ->where('organization_id', $orgId)
            ->where('scope', $scope->value)
            ->first();

        if ($format !== null) {
            return $format;
        }

        return new NumberFormat($this->defaultsFor($scope) + [
            'organization_id' => $orgId,
            'scope' => $scope->value,
        ]);
    }

    private function lockOrCreateSequence(int $orgId, NumberScope $scope, ?string $period, int $startsAt): NumberSequence {
        /** @var NumberSequence|null $sequence */
        $sequence = NumberSequence::query()
            ->where('organization_id', $orgId)
            ->where('scope', $scope->value)
            ->where(function ($q) use ($period): void {
                if ($period === null) {
                    $q->whereNull('period');
                } else {
                    $q->where('period', $period);
                }
            })
            ->lockForUpdate()
            ->first();

        if ($sequence instanceof NumberSequence) {
            return $sequence;
        }

        // Defensive insert: bei Race kann der zweite Aufrufer hier landen.
        // Wir verlassen uns auf die Unique-Constraint
        // (organization_id, scope, period) und fangen den Konflikt durch
        // erneutes lockForUpdate-Read auf.
        try {
            return NumberSequence::query()->create([
                'organization_id' => $orgId,
                'scope' => $scope->value,
                'period' => $period,
                'last_value' => $startsAt,
            ]);
        } catch (\Throwable) {
            /** @var NumberSequence $existing */
            $existing = NumberSequence::query()
                ->where('organization_id', $orgId)
                ->where('scope', $scope->value)
                ->where(function ($q) use ($period): void {
                    if ($period === null) {
                        $q->whereNull('period');
                    } else {
                        $q->where('period', $period);
                    }
                })
                ->lockForUpdate()
                ->firstOrFail();

            return $existing;
        }
    }

    private function compose(NumberFormat $format, CarbonInterface $when, int $sequenceValue): string {
        $out = (string) $format->prefix;

        if ($format->include_year) {
            $out .= (string) $format->prefix_separator;
            $out .= $when->format('Y');
            $out .= (string) $format->year_separator;
        } elseif ($out !== '') {
            $out .= (string) $format->prefix_separator;
        }

        $out .= str_pad((string) $sequenceValue, (int) $format->padding, '0', STR_PAD_LEFT);

        return $out;
    }

    /** @return array<string, mixed> */
    private function defaultsFor(NumberScope $scope): array {
        /** @var array<string, array<string, mixed>> $all */
        $all = (array) config('numbering.defaults', []);

        return $all[$scope->value] ?? [
            'prefix' => '',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ];
    }
}
