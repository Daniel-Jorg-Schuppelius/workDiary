<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationLifecycleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Http\Controllers\OrganizationSwitchController;
use App\Models\{Organization, OrganizationAuditLog, User};
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Log, Schema, Storage};
use RuntimeException;
use ZipArchive;

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
        // verletzen und die Kette zerreißen ({@see App\Models\AuditLog}).
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

        $this->log($org, OrganizationAuditLog::ACTION_DEACTIVATE, $actor);

        return $org;
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

        $zip = new ZipArchive;
        if ($zip->open($zipAbsPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Konnte ZIP-Datei nicht öffnen: ' . $zipAbsPath);
        }

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
            $zip->addFromString('data/' . $table . '.jsonl', $ndjson);
            $manifest['tables'][$table] = $count;
        }

        // 2) Organization-Stammsatz separat sichern (hat selbst keine
        //    organization_id-Spalte).
        $zip->addFromString(
            'data/_organization.json',
            JsonHelper::encode($org->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        // 3) Dateien aus bekannten orgspezifischen Storage-Pfaden.
        foreach ($this->storageFoldersFor($org) as $relFolder) {
            $abs = storage_path('app/' . ltrim($relFolder, '/'));
            if (! is_dir($abs)) {
                continue;
            }
            $count = 0;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($it as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $localName = 'files/' . ltrim($relFolder, '/') . '/' .
                    ltrim(str_replace($abs, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $zip->addFile($file->getPathname(), $localName);
                $count++;
            }
            if ($count > 0) {
                $manifest['files'][$relFolder] = $count;
            }
        }

        $zip->addFromString(
            'manifest.json',
            JsonHelper::encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
        $zip->close();

        $hash = ToolkitFile::exists($zipAbsPath) ? ToolkitFile::hash($zipAbsPath) : null;

        $this->log($org, OrganizationAuditLog::ACTION_EXPORT, $actor, [
            'file' => $zipRelPath,
            'tables' => $manifest['tables'],
            'files' => $manifest['files'],
            'bytes' => ToolkitFile::exists($zipAbsPath) ? ToolkitFile::size($zipAbsPath) : null,
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

        // Storage-Folders entfernen (best effort, außerhalb der Transaction).
        foreach ($this->storageFoldersFor($org) as $relFolder) {
            $abs = storage_path('app/' . ltrim($relFolder, '/'));
            if (is_dir($abs)) {
                $this->rrmdir($abs);
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

    private function rrmdir(string $dir): void {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
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
