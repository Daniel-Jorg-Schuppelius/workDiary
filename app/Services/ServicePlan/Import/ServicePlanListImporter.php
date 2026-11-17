<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanListImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Import;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Enums\Diary\{LocationMode, Mode, Status};
use App\Enums\Fields\FieldType;
use App\Enums\Period\PeriodKind;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Events\Diary\OrderCompleted;
use App\Exceptions\ClassificationRequirementException;
use App\Models\Classification\{Classification, EntryType};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Classification\{ClassificationRequirementValidator, ClassificationResolver};
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets, CustomerServiceManager};
use App\Services\Fields\FieldDefinition;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Services\ServicePlan\{ServicePlanCatalog, ServicePlanCells};
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{DateHelper, StringHelper};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

/**
 * Übernahme vorhandener Listen in den Leistungsplan (Feature 199, MVP-1640).
 *
 * Jahresliste: Kundennummer, Leistung, Stand, Bemerkung (optional Unterlagen
 * da, Pflicht, zuständig). Periodenliste: Kundennummer, Leistung und je
 * Periode eine Spalte (Monate, Quartale oder Halbjahre). Spalten mit den
 * Bezeichnungen der Regelsatz-Merkmale (USt-Turnus …) übernehmen die
 * Merkmale. Die Leistung kann für die ganze Liste vorgegeben werden.
 *
 * Zuordnung nie geraten: der Kunde nur über `customers.number` (genau), die
 * Leistung nur, wenn genau eine Vorlage oder Eintragsart passt, die
 * zuständige Person nur über Name oder E-Mail eines Mitglieds. Eine Zeile mit
 * unbekannter Kundennummer legt nichts an und wird gemeldet.
 *
 * Übernommen werden: fehlender Leistungsumfang (ab der laufenden Periode bzw.
 * dem Listenjahr; vorhandener bleibt), Merkmale (neue Fassung nur bei
 * Abweichung), „Unterlagen da am“ und Bemerkung der laufenden Periode (nur,
 * wo noch nichts steht), „entfällt“ für Perioden im Umfang und — auf Wunsch —
 * der Stand der laufenden Periode als Auftrag, wo es noch keinen gibt.
 */
final class ServicePlanListImporter {
    public const string KIND_YEAR = 'year';

    public const string KIND_PERIODS = 'periods';

    /** @var array<string, list<string>> Spalte → Kopfzeilen (normalisiert: klein, nur Buchstaben und Ziffern) */
    private const array COLUMNS = [
        'customer_number' => ['kundennummer', 'kundennr', 'kunde nr', 'mandantennummer', 'mandantennr', 'mandant', 'nummer', 'nr', 'customernumber', 'number', 'debitor', 'debitorennummer'],
        'customer_name' => ['kunde', 'kundenname', 'name', 'mandantenname', 'firma', 'customer', 'customername'],
        'service' => ['leistung', 'leistungsart', 'service', 'eintragsart', 'auftragsart'],
        'product_group' => ['produktgruppe', 'steuerart', 'productgroup'],
        'status' => ['stand', 'status', 'state'],
        'note' => ['bemerkung', 'bemerkungen', 'notiz', 'anmerkung', 'kommentar', 'note', 'notes'],
        'documents' => ['unterlagenda', 'unterlagen', 'unterlagenam', 'unterlagendaam', 'belegeda', 'belege', 'documents', 'documentsreceived'],
        'responsible' => ['zuständig', 'zustandig', 'zustaendig', 'bearbeiter', 'sachbearbeiter', 'verantwortlich', 'responsible'],
        'mandatory' => ['pflicht', 'mandatory'],
    ];

    /** @var array<string, list<string>> Stand → Schreibweisen (normalisiert); leer = kein Stand */
    private const array STATES = [
        'done' => ['erledigt', 'fertig', 'abgeschlossen', 'ok', 'x', 'ja', 'done', 'completed', 'eingereicht', 'versendet', 'übermittelt', 'uebermittelt'],
        'in_progress' => ['inarbeit', 'inbearbeitung', 'begonnen', 'laufend', 'inprogress'],
        'review' => ['zurprüfung', 'zurpruefung', 'prüfung', 'pruefung', 'inprüfung', 'review', 'inreview'],
        'waiting' => ['wartet', 'wartetaufkunde', 'unterlagenfehlen', 'rückfrage', 'rueckfrage', 'beimkunden', 'waiting'],
        'accepted' => ['angenommen', 'accepted'],
        'planned' => ['offen', 'geplant', 'angelegt', 'open', 'planned', 'todo'],
        'waived' => ['entfällt', 'entfaellt', 'entfallen', 'nichtnötig', 'nichtnoetig', 'keinbedarf', 'na', 'waived'],
    ];

    /** @var array<int, list<string>> Monat → Kopfzeilen (normalisiert) */
    private const array MONTHS = [
        1 => ['januar', 'jan', 'jänner', 'january', '01', '1'],
        2 => ['februar', 'feb', 'february', '02', '2'],
        3 => ['märz', 'maerz', 'mrz', 'mär', 'march', 'mar', '03', '3'],
        4 => ['april', 'apr', '04', '4'],
        5 => ['mai', 'may', '05', '5'],
        6 => ['juni', 'jun', 'june', '06', '6'],
        7 => ['juli', 'jul', 'july', '07', '7'],
        8 => ['august', 'aug', '08', '8'],
        9 => ['september', 'sep', 'sept', '09', '9'],
        10 => ['oktober', 'okt', 'october', 'oct', '10'],
        11 => ['november', 'nov', '11'],
        12 => ['dezember', 'dez', 'december', 'dec', '12'],
    ];

    public function __construct(
        private readonly ServicePlanCatalog $catalog,
        private readonly ServicePlanCells $cells,
        private readonly CustomerServiceManager $services,
        private readonly CustomerDeadlineProfileService $profiles,
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly ClassificationResolver $classifications,
        private readonly ClassificationRequirementValidator $requirements,
    ) {}

    /**
     * Vorschau: Spaltenzuordnung und je Zeile Kunde, Leistung, Stände, Merkmale und Meldungen.
     *
     * @param  array{header: list<string>, rows: array<int, list<string>>}  $table
     * @return array{kind: string, period_kind: PeriodKind, columns: array<string, int>, periods: array<string, int>, traits: array<string, int>, rule_set: string|null, current: PeriodKey, rows: list<array<string, mixed>>, errors: list<string>}
     */
    public function analyze(Organization $organization, array $table, int $year, ?ServiceKey $fixedService, CarbonImmutable $today): array {
        $orgId = (int) $organization->id;
        $columns = [];
        $periods = [];
        $periodKind = null;
        $errors = [];
        foreach ($table['header'] as $index => $title) {
            $normalized = self::normalize($title);
            foreach (self::COLUMNS as $column => $aliases) {
                if (! isset($columns[$column]) && in_array($normalized, array_map(self::normalize(...), $aliases), true)) {
                    $columns[$column] = $index;

                    continue 2;
                }
            }
            $period = $this->periodColumn($normalized, $year);
            if ($period !== null) {
                if ($periodKind !== null && $periodKind !== $period->kind) {
                    $errors[] = (string) __('service_plan.import.error.mixed_periods');
                }
                $periodKind ??= $period->kind;
                $periods[$period->key()] = $index;
            }
        }
        [$ruleSet, $traitColumns] = $this->traitColumns($organization, $table['header'], $columns, $periods);

        if (! isset($columns['customer_number'])) {
            $errors[] = (string) __('service_plan.import.error.no_customer_column');
        }
        if ($fixedService === null && ! isset($columns['service'])) {
            $errors[] = (string) __('service_plan.import.error.no_service_column');
        }

        $kind = $periods !== [] ? self::KIND_PERIODS : self::KIND_YEAR;
        $periodKind = $kind === self::KIND_YEAR ? PeriodKind::Year : ($periodKind ?? PeriodKind::Month);
        $current = $this->currentPeriod($periodKind, $year, $today);

        $customers = $this->customersByNumber($orgId, $table['rows'], $columns['customer_number'] ?? null);
        $members = $this->members($orgId);
        $seen = [];
        $rows = [];
        foreach ($table['rows'] as $line => $cells) {
            $value = static fn (string $column): string => isset($columns[$column]) ? trim($cells[$columns[$column]] ?? '') : '';
            $issues = [];
            $number = $value('customer_number');
            $customer = $number !== '' ? ($customers[$number] ?? null) : null;
            if ($number === '') {
                $issues[] = (string) __('service_plan.import.issue.no_number');
            } elseif ($customer === null) {
                $issues[] = (string) __('service_plan.import.issue.unknown_customer', ['number' => $number]);
            }

            $service = $fixedService;
            if ($service === null && $value('service') !== '') {
                [$service, $serviceIssue] = $this->resolveService($orgId, $value('service'), $value('product_group'));
                if ($serviceIssue !== null) {
                    $issues[] = $serviceIssue;
                }
            } elseif ($service === null) {
                $issues[] = (string) __('service_plan.import.issue.no_service');
            }
            if ($customer !== null && $service !== null) {
                $duplicate = $customer['id'] . '|' . $service->key();
                if (isset($seen[$duplicate])) {
                    $issues[] = (string) __('service_plan.import.issue.duplicate', ['line' => $seen[$duplicate]]);
                }
                $seen[$duplicate] ??= $line;
            }

            $states = [];
            $warnings = [];
            if ($kind === self::KIND_YEAR) {
                [$state, $warning] = $this->state($value('status'));
                $states[PeriodKey::of(PeriodKind::Year, $year)->key()] = $state;
                $warnings[] = $warning;
            } else {
                foreach ($periods as $periodKey => $index) {
                    [$state, $warning] = $this->state(trim($cells[$index] ?? ''));
                    $states[$periodKey] = $state;
                    $warnings[] = $warning;
                }
            }

            $responsible = null;
            if ($value('responsible') !== '') {
                $responsible = $members[mb_strtolower($value('responsible'))] ?? null;
                if ($responsible === null) {
                    $warnings[] = (string) __('service_plan.import.issue.unknown_responsible', ['name' => $value('responsible')]);
                }
            }
            $documents = null;
            if ($value('documents') !== '') {
                $documents = $this->date($value('documents'));
                if ($documents === null) {
                    $warnings[] = (string) __('service_plan.import.issue.bad_date', ['value' => $value('documents')]);
                }
            }
            [$traits, $traitWarnings] = $this->traits($ruleSet, $traitColumns, $cells);
            if ($ruleSet !== null && $traits !== [] && $customer !== null) {
                $invalid = $this->invalidTraits($orgId, $customer['id'], $ruleSet, $traits);
                if ($invalid !== null) {
                    $traitWarnings[] = $invalid;
                    $traits = [];
                }
            }

            $rows[] = [
                'line' => $line,
                'number' => $number,
                'name' => $value('customer_name'),
                'customer_id' => $customer['id'] ?? null,
                'customer_name' => $customer['name'] ?? null,
                'service' => $service?->key(),
                'service_label' => $service !== null ? $this->catalog->label($orgId, $service) : null,
                'states' => array_map(static fn (?ServicePlanCellState $state): ?string => $state?->value, $states),
                'note' => $value('note') !== '' ? $value('note') : null,
                'documents' => $documents?->toDateString(),
                'mandatory' => $value('mandatory') !== '' ? StringHelper::parseBool($value('mandatory')) : null,
                'responsible_id' => $responsible,
                'traits' => $traits,
                'issues' => $issues,
                'warnings' => array_values(array_filter([...$warnings, ...$traitWarnings])),
            ];
        }

        return [
            'kind' => $kind,
            'period_kind' => $periodKind,
            'columns' => $columns,
            'periods' => $periods,
            'traits' => $traitColumns,
            'rule_set' => $ruleSet?->key(),
            'current' => $current,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * Übernahme der fehlerfreien Zeilen (nach der Vorschau, unter einer Transaktion je Zeile).
     *
     * @param  array{header: list<string>, rows: array<int, list<string>>}  $table
     * @return array{rows: int, services: int, orders: int, waivers: int, notes: int, profiles: int, skipped: int}
     */
    public function apply(Organization $organization, array $table, int $year, ?ServiceKey $fixedService, bool $createOrders, User $actor, CarbonImmutable $today): array {
        $analysis = $this->analyze($organization, $table, $year, $fixedService, $today);
        $counts = ['rows' => 0, 'services' => 0, 'orders' => 0, 'waivers' => 0, 'notes' => 0, 'profiles' => 0, 'skipped' => 0];
        if ($analysis['errors'] !== []) {
            $counts['skipped'] = count($analysis['rows']);

            return $counts;
        }
        $ruleSet = $analysis['rule_set'] !== null ? $this->ruleSets->get($organization, $analysis['rule_set']) : null;
        /** @var PeriodKey $current */
        $current = $analysis['current'];
        $kind = $analysis['period_kind'];
        $validFrom = $kind === PeriodKind::Year ? PeriodKey::of(PeriodKind::Year, $year)->start() : $current->start();

        foreach ($analysis['rows'] as $row) {
            $service = is_string($row['service']) ? ServiceKey::fromKey($row['service']) : null;
            $customer = is_int($row['customer_id']) ? Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->find($row['customer_id']) : null;
            if ($row['issues'] !== [] || $service === null || ! $customer instanceof Customer) {
                $counts['skipped']++;

                continue;
            }
            $entryType = EntryType::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($service->entryTypeId);
            if (! $entryType instanceof EntryType) {
                $counts['skipped']++;

                continue;
            }

            DB::transaction(function () use ($row, $service, $customer, $entryType, $kind, $current, $validFrom, $year, $ruleSet, $createOrders, $actor, $organization, &$counts): void {
                if ($this->cells->scope($customer, $service, $kind === PeriodKind::Year ? PeriodKey::of(PeriodKind::Year, $year) : $current) === null) {
                    $template = $this->catalog->template((int) $organization->id, $service);
                    $mandatory = is_bool($row['mandatory']) ? $row['mandatory'] : ($template !== null ? $template->is_mandatory : true);
                    $this->services->add($customer, $entryType, $service->productGroup, $mandatory, $kind, $validFrom, null, null, $actor);
                    $counts['services']++;
                }
                if ($ruleSet !== null && $row['traits'] !== [] && $this->saveTraits($customer, $ruleSet, $row['traits'], $row['responsible_id'], $year, $actor)) {
                    $counts['profiles']++;
                }

                $notePeriod = $kind === PeriodKind::Year ? PeriodKey::of(PeriodKind::Year, $year) : $current;
                if (($row['note'] !== null || $row['documents'] !== null) && $this->cells->note($customer, $service, $notePeriod) === null) {
                    $this->cells->saveNote($customer, $service, $notePeriod, is_string($row['documents']) ? CarbonImmutable::parse($row['documents']) : null, $row['note'], $actor);
                    $counts['notes']++;
                }

                foreach ($row['states'] as $periodKey => $value) {
                    $state = is_string($value) ? ServicePlanCellState::tryFrom($value) : null;
                    $period = PeriodKey::tryParse((string) $periodKey);
                    if ($state === null || $period === null || $this->cells->scope($customer, $service, $period) === null || $this->cells->order($customer, $service, $period) !== null) {
                        continue;
                    }
                    if ($state === ServicePlanCellState::Waived) {
                        if ($this->cells->waiver($customer, $service, $period) === null) {
                            $this->cells->waive($customer, $service, $period, (string) __('service_plan.import.waiver_reason'), $actor);
                            $counts['waivers']++;
                        }

                        continue;
                    }
                    if ($createOrders && $period->equals($current) && $this->createOrder($customer, $entryType, $service, $period, $state, $row['responsible_id'], $actor)) {
                        $counts['orders']++;
                    }
                }
                $counts['rows']++;
            });
        }

        return $counts;
    }

    /** Laufende Periode der Liste: die heutige im laufenden Jahr, sonst die letzte (Vorjahre) bzw. erste (Folgejahre). */
    private function currentPeriod(PeriodKind $kind, int $year, CarbonImmutable $today): PeriodKey {
        if ($kind === PeriodKind::Year || $today->year === $year) {
            return PeriodKey::containing($kind, $today->year === $year ? $today : CarbonImmutable::create($year, 1, 1) ?? $today);
        }

        return PeriodKey::of($kind, $year, $year < $today->year ? $kind->perYear() : 1);
    }

    /** Spalte einer Periode (Monat, Quartal, Halbjahr) im Listenjahr; null, wenn keine. */
    private function periodColumn(string $normalized, int $year): ?PeriodKey {
        if (preg_match('/^(?:q|quartal|t)([1-4])$/u', $normalized, $match) === 1 || preg_match('/^([1-4])(?:quartal|q)$/u', $normalized, $match) === 1) {
            return PeriodKey::of(PeriodKind::Quarter, $year, (int) $match[1]);
        }
        if (preg_match('/^(?:h|hj|halbjahr|s)([12])$/u', $normalized, $match) === 1 || preg_match('/^([12])(?:halbjahr|hj|h)$/u', $normalized, $match) === 1) {
            return PeriodKey::of(PeriodKind::HalfYear, $year, (int) $match[1]);
        }
        if (preg_match('/^(\d{4})(\d{2})$/', $normalized, $match) === 1 && (int) $match[1] === $year && (int) $match[2] >= 1 && (int) $match[2] <= 12) {
            return PeriodKey::of(PeriodKind::Month, $year, (int) $match[2]);
        }
        foreach (self::MONTHS as $month => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return PeriodKey::of(PeriodKind::Month, $year, $month);
            }
        }

        return null;
    }

    /**
     * Merkmalsspalten: Kopfzeilen, die einer Bezeichnung oder einem Schlüssel
     * eines Merkmals des (ersten passenden) eingeschalteten Regelsatzes gleichen.
     *
     * @param  list<string>  $header
     * @param  array<string, int>  $columns
     * @param  array<string, int>  $periods
     * @return array{0: CustomerDeadlineRuleSet|null, 1: array<string, int>}
     */
    private function traitColumns(Organization $organization, array $header, array $columns, array $periods): array {
        $used = array_flip([...array_values($columns), ...array_values($periods)]);
        foreach ($this->ruleSets->enabled($organization) as $ruleSet) {
            $found = [];
            foreach ($ruleSet->traitSchema()->all() as $field) {
                foreach ($header as $index => $title) {
                    if (! isset($used[$index]) && in_array(self::normalize($title), [self::normalize($field->label), self::normalize($field->key)], true)) {
                        $found[$field->key] = $index;
                    }
                }
            }
            if ($found !== []) {
                return [$ruleSet, $found];
            }
        }

        return [null, []];
    }

    /**
     * Merkmalswerte einer Zeile; Auswahlfelder nur über Bezeichnung oder Schlüssel einer Option.
     *
     * @param  array<string, int>  $columns
     * @param  list<string>  $cells
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function traits(?CustomerDeadlineRuleSet $ruleSet, array $columns, array $cells): array {
        if ($ruleSet === null || $columns === []) {
            return [[], []];
        }
        $values = [];
        $warnings = [];
        foreach ($ruleSet->traitSchema()->all() as $field) {
            if (! isset($columns[$field->key])) {
                continue;
            }
            $raw = trim($cells[$columns[$field->key]] ?? '');
            if ($raw === '') {
                continue;
            }
            if ($field->options === []) {
                $values[$field->key] = $raw;

                continue;
            }
            $parts = $field->type === FieldType::Multichoice ? preg_split('/\s*[,;\/]\s*/u', $raw) : [$raw];
            $keys = [];
            foreach ($parts !== false ? $parts : [] as $part) {
                $option = $this->option($field, $part);
                if ($option === null) {
                    $warnings[] = (string) __('service_plan.import.issue.unknown_option', ['field' => $field->label, 'value' => $part]);

                    continue 2;
                }
                $keys[] = $option;
            }
            $values[$field->key] = $field->type === FieldType::Multichoice ? $keys : ($keys[0] ?? null);
        }

        return [$values, $warnings];
    }

    private function option(FieldDefinition $field, string $value): ?string {
        $needle = self::normalize($value);
        $matches = [];
        foreach ($field->options as $option) {
            $label = $field->optionLabels[$option] ?? $option;
            if (in_array($needle, [self::normalize($option), self::normalize($label)], true)) {
                $matches[$option] = true;
            }
        }

        return count($matches) === 1 ? (string) array_key_first($matches) : null;
    }

    /**
     * Prüft die Merkmale zusammen mit der geltenden Fassung gegen das
     * Feldschema; Meldung bei Verstoß (die Merkmale der Zeile entfallen dann).
     *
     * @param  array<string, mixed>  $traits
     */
    private function invalidTraits(int $orgId, int $customerId, CustomerDeadlineRuleSet $ruleSet, array $traits): ?string {
        $customer = Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->find($customerId);
        if (! $customer instanceof Customer) {
            return null;
        }
        $existing = $this->profiles->latest($customer, $ruleSet->key())?->traitValues()->toArray() ?? [];
        $validator = Validator::make(['traits' => array_merge($existing, $traits)], $this->profiles->rules($ruleSet));

        return $validator->fails()
            ? (string) __('service_plan.import.issue.invalid_traits', ['messages' => implode(' ', $validator->errors()->all())])
            : null;
    }

    /**
     * Merkmale speichern, wenn sie von der geltenden Fassung abweichen: ohne
     * Fassung ab dem Listenjahr, sonst ab heute (die alte Fassung wird
     * abgeschlossen). Nicht genannte Merkmale bleiben, wie sie sind.
     *
     * @param  array<string, mixed>  $traits
     */
    private function saveTraits(Customer $customer, CustomerDeadlineRuleSet $ruleSet, array $traits, ?int $responsibleId, int $year, User $actor): bool {
        $current = $this->profiles->latest($customer, $ruleSet->key());
        $existing = $current?->traitValues()->toArray() ?? [];
        $merged = array_merge($existing, $traits);
        if ($current !== null && $merged == $existing) {
            return false;
        }
        $validator = Validator::make(['traits' => $merged], $this->profiles->rules($ruleSet));
        if ($validator->fails()) {
            return false;
        }
        $responsible = $responsibleId !== null ? User::query()->find($responsibleId) : $current?->responsible;
        $validFrom = $current === null ? CarbonImmutable::create($year, 1, 1) ?? CarbonImmutable::today() : CarbonImmutable::today();
        try {
            $this->profiles->save($customer, $ruleSet, $merged, $validFrom, $responsible instanceof User ? $responsible : null, $actor);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    /** Auftrag der laufenden Periode im importierten Stand (Prüf- und Pflichtklassifikationen wie bei jeder Anlage). */
    private function createOrder(Customer $customer, EntryType $entryType, ServiceKey $service, PeriodKey $period, ServicePlanCellState $state, ?int $responsibleId, User $actor): bool {
        $status = match ($state) {
            ServicePlanCellState::Planned => Status::Open,
            ServicePlanCellState::Accepted => Status::Accepted,
            ServicePlanCellState::InProgress => Status::InProgress,
            ServicePlanCellState::Waiting => Status::Problem,
            ServicePlanCellState::Review => Status::InReview,
            ServicePlanCellState::Done => Status::Done,
            default => null,
        };
        if ($status === null) {
            return false;
        }
        $now = CarbonImmutable::now();
        $rank = array_search($status, [Status::Open, Status::Accepted, Status::InProgress, Status::Problem, Status::InReview, Status::Done], true);
        $data = [
            'organization_id' => $customer->organization_id,
            'user_id' => $actor->id,
            'entry_type_id' => $entryType->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $responsibleId,
            'title' => mb_substr($this->catalog->label((int) $customer->organization_id, $service) . ' ' . $period->label(), 0, 200),
            'content' => (string) __('service_plan.import.order_content'),
            'status' => $status->value,
            'period_key' => $period,
            'requires_review' => (bool) $entryType->requires_review,
            'mode' => Mode::Backlog,
            'location_mode' => LocationMode::Remote,
            'accepted_at' => $rank >= 1 ? $now : null,
            'accepted_by_user_id' => $rank >= 1 ? $actor->id : null,
            'started_at' => $rank >= 2 ? $now : null,
            'review_submitted_at' => $status === Status::InReview ? $now : null,
            'review_submitter_user_id' => $status === Status::InReview ? ($responsibleId ?? $actor->id) : null,
            'completed_at' => $status === Status::Done ? $now : null,
            'completed_by_user_id' => $status === Status::Done ? $actor->id : null,
        ];
        $candidate = new DiaryEntry($data);
        $candidate->organization_id = (int) $customer->organization_id;
        $values = $this->requirements->valuesFor($candidate);
        if ($service->productGroup !== null) {
            $values[ClassificationDomain::ProductGroup->value] = [$service->productGroup];
        }
        try {
            $this->requirements->assertSatisfied($candidate, ClassificationRequirementPhase::OnCreate, $values);
        } catch (ClassificationRequirementException) {
            return false;
        }

        $entry = new DiaryEntry($data);
        $entry->organization_id = (int) $customer->organization_id;
        $entry->save();
        $entry->assignProductGroup($service->productGroup);
        $entry->record('order.importedFromPlan', ['period_key' => $period->key(), 'state' => $state->value], $actor, extra: [
            'from_status' => null,
            'to_status' => $status->slug(),
            'actor_kind' => 'user',
        ]);
        if ($status === Status::Done) {
            OrderCompleted::dispatch((int) $entry->organization_id, (int) $entry->id);
        }

        return true;
    }

    /**
     * Leistung aus dem Listenwert: genau eine Vorlage oder Eintragsart (Bezeichnung oder Slug), optional mit Produktgruppe.
     *
     * @return array{0: ServiceKey|null, 1: string|null}
     */
    private function resolveService(int $orgId, string $value, string $productGroup): array {
        $needle = self::normalize($value);
        $group = $productGroup !== '' ? $this->productGroupCode($orgId, $productGroup) : null;
        if ($productGroup !== '' && $group === null) {
            return [null, (string) __('service_plan.import.issue.unknown_product_group', ['value' => $productGroup])];
        }
        $matches = [];
        foreach ($this->catalog->templates($orgId) as $template) {
            $names = [$template->label, $template->displayLabel(), ...array_values(is_array($template->label_i18n) ? $template->label_i18n : [])];
            if (in_array($needle, array_map(self::normalize(...), $names), true) && ($group === null || $template->product_group_code === $group)) {
                $matches[$template->serviceKey()->key()] = $template->serviceKey();
            }
        }
        if ($matches === []) {
            foreach (EntryType::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('is_active', true)->get(['id', 'slug', 'label']) as $type) {
                if (in_array($needle, [self::normalize((string) $type->label), self::normalize((string) $type->slug)], true)) {
                    $key = new ServiceKey((int) $type->id, $group);
                    $matches[$key->key()] = $key;
                }
            }
        }

        return match (count($matches)) {
            1 => [array_values($matches)[0], null],
            0 => [null, (string) __('service_plan.import.issue.unknown_service', ['value' => $value])],
            default => [null, (string) __('service_plan.import.issue.ambiguous_service', ['value' => $value])],
        };
    }

    private function productGroupCode(int $orgId, string $value): ?string {
        $needle = self::normalize($value);
        $matches = $this->classifications->list($orgId, ClassificationDomain::ProductGroup)
            ->filter(static fn (Classification $group): bool => in_array($needle, [self::normalize((string) $group->code), self::normalize((string) $group->label), self::normalize($group->displayLabel())], true));

        return $matches->count() === 1 ? (string) $matches->first()?->code : null;
    }

    /**
     * Stand aus dem Listenwert; unbekannte Werte melden (kein Stand), nie raten.
     *
     * @return array{0: ServicePlanCellState|null, 1: string|null}
     */
    private function state(string $value): array {
        if ($value === '') {
            return [null, null];
        }
        if (in_array($value, ['-', '–', '—', 'n/a', 'N/A'], true)) {
            return [ServicePlanCellState::Waived, null];
        }
        if (in_array($value, ['✓', '✔', '☑'], true)) {
            return [ServicePlanCellState::Done, null];
        }
        $normalized = self::normalize($value);
        foreach (self::STATES as $state => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return [ServicePlanCellState::from($state), null];
            }
        }
        if ($this->date($value) !== null) {
            // Ein Datum in der Standspalte heißt in den Listen „erledigt am“.
            return [ServicePlanCellState::Done, null];
        }

        return [null, (string) __('service_plan.import.issue.unknown_state', ['value' => $value])];
    }

    private function date(string $value): ?CarbonImmutable {
        $iso = DateHelper::normalizeToIso($value);

        return $iso !== null ? CarbonImmutable::parse($iso)->startOfDay() : null;
    }

    /**
     * Kunden der Liste nach genauer Kundennummer.
     *
     * @param  array<int, list<string>>  $rows
     * @return array<string, array{id: int, name: string}>
     */
    private function customersByNumber(int $orgId, array $rows, ?int $column): array {
        if ($column === null) {
            return [];
        }
        $numbers = array_values(array_unique(array_filter(array_map(static fn (array $cells): string => trim($cells[$column] ?? ''), $rows), static fn (string $n): bool => $n !== '')));
        $out = [];
        foreach (array_chunk($numbers, 500) as $chunk) {
            foreach (Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->whereIn('number', $chunk)->get(['id', 'name', 'number']) as $customer) {
                $out[(string) $customer->number] = ['id' => (int) $customer->id, 'name' => (string) $customer->name];
            }
        }

        return $out;
    }

    /** @return array<string, int> Name bzw. E-Mail (klein) → aktive Mitglieder; mehrdeutige Namen entfallen */
    private function members(int $orgId): array {
        $out = [];
        $ambiguous = [];
        foreach (User::query()->where('organization_id', $orgId)->whereNull('deactivated_at')->get(['id', 'name', 'email']) as $user) {
            foreach (array_filter([mb_strtolower((string) $user->name), mb_strtolower((string) $user->email)]) as $key) {
                if (isset($out[$key]) && $out[$key] !== (int) $user->id) {
                    $ambiguous[$key] = true;
                }
                $out[$key] = (int) $user->id;
            }
        }

        return array_diff_key($out, $ambiguous);
    }

    /** Kopfzeilen und Werte vergleichbar machen: klein, nur Buchstaben und Ziffern. */
    public static function normalize(string $value): string {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value)));
    }
}
