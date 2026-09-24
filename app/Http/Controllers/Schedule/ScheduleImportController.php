<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Schedule\{ScheduledShift, ShiftType};
use App\Support\{ErrorText, Setting};
use Carbon\Carbon;
use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Parsers\{CSVDocumentParser, XLSXDocumentParser};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Session, Storage};
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
        if ($path === false) {
            return back()->withErrors(['file' => __('Die Datei konnte nicht gespeichert werden.')]);
        }

        // Die local-Disk liegt unter storage/app/private — storage_path("app/…") ging daneben.
        $rows = $this->parseFile(Storage::disk('local')->path($path), $extension);

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
        $users = User::inCurrentOrganization()->orderBy('name')->pluck('name', 'id');

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
            'map.*' => ['required', 'string', 'in:skip,date,user,shift_type,start_time,end_time,note'],
        ])['map'];

        // Ohne Datum und Mitarbeiter scheiterte jede Zeile einzeln — gleich melden.
        if (! in_array('date', $mapping, true) || ! in_array('user', $mapping, true)) {
            return redirect()->route('schedule.import')
                ->withErrors(['file' => __('Datum und Mitarbeiter müssen einer Spalte zugeordnet sein.')]);
        }

        $rows = $this->parseFile(Storage::disk('local')->path($import['path']), $import['extension']);
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
                $errors[] = __('Zeile :line: :msg', ['line' => $line, 'msg' => ErrorText::for($e)]);
            }
        }

        Session::forget('schedule_import');
        Storage::disk('local')->delete($import['path']);

        $message = __(':count Schichten importiert.', ['count' => $imported]);
        if (! empty($errors)) {
            Session::flash('import_errors', $errors);
        }

        return redirect()->toList('schedule.index')->with('success', $message);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @return array<int, array<int, string>>
     */
    private function parseFile(string $path, string $extension): array {
        return match ($extension) {
            'xlsx' => $this->parseXlsx($path),
            'xls' => $this->parseSpreadsheet($path),
            default => $this->parseCsv($path),
        };
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
     * .xlsx über den Toolkit-Parser (erstes Blatt, inkl. Kopfzeile): Datums-
     * zellen kommen als `Y-m-d` statt als Excel-Seriennummer, Uhrzeiten als
     * `Y-m-d H:i:s` — beides versteht Carbon::parse() in confirm().
     *
     * @return array<int, array<int, string>>
     */
    private function parseXlsx(string $path): array {
        $sheet = XLSXDocumentParser::fromFile($path, hasHeader: false, sheetIndex: 0)->getFirstSheet();
        $rows = [];
        foreach ($sheet?->getRows() ?? [] as $row) {
            $rows[] = array_map(static fn (Cell $cell): string => $cell->toCanonicalString(), $row->getCells());
        }

        return $rows;
    }

    /**
     * Altes .xls-Format: nur PhpSpreadsheet liest es.
     *
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
