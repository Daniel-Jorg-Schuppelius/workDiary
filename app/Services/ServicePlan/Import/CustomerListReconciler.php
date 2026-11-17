<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerListReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Import;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};
use Illuminate\Support\Facades\DB;

/**
 * Abgleich des Kundenstamms gegen eine Kundenliste aus einem Fremdsystem
 * (Feature 199, MVP-1640), Spaltenvorlage DATEV-Mandantenexport
 * (Mandantennummer, Name, Vorname; auch die Stammdaten-Spalten
 * „Name (Adressattyp Unternehmen)“ usw.).
 *
 * Zuordnung nur über die genaue Kundennummer (`customers.number`). Ergebnis
 * sind Vorschläge: neue Kunden (Nummer unbekannt), umbenannte (Nummer gleich,
 * Name anders) und fehlende (Nummer im Bestand, nicht in der Liste). Nichts
 * wird überschrieben oder angelegt, bevor eine Person den einzelnen Vorschlag
 * bestätigt; fehlende Kunden werden nur genannt.
 */
final class CustomerListReconciler {
    public const string NEW = 'new';

    public const string RENAMED = 'renamed';

    public const string UNCHANGED = 'unchanged';

    public const string INVALID = 'invalid';

    /** Höchstzahl der fehlenden Kunden in der Vorschau. */
    public const int MISSING_LIMIT = 200;

    /** @var array<string, list<string>> Spalte → Kopfzeilen (normalisiert über {@see ServicePlanListImporter::normalize()}) */
    private const array COLUMNS = [
        'number' => ['mandantennummer', 'mandantennr', 'mandant', 'kundennummer', 'kundennr', 'nummer', 'nr', 'konto', 'debitor', 'debitorennummer', 'number', 'customernumber'],
        'company' => ['nameadressattypunternehmen', 'unternehmen', 'firma', 'firmenname', 'mandantenname', 'kurzbezeichnung', 'name', 'customer', 'kunde'],
        'last_name' => ['nameadressattypnatürlperson', 'nachname', 'lastname'],
        'first_name' => ['vornameadressattypnatürlperson', 'vorname', 'firstname'],
    ];

    /**
     * @param  array{header: list<string>, rows: array<int, list<string>>}  $table
     * @return array{rows: list<array{line: int, number: string, name: string, status: string, customer_id: int|null, current_name: string|null, issue: string|null}>, missing: list<array{id: int, number: string, name: string}>, missing_total: int, counts: array<string, int>, errors: list<string>}
     */
    public function analyze(Organization $organization, array $table): array {
        $columns = [];
        foreach ($table['header'] as $index => $title) {
            $normalized = ServicePlanListImporter::normalize($title);
            foreach (self::COLUMNS as $column => $aliases) {
                if (! isset($columns[$column]) && in_array($normalized, $aliases, true)) {
                    $columns[$column] = $index;

                    continue 2;
                }
            }
        }
        // „Name“ neben „Vorname“ ist der Nachname einer Person, nicht der Firmenname.
        if (isset($columns['company'], $columns['first_name']) && ! isset($columns['last_name'])
            && ServicePlanListImporter::normalize($table['header'][$columns['company']]) === 'name') {
            $columns['last_name'] = $columns['company'];
            unset($columns['company']);
        }
        $errors = [];
        if (! isset($columns['number'])) {
            $errors[] = (string) __('service_plan.reconcile.error.no_number_column');
        }
        if (! isset($columns['company']) && ! isset($columns['last_name'])) {
            $errors[] = (string) __('service_plan.reconcile.error.no_name_column');
        }

        $existing = [];
        foreach (Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->whereNotNull('number')->where('number', '!=', '')->get(['id', 'name', 'number', 'archived_at']) as $customer) {
            $existing[(string) $customer->number] = $customer;
        }

        $rows = [];
        $seen = [];
        $counts = [self::NEW => 0, self::RENAMED => 0, self::UNCHANGED => 0, self::INVALID => 0];
        foreach ($table['rows'] as $line => $cells) {
            $cell = static fn (string $column): string => isset($columns[$column]) ? trim($cells[$columns[$column]] ?? '') : '';
            $number = $cell('number');
            $name = $cell('company') !== '' ? $cell('company') : PersonNameHelper::join(['first_name' => $cell('first_name'), 'last_name' => $cell('last_name')]);
            $customer = $number !== '' ? ($existing[$number] ?? null) : null;
            [$status, $issue] = match (true) {
                $number === '' => [self::INVALID, (string) __('service_plan.reconcile.issue.no_number')],
                $name === '' => [self::INVALID, (string) __('service_plan.reconcile.issue.no_name')],
                isset($seen[$number]) => [self::INVALID, (string) __('service_plan.reconcile.issue.duplicate', ['line' => $seen[$number]])],
                $customer === null => [self::NEW, null],
                self::sameName((string) $customer->name, $name) => [self::UNCHANGED, null],
                default => [self::RENAMED, null],
            };
            $seen[$number] ??= $line;
            $counts[$status]++;
            $rows[] = [
                'line' => $line,
                'number' => $number,
                'name' => $name,
                'status' => $status,
                'customer_id' => $customer !== null ? (int) $customer->id : null,
                'current_name' => $customer?->name,
                'issue' => $issue,
            ];
        }

        $missing = [];
        $missingTotal = 0;
        foreach ($existing as $number => $customer) {
            if (isset($seen[(string) $number]) || $customer->archived_at !== null) {
                continue;
            }
            $missingTotal++;
            if (count($missing) < self::MISSING_LIMIT) {
                $missing[] = ['id' => (int) $customer->id, 'number' => (string) $number, 'name' => (string) $customer->name];
            }
        }
        usort($missing, static fn (array $a, array $b): int => strnatcasecmp($a['number'], $b['number']));
        $counts['missing'] = $missingTotal;

        return ['rows' => $rows, 'missing' => $missing, 'missing_total' => $missingTotal, 'counts' => $counts, 'errors' => $errors];
    }

    /**
     * Bestätigte Vorschläge übernehmen: neue Kunden anlegen (Nummer und Name),
     * umbenannte umbenennen. Nur, was die erneute Prüfung noch so vorschlägt.
     *
     * @param  array{header: list<string>, rows: array<int, list<string>>}  $table
     * @param  list<string>  $create  Kundennummern
     * @param  list<string>  $rename  Kundennummern
     * @return array{created: int, renamed: int}
     */
    public function apply(Organization $organization, array $table, array $create, array $rename, User $actor, bool $mayCreate, bool $mayRename): array {
        $analysis = $this->analyze($organization, $table);
        $result = ['created' => 0, 'renamed' => 0];
        if ($analysis['errors'] !== []) {
            return $result;
        }
        DB::transaction(function () use ($analysis, $organization, $create, $rename, $actor, $mayCreate, $mayRename, &$result): void {
            foreach ($analysis['rows'] as $row) {
                if ($mayCreate && $row['status'] === self::NEW && in_array($row['number'], $create, true)) {
                    Customer::query()->create([
                        'organization_id' => $organization->id,
                        'name' => $row['name'],
                        'number' => $row['number'],
                        'created_by' => $actor->id,
                    ]);
                    $result['created']++;
                }
                if ($mayRename && $row['status'] === self::RENAMED && $row['customer_id'] !== null && in_array($row['number'], $rename, true)) {
                    $customer = Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->find($row['customer_id']);
                    if ($customer instanceof Customer) {
                        $customer->update(['name' => $row['name']]);
                        $result['renamed']++;
                    }
                }
            }
        });

        return $result;
    }

    /** Gleicher Name ohne Rücksicht auf Groß-/Kleinschreibung und Leerraum. */
    private static function sameName(string $a, string $b): bool {
        $normalize = static fn (string $value): string => StringHelper::collapseWhitespace(mb_strtolower(trim($value)), unicode: true);

        return $normalize($a) === $normalize($b);
    }
}
