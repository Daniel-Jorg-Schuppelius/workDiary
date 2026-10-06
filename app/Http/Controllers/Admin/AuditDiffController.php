<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditDiffController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\ShiftType;
use App\Models\Time\{TimeAccount, WorkSchedule};
use App\Support\{MorphMap, Sqid};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Eloquent\{Builder, Collection as EloquentCollection};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Stammdaten-Versionsvergleich (MVP-528, Q1 „Protokollierung aller
 * Änderungen"): Änderungs-Timeline eines Datensatzes aus den vorhandenen
 * `audit_logs` (Hash-Kette) mit A/B-Vergleich zweier Stände — je Feld
 * ältester Vorher- und jüngster Nachher-Wert der Events dazwischen.
 * Bewusst NUR Anzeige, kein Undo: Korrekturen bleiben fachliche,
 * auditierte Vorgänge. Sensible Felder werden maskiert dargestellt.
 */
class AuditDiffController extends Controller {
    /** Anzeige-Maskierung zusätzlich zur Schreib-Maskierung des Auditable-Traits. */
    private const MASKED_PATTERNS = ['password', 'secret', 'token', 'tax_identification', 'social_security', 'recovery'];

    /** Timeline-Einträge je Seite. */
    public const PER_PAGE = 25;

    public function index(Request $request): View {
        $this->authorizeAdmin();

        $types = $this->types();
        $typeKey = (string) $request->input('type', '');
        $type = $types[$typeKey] ?? null;

        $records = collect();
        $record = null;
        $logs = null;
        $diff = null;
        $selectedA = null;
        $selectedB = null;
        $stateA = null;
        $stateB = null;

        if ($type !== null) {
            $records = ($type['records'])();

            $recordSqid = (string) $request->input('record', '');
            if ($recordSqid !== '') {
                $recordId = Sqid::decodeOrNumeric($type['class'], $recordSqid);
                $record = $records->firstWhere('id', $recordId);
            }

            if ($record !== null) {
                $logs = $this->timeline($type['class'], (int) $record->id)
                    ->with('user:id,name')
                    ->orderByDesc('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString();

                $selectedA = (int) $request->input('a', 0);
                $selectedB = (int) $request->input('b', 0);
                if ($selectedA > 0 && $selectedB > 0 && $selectedA > $selectedB) {
                    // A = älterer, B = jüngerer Stand — Reihenfolge normalisieren.
                    [$selectedA, $selectedB] = [$selectedB, $selectedA];
                }

                // Per Id statt aus der Seite: A und B dürfen auf verschiedenen Seiten liegen.
                $selectedIds = array_values(array_filter([$selectedA, $selectedB]));
                if ($selectedIds !== []) {
                    $states = $this->timeline($type['class'], (int) $record->id)->whereKey($selectedIds)->with('user:id,name')->get()->keyBy('id');
                    $stateA = $states->get($selectedA);
                    $stateB = $states->get($selectedB);
                }
                if ($stateA !== null && $stateB !== null) {
                    $diff = $this->diff($this->timeline($type['class'], (int) $record->id)
                        ->where('id', '>', $selectedA)
                        ->where('id', '<=', $selectedB)
                        ->orderBy('id')
                        ->get());
                }
            }
        }

        return view('admin.audit-diff.index', [
            'types' => $types,
            'typeKey' => $type !== null ? $typeKey : '',
            'records' => $records,
            'record' => $record,
            'recordSqid' => $record !== null && $type !== null ? Sqid::encode($type['class'], (int) $record->id) : '',
            'logs' => $logs,
            'diff' => $diff,
            'selectedA' => $selectedA,
            'selectedB' => $selectedB,
            'stateA' => $stateA,
            'stateB' => $stateB,
        ]);
    }

    /**
     * Audit-Einträge eines Datensatzes.
     *
     * @param  class-string  $class
     * @return Builder<AuditLog>
     */
    private function timeline(string $class, int $recordId): Builder {
        return AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey($class))
            ->where('auditable_id', $recordId);
    }

    /**
     * Feld-Diff über die Events zwischen zwei Audit-Ständen (A < id ≤ B,
     * aufsteigend): je Feld ältestes `before` und jüngstes `after`.
     *
     * @param  EloquentCollection<int, AuditLog>  $window
     * @return list<array{field: string, before: string, after: string}>
     */
    private function diff(EloquentCollection $window): array {
        /** @var array<string, array{before: mixed, after: mixed}> $fields */
        $fields = [];
        foreach ($window as $log) {
            $changes = (array) $log->changes;
            $before = (array) ($changes['before'] ?? []);
            $after = (array) ($changes['after'] ?? []);
            // created/deleted-Events tragen die Attribute flach — als "after" deuten.
            if ($before === [] && $after === [] && $changes !== []) {
                $after = $changes;
            }
            foreach ($after as $field => $value) {
                if (! isset($fields[$field])) {
                    $fields[$field] = ['before' => $before[$field] ?? null, 'after' => $value];
                } else {
                    $fields[$field]['after'] = $value;
                }
            }
        }

        $rows = [];
        foreach ($fields as $field => $pair) {
            if ($pair['before'] === $pair['after']) {
                continue;
            }
            $rows[] = [
                'field' => (string) $field,
                'before' => $this->display((string) $field, $pair['before']),
                'after' => $this->display((string) $field, $pair['after']),
            ];
        }
        usort($rows, static fn (array $x, array $y): int => strcmp($x['field'], $y['field']));

        return $rows;
    }

    private function display(string $field, mixed $value): string {
        foreach (self::MASKED_PATTERNS as $pattern) {
            if (str_contains($field, $pattern)) {
                return $value === null || $value === '' ? '—' : '•••';
            }
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return JsonHelper::encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Typ-Whitelist: Slug → Klasse, Label und Datensatz-Lader (org-gescoped).
     *
     * @return array<string, array{class: class-string, label: string, records: callable}>
     */
    private function types(): array {
        $orgId = $this->organizationId();

        return [
            'member' => [
                'class' => User::class,
                'label' => (string) __('Mitglied'),
                'records' => fn () => User::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'customer' => [
                'class' => Customer::class,
                'label' => (string) __('Kunde'),
                'records' => fn () => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            ],
            'work-schedule' => [
                'class' => WorkSchedule::class,
                'label' => (string) __('Arbeitszeit-Modell'),
                'records' => fn () => WorkSchedule::query()
                    ->with('user:id,name')
                    ->orderByDesc('valid_from')
                    ->limit(500)
                    ->get()
                    ->map(function (WorkSchedule $schedule): WorkSchedule {
                        $schedule->setAttribute('name', ($schedule->user->name ?? '#' . $schedule->user_id) . ' · ' . __('ab') . ' ' . $schedule->valid_from->format('d.m.Y'));

                        return $schedule;
                    }),
            ],
            'shift-type' => [
                'class' => ShiftType::class,
                'label' => (string) __('Schichttyp'),
                'records' => fn () => ShiftType::query()->orderBy('name')->get(['id', 'name']),
            ],
            'time-account' => [
                'class' => TimeAccount::class,
                'label' => (string) __('Zeitkonto'),
                'records' => fn () => TimeAccount::query()->orderBy('name')->get(['id', 'name']),
            ],
            'organization' => [
                'class' => Organization::class,
                'label' => (string) __('Organisation'),
                'records' => fn () => Organization::query()->whereKey($orgId)->get(['id', 'name']),
            ],
        ];
    }

    private function authorizeAdmin(): void {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && $user->isAdmin(), 403);
    }

    private function organizationId(): int {
        /** @var User $user */
        $user = Auth::user();

        return (int) $user->organization_id;
    }
}
