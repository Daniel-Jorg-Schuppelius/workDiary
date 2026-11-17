<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FollowUpPeriodService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Enums\Diary\{Mode, Status};
use App\Enums\Procedure\ProcedureRunStatus;
use App\Exceptions\ClassificationRequirementException;
use App\Models\Classification\EntryType;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureTemplate};
use App\Services\Classification\ClassificationRequirementValidator;
use App\Services\Customer\Deadlines\CustomerDeadlineService;
use App\Services\Procedure\ProcedureExecutionService;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Folgeperiode anlegen (Feature 198, MVP-1637): einzeln am Auftrag oder
 * gesammelt für eine Eintragsart und Periode (erst Vorschau, dann Anlage).
 *
 * Übernommen werden Kunde, Eintragsart, Produktgruppe, Zuständigkeit,
 * Prüfpflicht, benannte prüfende Person, Standort und die Prozedurvorlagen des
 * Vorgängers als neue Läufe. Offene Merker (Feature 201) erscheinen über die
 * Reihe von selbst, kopiert wird nichts. Nicht übernommen werden Inhalt und
 * Notizen, Zeiten und Ergebnis. Je Kunde × Eintragsart × Produktgruppe ×
 * Periode entsteht höchstens ein Auftrag; ein vorhandener (nicht stornierter)
 * wird genannt statt verdoppelt.
 */
class FollowUpPeriodService {
    /** Sperre je Organisation: zwei gleichzeitige Läufe dürfen keine Dublette anlegen. */
    private const int LOCK_SECONDS = 30;

    /** Höchstzahl der Vorgänger einer Sammelanlage. */
    public const int BULK_LIMIT = 500;

    public function __construct(
        private readonly ClassificationRequirementValidator $requirements,
        private readonly ProcedureExecutionService $procedures,
        private readonly CustomerDeadlineService $customerDeadlines,
    ) {}

    /** Die Periode, die auf die des Auftrags folgt; null ohne Periode. */
    public function targetPeriod(DiaryEntry $source): ?PeriodKey {
        return $source->period_key?->next();
    }

    /**
     * Fälligkeit des Auftrags der Folgeperiode aus den Kundenfristen
     * (Feature 197, MVP-1632): {@see CustomerDeadlineService::dueDateFor()}
     * liefert den frühesten Termin der nicht entfallenen Fristen des Kunden in
     * der Zielperiode, die der Regelsatz Eintragsart und Produktgruppe des
     * Vorgängers zuordnet; ohne passende Frist null (Feature 198: „sonst leer“).
     */
    public function dueDateFor(DiaryEntry $source, PeriodKey $period): ?CarbonImmutable {
        return $this->customerDeadlines->dueDateFor($source, $period);
    }

    /** Vorhandener, nicht stornierter Auftrag derselben Reihe in der Periode (Dublettenschutz). */
    public function existingFor(DiaryEntry $source, PeriodKey $period): ?DiaryEntry {
        if ($source->customer_id === null || $source->entry_type_id === null) {
            return null;
        }
        $productGroup = $source->productGroupCode();

        return DiaryEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('customer_id', $source->customer_id)
            ->where('entry_type_id', $source->entry_type_id)
            ->where('period_key', $period->key())
            ->where('status', '!=', Status::Cancelled->value)
            ->whereKeyNot($source->getKey())
            ->when(
                $productGroup !== null,
                static fn (Builder $q) => $q->whereHas('classifications', static fn (Builder $c) => $c->where('domain', ClassificationDomain::ProductGroup->value)->where('code', $productGroup)),
                static fn (Builder $q) => $q->whereDoesntHave('classifications', static fn (Builder $c) => $c->where('domain', ClassificationDomain::ProductGroup->value)),
            )
            ->orderBy('id')
            ->first();
    }

    /**
     * Vorschau der Sammelanlage: alle nicht stornierten Aufträge der
     * Eintragsart in der Periode, je mit Zielperiode und vorhandenem Auftrag.
     *
     * @return list<array{source: DiaryEntry, target: PeriodKey, existing: DiaryEntry|null, blocked: string|null}>
     */
    public function preview(EntryType $type, PeriodKey $period): array {
        $sources = DiaryEntry::query()
            ->where('organization_id', $type->organization_id)
            ->where('entry_type_id', $type->id)
            ->where('period_key', $period->key())
            ->where('status', '!=', Status::Cancelled->value)
            ->where('is_archived', false)
            ->with(['customer:id,name', 'assignedUser:id,name', 'classifications', 'entryType:id,label'])
            ->orderBy('customer_id')
            ->orderBy('id')
            ->limit(self::BULK_LIMIT)
            ->get();

        $rows = [];
        $seen = [];
        foreach ($sources as $source) {
            $target = $period->next();
            $existing = $this->existingFor($source, $target);
            $series = $source->customer_id . ':' . ($source->productGroupCode() ?? '');
            $blocked = match (true) {
                $source->customer_id === null => 'no_customer',
                $existing !== null => 'exists',
                isset($seen[$series]) => 'duplicate_source',
                default => null,
            };
            $seen[$series] = true;
            $rows[] = ['source' => $source, 'target' => $target, 'existing' => $existing, 'blocked' => $blocked];
        }

        return $rows;
    }

    /**
     * Auftrag der Folgeperiode anlegen. `$target` (Feature 199, „Perioden des
     * Jahres anlegen“): eine spätere Periode gleicher Art statt der nächsten —
     * etwa nach einer Periode mit „entfällt“ im Leistungsplan.
     *
     * @throws ValidationException ohne Periode oder Kunde, bei vorhandenem Auftrag oder fehlender Pflichtklassifikation
     */
    public function create(DiaryEntry $source, User $actor, ?PeriodKey $target = null): DiaryEntry {
        return $this->locked((int) $source->organization_id, fn (): DiaryEntry => $this->createLocked($source, $actor, $target));
    }

    /**
     * Sammelanlage für eine Eintragsart und Periode; übersprungen wird, was
     * schon existiert oder nicht angelegt werden kann.
     *
     * @return array{created: list<DiaryEntry>, skipped: int}
     */
    public function createMany(EntryType $type, PeriodKey $period, User $actor): array {
        return $this->locked((int) $type->organization_id, function () use ($type, $period, $actor): array {
            $created = [];
            $skipped = 0;
            foreach ($this->preview($type, $period) as $row) {
                if ($row['blocked'] !== null) {
                    $skipped++;

                    continue;
                }
                try {
                    $created[] = $this->createLocked($row['source'], $actor);
                } catch (ValidationException) {
                    $skipped++;
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /** Läuft nur unter der Sperre der Organisation ({@see locked()}). */
    private function createLocked(DiaryEntry $source, User $actor, ?PeriodKey $target = null): DiaryEntry {
        $from = $source->period_key;
        // Eine vorgegebene Zielperiode muss gleicher Art sein und nach der des Vorgängers liegen.
        if ($target !== null && ($from === null || $target->kind !== $from->kind || ! $from->endsBefore($target))) {
            $target = null;
        } else {
            $target ??= $this->targetPeriod($source);
        }
        if ($target === null || $source->customer_id === null || $source->entry_type_id === null) {
            throw ValidationException::withMessages(['period_key' => __('diary.follow_up.error.no_series')]);
        }
        $existing = $this->existingFor($source, $target);
        if ($existing !== null) {
            throw ValidationException::withMessages(['period_key' => __('diary.follow_up.error.exists', ['id' => $existing->id])]);
        }

        $productGroup = $source->productGroupCode();
        $dueDate = $this->dueDateFor($source, $target);
        $data = [
            'organization_id' => $source->organization_id,
            'user_id' => $actor->id,
            'entry_type_id' => $source->entry_type_id,
            'customer_id' => $source->customer_id,
            'assigned_user_id' => $source->assigned_user_id,
            'title' => $this->title($source, $target),
            'content' => (string) __('diary.follow_up.content', ['period' => $target->label(), 'id' => $source->id]),
            'status' => Status::Planned->value,
            'period_key' => $target,
            'requires_review' => (bool) $source->requires_review,
            'reviewer_user_id' => $source->reviewer_user_id,
            'mode' => $dueDate !== null ? Mode::Deadline : Mode::Backlog,
            'due_date' => $dueDate?->toDateString(),
            'location_mode' => $source->location_mode,
        ];

        $candidate = new DiaryEntry($data);
        $candidate->organization_id = (int) $source->organization_id;
        $values = $this->requirements->valuesFor($candidate);
        if ($productGroup !== null) {
            $values[ClassificationDomain::ProductGroup->value] = [$productGroup];
        }
        try {
            $this->requirements->assertSatisfied($candidate, ClassificationRequirementPhase::OnCreate, $values);
        } catch (ClassificationRequirementException $e) {
            throw ValidationException::withMessages(['classification' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($source, $actor, $data, $productGroup, $target): DiaryEntry {
            $entry = new DiaryEntry($data);
            $entry->organization_id = (int) $source->organization_id;
            $entry->save();
            $entry->assignProductGroup($productGroup);
            $this->startProcedures($source, $entry, $actor);
            $entry->record('order.followUpCreated', ['source_id' => $source->id, 'period_key' => $target->key()], $actor, extra: [
                'from_status' => null,
                'to_status' => $entry->status->slug(),
                'actor_kind' => 'user',
            ]);

            return $entry->refresh();
        });
    }

    /** Titel des Vorgängers mit der neuen Periode an Stelle der alten. */
    private function title(DiaryEntry $source, PeriodKey $target): ?string {
        $title = trim((string) $source->title);
        $from = $source->period_key;
        if ($title === '' || $from === null) {
            return $title !== '' ? $title : null;
        }

        return mb_substr(str_replace([$from->label(), $from->key()], [$target->label(), $target->key()], $title), 0, 200);
    }

    /** Prozedurvorlagen des Vorgängers als neue Läufe (nur aktive Vorlagen mit gültiger Version). */
    private function startProcedures(DiaryEntry $source, DiaryEntry $entry, User $actor): void {
        $templateIds = ProcedureRun::query()
            ->where('subject_type', $source->getMorphClass())
            ->where('subject_id', $source->getKey())
            ->where('status', '!=', ProcedureRunStatus::Aborted->value)
            ->with('templateVersion:id,procedure_template_id')
            ->get()
            ->map(static fn (ProcedureRun $run): ?int => $run->templateVersion?->procedure_template_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($templateIds === []) {
            return;
        }

        $assignee = $entry->assignedUser;
        foreach (ProcedureTemplate::query()->whereIn('id', $templateIds)->where('active', true)->get() as $template) {
            try {
                $this->procedures->start($template, $entry, $actor, $assignee instanceof User ? $assignee : null);
            } catch (RuntimeException $e) {
                // Ohne gültige Version kein Lauf; der Auftrag entsteht trotzdem.
                report($e);
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(int $organizationId, callable $callback): mixed {
        return Cache::lock('diary:follow-up:' . $organizationId, self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, $callback);
    }
}
