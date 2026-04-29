<?php

namespace App\Console\Commands;

use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyImportCommand extends Command {
    protected $signature = 'legacy:import
        {--users : Nur Benutzer importieren}
        {--diary : Nur Tagebuch-Einträge importieren}
        {--fresh : Vorhandene Daten vor dem Import löschen}';

    protected $description = 'Importiert Benutzer und Tagebuch-Einträge aus der Legacy-Datenbank in die neue Struktur';

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

        $importUsers = $this->option('users') || (! $this->option('users') && ! $this->option('diary'));
        $importDiary = $this->option('diary') || (! $this->option('users') && ! $this->option('diary'));

        if ($this->option('fresh') && $this->confirm('Wirklich alle vorhandenen Daten löschen vor dem Import?', false)) {
            if ($importDiary) {
                DiaryEntry::truncate();
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

        $this->newLine();
        $this->info('Import abgeschlossen.');
        return self::SUCCESS;
    }

    private function importUsers(): void {
        $this->info('Importiere Benutzer ...');

        $legacyUsers = DB::connection('legacy')
            ->table('user')
            ->get();

        $bar = $this->output->createProgressBar($legacyUsers->count());
        $bar->start();

        foreach ($legacyUsers as $legacy) {
            // Passwort in Legacy ist Klartext (varchar 15)
            User::updateOrCreate(
                ['legacy_user_id' => $legacy->id],
                [
                    'name' => $legacy->uname,
                    'email' => $legacy->email ?: $legacy->uname . '@workdiary.local',
                    'password' => Hash::make($legacy->userpw),
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line('  ' . $legacyUsers->count() . ' Benutzer importiert.');
    }

    private function importDiary(): void {
        $this->info('Importiere Tagebuch-Einträge ...');

        // Benutzer-Mapping von legacy_id zu lokaler user_id
        $userMap = User::whereNotNull('legacy_user_id')
            ->pluck('id', 'legacy_user_id')
            ->toArray();

        $total = DB::connection('legacy')->table('tagebuch')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        DB::connection('legacy')
            ->table('tagebuch')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($userMap, $bar, &$imported, &$skipped) {
                foreach ($rows as $row) {
                    $userId = $userMap[$row->user] ?? null;
                    if (! $userId) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // latin1 → utf8: PHP konvertiert beim Lesen via PDO, wenn charset korrekt gesetzt
                    DiaryEntry::updateOrCreate(
                        ['legacy_id' => $row->id],
                        [
                            'user_id' => $userId,
                            'content' => $row->inhalt ?? '',
                            'response' => $row->antwort ?: null,
                            'status' => (int) $row->gelesen,
                            'start_at' => $row->von !== '0000-00-00 00:00:00' ? $row->von : null,
                            'end_at' => $row->bis !== '0000-00-00 00:00:00' ? $row->bis : null,
                            'created_at' => $row->aktuell !== '0000-00-00 00:00:00' ? $row->aktuell : now(),
                            'updated_at' => $row->aktuell !== '0000-00-00 00:00:00' ? $row->aktuell : now(),
                        ]
                    );

                    $imported++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->line("  {$imported} Einträge importiert, {$skipped} ohne bekannten Benutzer übersprungen.");
    }
}
