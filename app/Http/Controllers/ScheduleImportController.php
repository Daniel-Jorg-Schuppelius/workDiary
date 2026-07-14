<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{ScheduledShift, ShiftType, User};
use App\Support\Setting;
use Carbon\Carbon;
use CommonToolkit\Parsers\CSVDocumentParser;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Session};
use Illuminate\View\View;

class ScheduleImportController extends Controller {
    /** Step 1 – show upload form */
    public function show(): View {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        return view('schedule.import.index');
    }

    /** Step 2 – parse file, show column-mapping form */
    public function preview(Request $request): View|RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:' . (int) Setting::get('uploads.csv_import_kb', 10240)],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->store('schedule-imports', 'local');

        $rows = $this->parseFile(storage_path("app/{$path}"), $extension);

        if (empty($rows)) {
            return back()->withErrors(['file' => __('Die Datei enthält keine verwertbaren Zeilen.')]);
        }

        $headers = $rows[0];
        $preview = array_slice($rows, 1, 20);
        $remaining = max(0, count($rows) - 1);

        Session::put('schedule_import', [
            'path' => $path,
            'extension' => $extension,
            'headers' => $headers,
        ]);

        $shiftTypes = ShiftType::active()->orderBy('name')->pluck('name', 'id');
        $users = User::orderBy('name')->pluck('name', 'id');

        return view('schedule.import.preview', [
            'headers' => $headers,
            'preview' => $preview,
            'remaining' => $remaining,
            'shiftTypes' => $shiftTypes,
            'users' => $users,
        ]);
    }

    /** Step 3 – confirm import with column mapping */
    public function confirm(Request $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        $import = Session::get('schedule_import');
        if (! $import) {
            return redirect()->route('schedule.import')->withErrors(['file' => __('Sitzung abgelaufen. Bitte erneut hochladen.')]);
        }

        $mapping = $request->validate([
            'map' => ['required', 'array'],
            'map.*' => ['required', 'string'],
        ])['map'];

        $rows = $this->parseFile(storage_path("app/{$import['path']}"), $import['extension']);
        $data = array_slice($rows, 1); // skip header

        $users = User::inCurrentOrganization()->pluck('id', 'name');
        $userEmails = User::inCurrentOrganization()->pluck('id', 'email');
        $shiftTypes = ShiftType::pluck('id', 'name');

        $imported = 0;
        $errors = [];

        foreach ($data as $idx => $row) {
            $line = $idx + 2; // 1-based, +1 for header
            try {
                $mapped = $this->mapRow($row, $mapping);
                $userId = $this->resolveUser($mapped['user'] ?? null, $users, $userEmails);

                if (! $userId) {
                    $errors[] = __('Zeile :line: Mitarbeiter ":user" nicht gefunden.', ['line' => $line, 'user' => $mapped['user'] ?? '?']);

                    continue;
                }

                if (empty($mapped['date'])) {
                    $errors[] = __('Zeile :line: Datum fehlt.', ['line' => $line]);

                    continue;
                }

                try {
                    $date = Carbon::parse($mapped['date'])->format('Y-m-d');
                } catch (\Exception) {
                    $errors[] = __('Zeile :line: Ungültiges Datum ":date".', ['line' => $line, 'date' => $mapped['date']]);

                    continue;
                }

                $shiftTypeId = null;
                if (! empty($mapped['shift_type'])) {
                    $shiftTypeId = $shiftTypes[$mapped['shift_type']] ?? null;
                }

                ScheduledShift::updateOrCreate(
                    ['user_id' => $userId, 'date' => $date],
                    [
                        'shift_type_id' => $shiftTypeId,
                        'start_time' => $this->normalizeTime($mapped['start_time'] ?? null),
                        'end_time' => $this->normalizeTime($mapped['end_time'] ?? null),
                        'note' => $mapped['note'] ?? null,
                        'status' => ScheduledShiftStatus::Draft,
                        'created_by' => $auth->id,
                        'updated_by' => $auth->id,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = __('Zeile :line: :msg', ['line' => $line, 'msg' => $e->getMessage()]);
            }
        }

        Session::forget('schedule_import');

        $message = __(':count Schichten importiert.', ['count' => $imported]);
        if (! empty($errors)) {
            Session::flash('import_errors', $errors);
        }

        return redirect()->route('schedule.index')->with('success', $message);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @return array<int, array<int, string>>
     */
    private function parseFile(string $path, string $extension): array {
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseSpreadsheet($path);
        }

        return $this->parseCsv($path);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsv(string $path): array {
        $rows = [];
        // Toolkit-Parser inkl. Header-Zeile (hasHeader: false) — der Aufrufer
        // verarbeitet die Kopfzeile selbst, wie beim Spreadsheet-Pfad.
        foreach (CSVDocumentParser::streamRows($path, ';', hasHeader: false) as $line) {
            $rows[] = array_map(static fn($field): string => (string) $field->getValue(), $line->getFields());
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseSpreadsheet(string $path): array {
        $factory = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (! class_exists($factory)) {
            abort(500, 'phpoffice/phpspreadsheet ist nicht installiert.');
        }

        $spreadsheet = $factory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = (string) $cell->getValue();
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, string>  $mapping  column-index → field-name
     * @return array<string, string|null>
     */
    private function mapRow(array $row, array $mapping): array {
        $result = [];
        foreach ($mapping as $colIndex => $fieldName) {
            if ($fieldName === 'skip') {
                continue;
            }
            $result[$fieldName] = $row[(int) $colIndex] ?? null;
        }

        return $result;
    }

    /**
     * @param  Collection<string, int>  $byName
     * @param  Collection<string, int>  $byEmail
     */
    private function resolveUser(?string $value, $byName, $byEmail): ?int {
        if (empty($value)) {
            return null;
        }
        $value = trim($value);

        return $byName[$value] ?? $byEmail[$value] ?? null;
    }

    private function normalizeTime(?string $value): ?string {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Exception) {
            return null;
        }
    }
}
