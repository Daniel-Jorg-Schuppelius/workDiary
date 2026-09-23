<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationLifecycleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Org;

use App\Http\Controllers\Platform\OrganizationSwitchController;
use App\Models\Platform\Organization;
use App\Models\Audit\OrganizationAuditLog;
use App\Models\Platform\User;
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\{File as ToolkitFile, Files, Folder as ToolkitFolder};
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Log, Schema, Storage};
use RuntimeException;

/**
 * Bündelt den vollständigen Lebenszyklus einer Organisation:
 *   - deactivate / reactivate  (reversible, sofort wirksam)
 *   - export                   (vollständiger Datenabzug als ZIP)
 *   - purge                    (endgültiges Löschen aller mandanten-
 *                               gebundenen Datensätze und Dateien)
 *
 * Der Service ist die einzige Stelle, an der ein Hard-Delete einer
 * Organisation erfolgt. Direkter Aufruf von Organization::delete() oder
 * destroy() im Controller ist nicht mehr vorgesehen.
 *
 * Compliance-Hinweis (DSGVO Art. 17 & 20):
 *   - export() liefert eine vollständige, maschinenlesbare Kopie aller
 *     personenbezogenen und buchhalterischen Daten.
 *   - purge() entfernt diese Daten unwiderruflich aus der Datenbank und
 *     dem Storage.
 *   - Beide Vorgänge werden in organization_audit_logs revisionssicher
 *     protokolliert; dieser Audit-Trail überlebt den Purge selbst.
 */
class OrganizationLifecycleService {
    /**
     * Zeit, die zwischen Deaktivierung und endgültigem Löschen liegen muss.
     * Override-bar via config('archive.purge_cooldown_hours').
     */
    public const DEFAULT_COOLDOWN_HOURS = 24;

    /**
     * Maximale Anzahl Pässe, die der Purge versucht, um zyklische FK-
     * Abhängigkeiten zu lösen. Bei unrealistisch vielen Pässen wird mit
     * einer Ausnahme abgebrochen (Transaction rollback'd dann sowieso).
     */
    private const PURGE_MAX_PASSES = 25;

    /**
     * Tabellen, die NICHT als "Mandantendaten" behandelt werden,
     * obwohl sie eine organization_id-Spalte tragen:
     *   - organizations selbst
     *   - der Audit-Trail (überdauert den Purge)
     */
    private const PURGE_EXCLUDE_TABLES = [
        'organizations',
        'organization_audit_logs',
        // Der revisionssichere Änderungs-Trail (Hash-Kette) überdauert den
        // Purge bewusst – ein Löschen würde die GoBD-Unveränderbarkeit
        // verletzen und die Kette zerreißen ({@see App\Models\Audit\AuditLog}).
        'audit_logs',
    ];

    public function deactivate(Organization $org, ?User $actor): Organization {
        if (! $org->is_active) {
            return $org;
        }

        $org->forceFill([
            'is_active' => false,
            'deactivated_at' => Carbon::now(),
        ])->save();

        $this->revokeAccess($org);

        $this->log($org, OrganizationAuditLog::ACTION_DEACTIVATE, $actor);

        return $org;
    }

    /**
     * Bestehende Zugänge der Organisation entwerten.
     *
     * Ein Schalter, der nur künftige Anmeldungen verhindert, sperrt niemanden
     * aus: die offenen Sitzungen und API-Tokens laufen weiter (Sicherheitsscan
     * 2026-08-23, S-04). Deshalb wird beim Abschalten beides gekappt —
     * Sanctum-Tokens hart, Sitzungen über `remember_token` und den
     * Datenbank-Sitzungsspeicher, sofern er verwendet wird.
     */
    private function revokeAccess(Organization $org): void {
        $userIds = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->pluck('id')
            ->all();

        if ($userIds === []) {
            return;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_type', MorphMap::alias(User::class))
            ->whereIn('tokenable_id', $userIds)
            ->delete();

        // Neues remember_token: ein „Angemeldet bleiben"-Cookie gilt danach nicht mehr.
        User::query()->withoutGlobalScopes()->whereIn('id', $userIds)
            ->update(['remember_token' => null]);

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->whereIn('user_id', $userIds)
                ->delete();
        }
    }

    public function reactivate(Organization $org, ?User $actor): Organization {
        if ($org->is_active) {
            return $org;
        }

        $org->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();

        $this->log($org, OrganizationAuditLog::ACTION_REACTIVATE, $actor);

        return $org;
    }

    /**
     * Exportiert sämtliche zur Organisation gehörenden Datensätze als ZIP.
     * Liefert den Storage-relativen Pfad (Disk "local") zur erzeugten Datei.
     */
    public function export(Organization $org, ?User $actor): string {
        $disk = Storage::disk('local');
        $relDir = 'org-exports';
        $disk->makeDirectory($relDir);

        $slug = (string) ($org->slug ?: 'org-' . $org->id);
        $stamp = Carbon::now()->format('Ymd-His');
        $base = sprintf('%s-%s-%s', $slug, $stamp, Str::random(6));
        $zipRelPath = $relDir . '/' . $base . '.zip';
        $zipAbsPath = $disk->path($zipRelPath);

        /** @var list<array{archiveName: string, path?: string, content?: string}> $entries */
        $entries = [];
        $manifest = [
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'plan' => $org->plan,
                'locale' => $org->locale,
                'timezone' => $org->timezone,
                'is_active' => (bool) $org->is_active,
                'created_at' => optional($org->created_at)?->toIso8601String(),
            ],
            'exported_at' => Carbon::now()->toIso8601String(),
            'exported_by' => $actor ? [
                'id' => $actor->id,
                'email' => $actor->email,
                'name' => $actor->name,
            ] : null,
            'app' => [
                'version' => (string) config('app.version', 'dev'),
                'env' => (string) config('app.env'),
            ],
            'tables' => [],
            'files' => [],
        ];

        // 1) DB-Dump: pro mandantengebundener Tabelle eine NDJSON-Datei.
        foreach ($this->organizationTables() as $table) {
            // Hinweisgeberdaten sind aus dem Standard-Mandantenexport
            // ausgeschlossen (besonders schutzbeduerftig, eigener autorisierter
            // Exportpfad, Abschnitt 17/25 des Hinweisgeber-Konzepts).
            if (str_starts_with($table, 'whistleblowing_')) {
                continue;
            }
            $rows = DB::table($table)
                ->where('organization_id', $org->id)
                ->orderBy(Schema::hasColumn($table, 'id') ? 'id' : 'organization_id')
                ->get();
            $count = $rows->count();
            if ($count === 0) {
                continue;
            }
            $ndjson = $rows->map(fn($row) => JsonHelper::encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->implode("\n");
            $entries[] = ['archiveName' => 'data/' . $table . '.jsonl', 'content' => $ndjson];
            $manifest['tables'][$table] = $count;
        }

        // 2) Organization-Stammsatz separat sichern (hat selbst keine
        //    organization_id-Spalte).
        $entries[] = [
            'archiveName' => 'data/_organization.json',
            'content' => JsonHelper::encode($org->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];

        // 3) Dateien aus bekannten orgspezifischen Storage-Pfaden.
        foreach ($this->storageFoldersFor($org) as $relFolder) {
            $abs = storage_path('app/' . ltrim($relFolder, '/'));
            if (! ToolkitFolder::exists($abs)) {
                continue;
            }
            $count = 0;
            foreach (Files::get($abs, true) as $path) {
                $entries[] = [
                    'archiveName' => 'files/' . ltrim($relFolder, '/') . '/' . substr($path, strlen($abs) + 1),
                    'path' => $path,
                ];
                $count++;
            }
            if ($count > 0) {
                $manifest['files'][$relFolder] = $count;
            }
        }

        $entries[] = [
            'archiveName' => 'manifest.json',
            'content' => JsonHelper::encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];
        ZipFile::createFromEntries($entries, $zipAbsPath);

        $hash = ToolkitFile::hash($zipAbsPath);

        $this->log($org, OrganizationAuditLog::ACTION_EXPORT, $actor, [
            'file' => $zipRelPath,
            'tables' => $manifest['tables'],
            'files' => $manifest['files'],
            'bytes' => ToolkitFile::size($zipAbsPath),
        ], $hash);

        return $zipRelPath;
    }

    /**
     * Endgültiges Löschen aller mandantengebundenen Datensätze, Dateien
     * und der Organisation selbst. Idempotent: ein zweiter Aufruf für eine
     * bereits gelöschte Organisation ist ein No-Op (Caller-seitige Prüfung
     * verhindert das ohnehin).
     */
    public function purge(Organization $org, ?User $actor): void {
        $snapshot = [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'plan' => $org->plan,
            'deactivated_at' => optional($org->deactivated_at)?->toIso8601String(),
        ];

        // VOR dem Zeilenloeschen: sonst sind die Dateizeiger weg.
        $fileTargets = $this->fileTargetsFor((int) $org->id);

        DB::transaction(function () use ($org) {
            $orgId = (int) $org->id;
            $tables = $this->organizationTables();

            // Iterative Pässe: FK-blockierte Tabellen werden im nächsten Pass
            // erneut versucht, sobald ihre Abhängigkeiten entfernt sind.
            $remaining = $tables;
            for ($pass = 0; $pass < self::PURGE_MAX_PASSES; $pass++) {
                $stillRemaining = [];
                $progressed = false;

                foreach ($remaining as $table) {
                    try {
                        $deleted = DB::table($table)
                            ->where('organization_id', $orgId)
                            ->delete();
                        if ($deleted > 0) {
                            $progressed = true;
                        }
                    } catch (\Throwable $e) {
                        // FK-Verletzung o. Ä.: im nächsten Pass erneut versuchen.
                        $stillRemaining[] = $table;
                        continue;
                    }
                }

                $remaining = $stillRemaining;
                if ($remaining === [] || ! $progressed) {
                    break;
                }
            }

            if ($remaining !== []) {
                throw new RuntimeException(
                    'Purge: Folgende Tabellen konnten nicht geleert werden: '
                        . implode(', ', $remaining),
                );
            }

            // Organization selbst löschen.
            DB::table('organizations')->where('id', $orgId)->delete();
        });

        // Dateien entfernen (best effort, außerhalb der Transaction).
        $fileResult = $this->deleteFileTargets($fileTargets);
        $snapshot['files_deleted'] = $fileResult['deleted'];
        $snapshot['files_failed'] = $fileResult['failed'];

        // Zusaetzlich die historischen Ablage-Verzeichnisse (best effort).
        foreach ($this->storageFoldersFor($org) as $relFolder) {
            $abs = storage_path('app/' . ltrim($relFolder, '/'));
            if (! ToolkitFolder::exists($abs)) {
                continue;
            }
            try {
                // Symlink-sicher: Links werden entfernt, ihre Ziele bleiben.
                ToolkitFolder::delete($abs, true);
            } catch (\Throwable $e) {
                Log::warning('Purge: Ablageordner nicht vollständig gelöscht', ['folder' => $relFolder, 'error' => $e->getMessage()]);
            }
        }

        // Eventuellen Session-Override aufräumen.
        /** @var \Illuminate\Http\Request $req */
        $req = app('request');
        if ($req->hasSession()) {
            $session = $req->session();
            if ((int) $session->get(OrganizationSwitchController::SESSION_KEY) === (int) $snapshot['id']) {
                $session->forget(OrganizationSwitchController::SESSION_KEY);
            }
        }

        // Audit nach erfolgreichem Purge schreiben – mit Snapshot der Org,
        // weil der Datensatz nun nicht mehr existiert.
        OrganizationAuditLog::create([
            'organization_id' => $snapshot['id'],
            'organization_slug' => $snapshot['slug'],
            'organization_name' => $snapshot['name'],
            'action' => OrganizationAuditLog::ACTION_PURGE,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'payload' => $snapshot,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Cooldown-Prüfung: wie lange muss eine Org deaktiviert sein, bevor
     * sie endgültig gelöscht werden darf.
     */
    public function cooldownHours(): int {
        $h = (int) config('archive.purge_cooldown_hours', self::DEFAULT_COOLDOWN_HOURS);

        return $h > 0 ? $h : self::DEFAULT_COOLDOWN_HOURS;
    }

    public function isPurgeAllowed(Organization $org): bool {
        if ($org->is_active) {
            return false;
        }
        if (! $org->deactivated_at instanceof \DateTimeInterface) {
            // Defensiv: ohne Timestamp lieber blocken.
            return false;
        }
        return Carbon::parse($org->deactivated_at)
            ->addHours($this->cooldownHours())
            ->isPast();
    }

    /**
     * Liste aller Tabellen, die eine organization_id-Spalte tragen
     * (ohne Audit-Trail und ohne organizations selbst).
     *
     * @return list<string>
     */
    private function organizationTables(): array {
        $out = [];
        foreach (Schema::getTables() as $tableInfo) {
            // Laravel 11+ gibt Arrays mit 'name' zurück.
            $name = is_array($tableInfo) ? (string) ($tableInfo['name'] ?? '') : (string) $tableInfo;
            if ($name === '' || in_array($name, self::PURGE_EXCLUDE_TABLES, true)) {
                continue;
            }
            if (Schema::hasColumn($name, 'organization_id')) {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Bekannte Storage-Ordner pro Organisation. Werden für Export und
     * Purge verwendet. Pfade sind relativ zu storage/app/.
     *
     * @return list<string>
     */
    /**
     * Tabellen mit Zeigern auf abgelegte Dateien.
     *
     * Sicherheitsaudit 2026-09-13: Der Purge loeschte ausschliesslich eine
     * Kandidatenliste von Verzeichnissen ({@see self::storageFoldersFor()}),
     * die auf Pfade zeigte, die es nicht gibt — die Oberflaeche meldete die
     * endgueltige Loeschung, auf der Platte blieb alles liegen: Anhaenge,
     * Dokumentfassungen, Personalakten, Bewerbungsunterlagen, Meldeanhaenge.
     * Deshalb werden die Pfade jetzt aus den Daten selbst gelesen, VOR dem
     * Zeilenloeschen, und danach gezielt entfernt.
     *
     * `disk` = Spalte mit dem Datentraeger, sonst `default_disk`.
     * `dir` = der Zeiger ist ein Verzeichnis, kein Einzeldokument.
     *
     * @var array<string, array{path: string, disk?: string, default_disk?: string, dir?: bool}>
     */
    private const FILE_POINTER_TABLES = [
        'attachments' => ['path' => 'path', 'disk' => 'disk'],
        'bank_statements' => ['path' => 'file_path'],
        'billing_transfers' => ['path' => 'file_path'],
        'datev_booking_batches' => ['path' => 'file_path'],
        'export_runs' => ['path' => 'storage_path'],
        'gobd_exports' => ['path' => 'file_path'],
        'import_runs' => ['path' => 'storage_path'],
        'isms_advisories' => ['path' => 'file_path'],
        'isms_audit_packages' => ['path' => 'file_path'],
        'job_application_uploads' => ['path' => 'storage_key'],
        'learning_cmi5_packages' => ['path' => 'storage_path', 'dir' => true],
        'learning_scorm_packages' => ['path' => 'storage_path', 'dir' => true],
        'letterhead_assets' => ['path' => 'original_path', 'disk' => 'disk'],
        'lexoffice_vouchers' => ['path' => 'file_path'],
        'media_renditions' => ['path' => 'path', 'disk' => 'disk'],
        'privacy_attachments' => ['path' => 'path'],
        'resale_imports' => ['path' => 'file_path'],
        'time_exports' => ['path' => 'file_path'],
        'whistleblowing_attachments' => ['path' => 'storage_key', 'default_disk' => 'whistleblowing'],
    ];

    /**
     * Alle Dateien der Organisation einsammeln — VOR dem Zeilenloeschen, sonst
     * sind die Zeiger weg und die Dateien unauffindbar.
     *
     * @return list<array{disk: string, path: string, dir: bool}>
     */
    private function fileTargetsFor(int $orgId): array {
        $targets = [];

        foreach (self::FILE_POINTER_TABLES as $table => $spec) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $spec['path'])) {
                continue;
            }
            $diskColumn = ($spec['disk'] ?? null) !== null && Schema::hasColumn($table, (string) $spec['disk'])
                ? (string) $spec['disk']
                : null;
            $defaultDisk = (string) ($spec['default_disk'] ?? 'local');
            if ($table === 'whistleblowing_attachments') {
                $defaultDisk = (string) config('whistleblowing.disk', 'whistleblowing');
            }

            $columns = [$spec['path']];
            if ($diskColumn !== null) {
                $columns[] = $diskColumn;
            }

            DB::table($table)->where('organization_id', $orgId)->select($columns)->orderBy($spec['path'])
                ->chunk(500, function ($rows) use (&$targets, $spec, $diskColumn, $defaultDisk): void {
                    foreach ($rows as $row) {
                        $path = (string) ($row->{$spec['path']} ?? '');
                        if ($path === '') {
                            continue;
                        }
                        $targets[] = [
                            'disk' => $diskColumn !== null ? (string) ($row->{$diskColumn} ?: $defaultDisk) : $defaultDisk,
                            'path' => $path,
                            'dir' => (bool) ($spec['dir'] ?? false),
                        ];
                    }
                });
        }

        // Dokumentfassungen haengen ueber `document_id` an der Organisation,
        // sie tragen selbst keine organization_id.
        if (Schema::hasTable('document_versions') && Schema::hasTable('documents')) {
            DB::table('document_versions')
                ->join('documents', 'documents.id', '=', 'document_versions.document_id')
                ->where('documents.organization_id', $orgId)
                ->select(['document_versions.disk', 'document_versions.path'])
                ->orderBy('document_versions.path')
                ->chunk(500, function ($rows) use (&$targets): void {
                    foreach ($rows as $row) {
                        $path = (string) ($row->path ?? '');
                        if ($path !== '') {
                            $targets[] = ['disk' => (string) ($row->disk ?: 'local'), 'path' => $path, 'dir' => false];
                        }
                    }
                });
        }

        return $targets;
    }

    /**
     * Eingesammelte Dateien entfernen. Best effort: ein fehlender Datentraeger
     * oder eine bereits verschwundene Datei darf den Purge nicht aufhalten,
     * wird aber gezaehlt und protokolliert.
     *
     * @param  list<array{disk: string, path: string, dir: bool}>  $targets
     * @return array{deleted: int, failed: int}
     */
    private function deleteFileTargets(array $targets): array {
        $deleted = 0;
        $failed = 0;

        foreach ($targets as $target) {
            try {
                $disk = Storage::disk($target['disk']);
                $ok = $target['dir'] ? $disk->deleteDirectory($target['path']) : $disk->delete($target['path']);
                $ok ? $deleted++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('organization.purge.file_delete_failed', [
                    'disk' => $target['disk'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Historische Ablage-Verzeichnisse (Bestandsschutz). Die eigentliche
     * Loeschung laeuft ueber {@see self::fileTargetsFor()}.
     *
     * @return list<string>
     */
    private function storageFoldersFor(Organization $org): array {
        $orgId = (int) $org->id;
        $slug = (string) ($org->slug ?? '');

        $candidates = [
            'public/branding/' . $orgId,
            'public/branding/' . $slug,
            'public/uploads/organizations/' . $orgId,
            'private/uploads/organizations/' . $orgId,
            'private/invoices/' . $orgId,
            'private/exports/' . $orgId,
        ];

        // Unique + ohne leere Slugs.
        return array_values(array_unique(array_filter(
            $candidates,
            fn(string $p) => ! str_ends_with($p, '/') && ! str_ends_with($p, '/0'),
        )));
    }

    /**
     * @param  array<string,mixed>|null  $payload
     */
    private function log(Organization $org, string $action, ?User $actor, ?array $payload = null, ?string $hash = null): void {
        try {
            OrganizationAuditLog::create([
                'organization_id' => $org->id,
                'organization_slug' => $org->slug,
                'organization_name' => $org->name,
                'action' => $action,
                'actor_user_id' => $actor?->id,
                'actor_email' => $actor?->email,
                'payload' => $payload,
                'export_hash' => $hash,
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            // Logging darf den Vorgang nicht abbrechen.
            Log::warning('OrganizationAuditLog write failed', [
                'action' => $action,
                'organization_id' => $org->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
