<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Console\Commands;

use App\Legacy\Services\LegacySupportImportService;
use App\Legacy\Support\LegacyClock;
use App\Models\Diary\{DiaryEntry, EmergencyAssignment, OnCallShift};
use App\Models\Platform\{Organization, User};
use CommonToolkit\Helper\Data\StringHelper as ToolkitStringHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class LegacyImportCommand extends Command {
    protected $signature = 'legacy:import
        {--users : Nur Benutzer importieren}
        {--diary : Nur Tagebuch-Einträge importieren}
        {--shifts : Nur Bereitschaften importieren}
        {--assignments : Nur Notdienste importieren}
        {--support : Nur das Support-Portal importieren (Kunden und Aufträge als Servicetickets, braucht --organization)}
        {--organization= : Zielorganisation des Support-Portals (ID oder Kürzel); ohne sie überspringt der Gesamtlauf das Portal}
        {--fresh : Vorhandene Daten vor dem Import löschen (gilt nicht für das Support-Portal)}';

    protected $description = 'Importiert Benutzer, Tagebuch, Bereitschaften, Notdienste und das Support-Portal aus der Legacy-DB (idempotent).';

    private string $legacyEncoding = 'latin1';

    public function handle(): int {
        if (! filled(config('database.connections.legacy.database'))) {
            $this->error('Legacy-DB ist nicht konfiguriert. Bitte LEGACY_DB_* in der .env setzen.');

            return self::FAILURE;
        }

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error('Legacy-DB nicht erreichbar: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->legacyEncoding = (string) config('database.connections.legacy.charset', 'latin1');

        $any = $this->option('users') || $this->option('diary') || $this->option('shifts') || $this->option('assignments') || $this->option('support');
        $importUsers = $this->option('users') || ! $any;
        $importDiary = $this->option('diary') || ! $any;
        $importShifts = $this->option('shifts') || ! $any;
        $importAssignments = $this->option('assignments') || ! $any;

        // Kunden und Tickets gehören einer Organisation; ohne Ziel kein Portal-Import.
        $supportOrganization = null;
        $rawOrganization = trim((string) $this->option('organization'));
        if ($rawOrganization !== '') {
            $supportOrganization = Organization::query()->withoutGlobalScopes()
                ->where(fn($query) => $query->where('slug', $rawOrganization)
                    ->when(ctype_digit($rawOrganization), fn($inner) => $inner->orWhere('id', (int) $rawOrganization)))
                ->first();
            if ($supportOrganization === null) {
                $this->error("Organisation „{$rawOrganization}“ nicht gefunden.");

                return self::FAILURE;
            }
        } elseif ($this->option('support')) {
            $this->error('Für das Support-Portal bitte die Zielorganisation mit --organization=<ID|Kürzel> angeben.');

            return self::FAILURE;
        }
        $importSupport = $supportOrganization !== null && ($this->option('support') || ! $any);

        if ($this->option('fresh') && $this->confirm('Wirklich alle vorhandenen Daten löschen vor dem Import?', false)) {
            if ($importAssignments) {
                EmergencyAssignment::query()->delete();
                $this->line('  Notdienste gelöscht.');
            }
            if ($importShifts) {
                OnCallShift::query()->delete();
                $this->line('  Bereitschaften gelöscht.');
            }
            if ($importDiary) {
                DiaryEntry::query()->delete();
                $this->line('  Diary-Einträge gelöscht.');
            }
            if ($importUsers) {
                User::whereNotNull('legacy_user_id')->delete();
                $this->line('  Legacy-Benutzer gelöscht.');
            }
        }

        if ($importUsers) {
            $this->importUsers();
        }
        if ($importDiary) {
            $this->importDiary();
        }
        if ($importShifts) {
            $this->importShifts();
        }
        if ($importAssignments) {
            $this->importAssignments();
        }
        if ($importSupport) {
            $this->importSupport($supportOrganization);
        } elseif (! $any) {
            $this->line('  Support-Portal übersprungen: --organization fehlt.');
        }

        $this->newLine();
        $this->info('Import abgeschlossen.');

        return self::SUCCESS;
    }

    private function importUsers(): void {
        $this->info('Importiere Benutzer ...');

        $legacyUsers = DB::connection('legacy')->table('user')->get();

        $bar = $this->output->createProgressBar($legacyUsers->count());
        $bar->start();

        foreach ($legacyUsers as $legacy) {
            // Nur Neuanlagen: bestehende Konten behalten Name, E-Mail und Passwort
            // (vorher setzte jeder Lauf verknüpfte Konten zurück und sperrte sie aus).
            $user = User::query()->firstOrNew(['legacy_user_id' => $legacy->id]);
            if (! $user->exists) {
                $user->fill([
                    'name' => $this->normalizeLegacyText((string) ($legacy->uname ?? '')),
                    'email' => $this->normalizeLegacyText((string) ($legacy->email ?: ($legacy->uname . '@workdiary.local'))),
                    'password' => Hash::make(Str::random(40)),
                    'must_change_password' => true,
                ])->save();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line('  ' . $legacyUsers->count() . ' Benutzer verarbeitet.');
    }

    /** @return array<int, int> */
    private function userMap(): array {
        return User::whereNotNull('legacy_user_id')->pluck('id', 'legacy_user_id')->toArray();
    }

    private function importDiary(): void {
        $this->info('Importiere Tagebuch-Einträge ...');

        $userMap = $this->userMap();
        $total = DB::connection('legacy')->table('tagebuch')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        DB::connection('legacy')->table('tagebuch')->orderBy('id')->chunk(200, function ($rows) use ($userMap, $bar, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $userId = $userMap[$row->user] ?? null;
                if (! $userId) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                DiaryEntry::updateOrCreate(
                    ['legacy_id' => $row->id],
                    [
                        'user_id' => $userId,
                        'content' => $this->normalizeLegacyText((string) ($row->inhalt ?? '')),
                        'response' => $this->nullableLegacyText($row->antwort),
                        'status' => (int) $row->gelesen,
                        'start_at' => $this->dt($row->von),
                        'end_at' => $this->dt($row->bis),
                        'created_at' => $this->dt($row->aktuell) ?? now(),
                        'updated_at' => $this->dt($row->aktuell) ?? now(),
                    ]
                );
                $imported++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->line("  {$imported} Tagebuch-Einträge importiert, {$skipped} übersprungen.");
    }

    private function importShifts(): void {
        $this->info('Importiere Bereitschaften ...');

        $userMap = $this->userMap();
        $total = DB::connection('legacy')->table('bereit')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        DB::connection('legacy')->table('bereit')->orderBy('id')->chunk(200, function ($rows) use ($userMap, $bar, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $userId = $userMap[$row->user] ?? null;
                $start = $this->dt($row->von);
                $end = $this->dt($row->bis);
                if (! $userId || ! $start || ! $end) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                OnCallShift::updateOrCreate(
                    ['legacy_id' => $row->id],
                    [
                        'user_id' => $userId,
                        'start_at' => $start,
                        'end_at' => $end,
                        'note' => null,
                        'is_archived' => false,
                    ]
                );
                $imported++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->line("  {$imported} Bereitschaften importiert, {$skipped} übersprungen.");
    }

    private function importAssignments(): void {
        $this->info('Importiere Notdienste ...');

        $userMap = $this->userMap();
        $total = DB::connection('legacy')->table('notdnst')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        DB::connection('legacy')->table('notdnst')->orderBy('id')->chunk(200, function ($rows) use ($userMap, $bar, &$imported, &$skipped) {
            foreach ($rows as $row) {
                $userId = $userMap[$row->user] ?? null;
                $start = $this->dt($row->von);
                $end = $this->dt($row->bis);
                if (! $userId || ! $start || ! $end) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                EmergencyAssignment::updateOrCreate(
                    ['legacy_id' => $row->id],
                    [
                        'user_id' => $userId,
                        'on_call_shift_id' => null,
                        'start_at' => $start,
                        'end_at' => $end,
                        'reason' => isset($row->grund) ? $this->nullableLegacyText($row->grund) : null,
                        'is_archived' => false,
                    ]
                );
                $imported++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->line("  {$imported} Notdienste importiert, {$skipped} übersprungen.");
    }

    private function importSupport(Organization $organization): void {
        $this->info("Importiere Support-Portal nach „{$organization->name}“ ...");

        $result = app(LegacySupportImportService::class)->import($organization);

        $this->line("  Kunden: {$result['customers_created']} angelegt, {$result['customers_matched']} vorhandenen zugeordnet.");
        $this->line("  Aufträge: {$result['tickets_created']} als Ticket angelegt, {$result['tickets_updated']} aktualisiert, {$result['tickets_skipped']} übersprungen (Ticketnummer belegt).");
    }

    /** Ortszeit des Altsystems als UTC-Wert für das neue System; Null-Daten entfallen. */
    private function dt(mixed $val): ?string {
        if ($val === null) {
            return null;
        }
        $s = (string) $val;
        if (ToolkitStringHelper::isNullOrEmpty($s) || str_starts_with($s, '0000-00-00')) {
            return null;
        }

        try {
            return LegacyClock::toUtc($s);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeLegacyText(string $value): string {
        if (ToolkitStringHelper::isNullOrEmpty($value)) {
            return '';
        }

        return ToolkitStringHelper::convertToUtf8($value, $this->legacyEncoding);
    }

    private function nullableLegacyText(mixed $value): ?string {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeLegacyText((string) $value);

        return ToolkitStringHelper::isNullOrEmpty($normalized) ? null : $normalized;
    }
}
