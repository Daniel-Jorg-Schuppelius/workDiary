<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : add-sqid-decoding-to-requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Phase-3b: Stattet FormRequest-Klassen mit dem
 * `DecodesSqidInputs`-Trait aus und füllt `$sqidFields` aus den
 * vorhandenen `exists:<table>,id`-Validierungs-Rules.
 *
 * Aufruf:
 *   php scripts/add-sqid-decoding-to-requests.php           # Dry-Run
 *   php scripts/add-sqid-decoding-to-requests.php --apply
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);

/** Tabelle → vollqualifizierte Modellklasse (HasSqid). */
$tableToModel = [
    'users' => 'App\\Models\\User',
    'customers' => 'App\\Models\\Customer',
    'tags' => 'App\\Models\\Tag',
    'diary_entries' => 'App\\Models\\DiaryEntry',
    'assets' => 'App\\Models\\Asset',
    'tasks' => 'App\\Models\\Task',
    'invoices' => 'App\\Models\\Invoice',
    'materials' => 'App\\Models\\Material',
    'vehicles' => 'App\\Models\\Vehicle',
    'sites' => 'App\\Models\\Site',
    'buildings' => 'App\\Models\\Building',
    'floors' => 'App\\Models\\Floor',
    'rooms' => 'App\\Models\\Room',
    'tours' => 'App\\Models\\Tour',
    'shift_types' => 'App\\Models\\ShiftType',
    'cleaning_profiles' => 'App\\Models\\CleaningProfile',
    'qualifications' => 'App\\Models\\Qualification',
    'sick_leaves' => 'App\\Models\\SickLeave',
    'invoice_templates' => 'App\\Models\\InvoiceTemplate',
    'expense_categories' => 'App\\Models\\ExpenseCategory',
    'entry_types' => 'App\\Models\\EntryType',
    'event_categories' => 'App\\Models\\EventCategory',
    'classifications' => 'App\\Models\\Classification',
    'milestones' => 'App\\Models\\Milestone',
    'recurrence_rules' => 'App\\Models\\RecurrenceRule',
    'attendances' => 'App\\Models\\Attendance',
    'scheduled_shifts' => 'App\\Models\\ScheduledShift',
    'on_call_shifts' => 'App\\Models\\OnCallShift',
    'service_tickets' => 'App\\Models\\ServiceTicket',
    'holidays' => 'App\\Models\\Holiday',
    'software' => 'App\\Models\\Software',
    'expenses' => 'App\\Models\\Expense',
    'comments' => 'App\\Models\\Comment',
    'attachments' => 'App\\Models\\Attachment',
    'protocols' => 'App\\Models\\Protocol',
    'per_diem_trips' => 'App\\Models\\PerDiemTrip',
    'emergency_assignments' => 'App\\Models\\EmergencyAssignment',
    'user_groups' => 'App\\Models\\UserGroup',
    'organizations' => 'App\\Models\\Organization',
    'travel_logs' => 'App\\Models\\TravelLog',
    'energy_logs' => 'App\\Models\\EnergyLog',
    'activity_categories' => 'App\\Models\\ActivityCategory',
    'duty_plans' => 'App\\Models\\DutyPlan',
    'time_entries' => 'App\\Models\\TimeEntry',
    'timesheets' => 'App\\Models\\Timesheet',
    'events' => 'App\\Models\\Event',
    // Project hat KEINEN HasSqid:
    'projects' => null,
];

$files = glob($root . '/app/Http/Requests/*.php') ?: [];
$files = array_merge($files, glob($root . '/app/Http/Requests/**/*.php') ?: []);

/** @var array<string, array<string, string>> $report */
$report = [];

foreach ($files as $file) {
    $rel = substr($file, strlen($root) + 1);
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    // Bereits Trait? -> skip (manuelles Review nötig)
    if (str_contains($content, 'DecodesSqidInputs')) {
        continue;
    }

    // Sammle Felder: 'NAME_id' => [..., 'exists:TABLE,id', ...]   oder Rule::exists('TABLE', ...)
    $sqidFields = [];
    if (preg_match_all(
        "/'(\w+(?:_id|_ids))'\s*=>\s*\[[^\]]*?(?:'exists:(\w+),id'|Rule::exists\('(\w+)')/s",
        $content,
        $matches,
        PREG_SET_ORDER,
    )) {
        foreach ($matches as $m) {
            $field = $m[1];
            $table = (string) ($m[2] ?: ($m[3] ?? ''));
            if ($table === '' || ! array_key_exists($table, $tableToModel)) {
                continue;
            }
            $cls = $tableToModel[$table];
            if ($cls === null) {
                continue;
            }
            // Polymorphe / id arrays:
            $sqidFields[$field] = $cls;
        }
    }

    if ($sqidFields === []) {
        continue;
    }

    // Build trait insertion
    $traitImport = "use App\\Http\\Requests\\Concerns\\DecodesSqidInputs;\n";

    // Insert trait import after existing 'use ' lines block
    if (! str_contains($content, 'use App\\Http\\Requests\\Concerns\\DecodesSqidInputs;')) {
        // place before "use Illuminate\\Foundation\\Http\\FormRequest;" or after last use
        $content = preg_replace(
            '/(^use [^\n]+;\n)(?!use )/m',
            "$1" . $traitImport,
            $content,
            1,
        ) ?? $content;
    }

    // Insert "use DecodesSqidInputs;" + $sqidFields after "class X extends FormRequest {"
    $propertyLines = "    /** @var array<string, class-string> */\n    protected array \$sqidFields = [\n";
    foreach ($sqidFields as $field => $cls) {
        $propertyLines .= "        '" . $field . "' => \\" . $cls . "::class,\n";
    }
    $propertyLines .= "    ];\n";

    $content = preg_replace(
        '/(class\s+\w+\s+extends\s+FormRequest\s*\{\n)/',
        "$1    use DecodesSqidInputs;\n\n" . $propertyLines . "\n",
        $content,
        1,
    ) ?? $content;

    $report[$rel] = $sqidFields;
    if ($apply) {
        file_put_contents($file, $content);
    }
}

ksort($report);
echo ($apply ? 'APPLIED' : 'DRY-RUN') . ' — Updated FormRequests: ' . count($report) . "\n";
foreach ($report as $f => $fields) {
    echo $f . "\n";
    foreach ($fields as $field => $cls) {
        echo '    ' . $field . ' => ' . $cls . "\n";
    }
}
if (! $apply) {
    echo "\nRun again with --apply to write changes.\n";
}
