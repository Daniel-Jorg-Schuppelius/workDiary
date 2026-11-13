<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CarryForward;

use App\Models\CarryForward\CarryForwardNote;
use App\Models\Contracts\PeriodicOccurrence;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Support\Period\PeriodKey;
use App\Support\Query\DateRange;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645): Lesen, Anlegen,
 * Abhaken und Zurückziehen für ein Vorkommen ({@see PeriodicOccurrence}).
 *
 * Sichtbarkeit: Ein offener Merker mit Zielperiode Z erscheint an jedem
 * Vorkommen derselben Reihe und Organisation, dessen Periode P nicht vor Z
 * endet (`P.end >= Z.start`) — also ab der Zielperiode, auch wenn Perioden
 * ausgelassen wurden, und über einen Wechsel der Periodenart hinweg. Abgehakt
 * erscheint er nur noch an dem Vorkommen, an dem er erledigt wurde.
 *
 * Rechte prüft der Aufrufer am Träger (`view`/`update`), nicht dieser Dienst.
 */
final class CarryForwardNotes {
    /** Höchstlänge des Reihenschlüssels (Index). */
    public const int SERIES_MAX = 191;

    /**
     * Merker eines Vorkommens: offen und fällig (`open`), hier für später
     * hinterlegt (`left`), hier abgehakt (`resolved`). Ohne Reihe oder Periode leer.
     *
     * @return array{open: Collection<int, CarryForwardNote>, left: Collection<int, CarryForwardNote>, resolved: Collection<int, CarryForwardNote>}
     */
    public function forOccurrence(Model&PeriodicOccurrence $subject): array {
        $empty = ['open' => new Collection, 'left' => new Collection, 'resolved' => new Collection];
        $series = $this->series($subject);
        $period = $subject->carryForwardPeriod();
        if ($series === null || $period === null) {
            return $empty;
        }

        $rows = $this->query($subject)
            ->where('series_key', $series)
            ->where(function (Builder $q) use ($subject, $period): void {
                $q->where(fn (Builder $open) => $open->whereNull('resolved_at')->where('valid_from', '<', DateRange::dayAfter($period->end())))
                    ->orWhere(fn (Builder $left) => $left->where('origin_type', $subject->getMorphClass())->where('origin_id', $subject->getKey()))
                    ->orWhere(fn (Builder $done) => $done->where('resolved_occurrence_type', $subject->getMorphClass())->where('resolved_occurrence_id', $subject->getKey()));
            })
            ->with(['creator:id,name', 'resolver:id,name'])
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get();

        $isHere = static fn (?string $type, ?int $id): bool => $type === $subject->getMorphClass() && (int) $id === (int) $subject->getKey();

        return [
            'open' => $rows->filter(fn (CarryForwardNote $n): bool => $n->isOpen() && $this->appliesTo($n, $period))->values(),
            'left' => $rows->filter(fn (CarryForwardNote $n): bool => $n->isOpen() && $isHere($n->origin_type, $n->origin_id))->values(),
            'resolved' => $rows->filter(fn (CarryForwardNote $n): bool => ! $n->isOpen() && $isHere($n->resolved_occurrence_type, $n->resolved_occurrence_id))->values(),
        ];
    }

    /**
     * Merker fürs nächste Mal: gilt ab $from (Vorgabe: die Folgeperiode), die
     * nach der Periode des Vorkommens beginnen muss.
     */
    public function create(Model&PeriodicOccurrence $subject, string $text, User $actor, ?PeriodKey $from = null): CarryForwardNote {
        $series = $this->series($subject);
        $period = $subject->carryForwardPeriod();
        if ($series === null || $period === null) {
            throw ValidationException::withMessages(['note' => __('carry_forward.error.no_series')]);
        }
        $from ??= $period->next();
        if (! $period->endsBefore($from)) {
            throw ValidationException::withMessages(['period_key' => __('carry_forward.error.target_not_after')]);
        }
        $text = trim($text);
        if ($text === '') {
            throw ValidationException::withMessages(['note' => __('carry_forward.error.empty')]);
        }

        return CarryForwardNote::query()->create([
            'organization_id' => $this->organizationId($subject),
            'series_key' => $series,
            'period_key' => $from,
            'valid_from' => $from->start(),
            'note' => $text,
            'origin_type' => $subject->getMorphClass(),
            'origin_id' => $subject->getKey(),
            'origin_period_key' => $period,
            'created_by' => $actor->id,
        ]);
    }

    /** Abhaken an einem Vorkommen, an dem der Merker fällig ist; der Vermerk ist optional. */
    public function resolve(CarryForwardNote $note, Model&PeriodicOccurrence $subject, User $actor, ?string $resolutionNote): CarryForwardNote {
        return DB::transaction(function () use ($note, $subject, $actor, $resolutionNote): CarryForwardNote {
            $fresh = $this->query($subject)->whereKey($note->id)->lockForUpdate()->first();
            $period = $subject->carryForwardPeriod();
            if (! $fresh instanceof CarryForwardNote || $period === null || $fresh->series_key !== $this->series($subject)) {
                throw ValidationException::withMessages(['note' => __('carry_forward.error.foreign_series')]);
            }
            if (! $fresh->isOpen()) {
                throw ValidationException::withMessages(['note' => __('carry_forward.error.already_resolved')]);
            }
            if (! $this->appliesTo($fresh, $period)) {
                throw ValidationException::withMessages(['note' => __('carry_forward.error.not_due')]);
            }
            $resolutionNote = trim((string) $resolutionNote);
            $fresh->update([
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolved_occurrence_type' => $subject->getMorphClass(),
                'resolved_occurrence_id' => $subject->getKey(),
                'resolution_note' => $resolutionNote !== '' ? $resolutionNote : null,
            ]);

            return $fresh;
        });
    }

    /**
     * Offene Merker mehrerer Reihen einer Organisation — für das Cockpit von
     * Kunde oder Asset (MVP-1646): nicht abgehakt, unabhängig davon, ob die
     * Zielperiode schon erreicht ist; früheste Zielperiode zuerst. Der Träger
     * nennt seine Reihen und prüft die Rechte.
     *
     * @param  list<string>  $series
     * @return array{count: int, notes: Collection<int, CarryForwardNote>}
     */
    public function openInSeries(int $organizationId, array $series, int $limit = 10): array {
        $series = array_values(array_unique(array_filter($series, static fn (string $key): bool => $key !== '')));
        if ($organizationId <= 0 || $series === []) {
            return ['count' => 0, 'notes' => new Collection];
        }
        $query = static fn (): Builder => CarryForwardNote::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereIn('series_key', $series)
            ->whereNull('resolved_at');

        return [
            'count' => $query()->count(),
            'notes' => $query()->with('creator:id,name')->orderBy('valid_from')->orderBy('id')->limit(max(1, $limit))->get(),
        ];
    }

    /**
     * Reihen mit offenen Merkern, deren Schlüssel mit `$prefix` beginnt — für
     * Träger, deren Reihen sich nicht vorab aufzählen lassen (Periodenaufträge
     * eines Kunden: `order:<kunde>:`). Platzhalter im Präfix gelten wörtlich.
     *
     * @return list<string>
     */
    public function openSeriesStartingWith(int $organizationId, string $prefix, int $limit = 100): array {
        if ($organizationId <= 0 || $prefix === '') {
            return [];
        }

        $keys = CarryForwardNote::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereNull('resolved_at')
            ->whereLikeEscaped('series_key', $prefix, 'prefix')
            ->distinct()
            ->orderBy('series_key')
            ->limit(max(1, $limit))
            ->pluck('series_key')
            ->all();

        // LIKE vergleicht je nach Datenbank ohne Groß-/Kleinschreibung — der Anfang muss exakt passen.
        return array_values(array_filter(array_map('strval', $keys), static fn (string $key): bool => str_starts_with($key, $prefix)));
    }

    /** Einen offenen Merker zurückziehen (Tippfehler, falsche Reihe); erledigte bleiben. */
    public function withdraw(CarryForwardNote $note): void {
        if (! $note->isOpen()) {
            throw ValidationException::withMessages(['note' => __('carry_forward.error.already_resolved')]);
        }
        $note->delete();
    }

    /** Ist der Merker an einem Vorkommen der Periode fällig (Periode endet nicht vor der Zielperiode)? */
    private function appliesTo(CarryForwardNote $note, PeriodKey $period): bool {
        $target = $note->period_key;

        return $target !== null && ! $period->endsBefore($target);
    }

    private function series(Model&PeriodicOccurrence $subject): ?string {
        $series = $subject->carryForwardSeries();
        if ($series === null || $series === '') {
            return null;
        }
        if (mb_strlen($series) > self::SERIES_MAX) {
            throw new \LengthException('Reihenschlüssel länger als ' . self::SERIES_MAX . ' Zeichen: ' . $subject::class);
        }

        return $series;
    }

    /** @return Builder<CarryForwardNote> */
    private function query(Model&PeriodicOccurrence $subject): Builder {
        return CarryForwardNote::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $this->organizationId($subject));
    }

    private function organizationId(Model&PeriodicOccurrence $subject): int {
        $organizationId = (int) $subject->getAttribute('organization_id');
        if ($organizationId <= 0) {
            throw new \LogicException('Vorkommen ohne organization_id: ' . $subject::class);
        }

        return $organizationId;
    }
}
