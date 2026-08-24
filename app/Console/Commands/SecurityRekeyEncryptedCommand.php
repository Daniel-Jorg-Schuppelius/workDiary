<?php
/*
 * Created on   : Mon Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityRekeyEncryptedCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Crypt, DB};

/**
 * Verschlüsselt alle `encrypted`-Felder mit dem aktuellen APP_KEY neu.
 *
 * Voraussetzung: Während des Laufs muss der ALTE Schlüssel in
 * `APP_PREVIOUS_KEYS` und der NEUE in `APP_KEY` stehen — dann entschlüsselt
 * Laravel mit beiden und verschlüsselt mit dem neuen. Der Command liest jedes
 * Feld roh, entschlüsselt es (alt oder neu) und schreibt es mit dem neuen
 * Schlüssel zurück. Idempotent: ein zweiter Lauf schadet nicht.
 *
 * Felder mit NULL/leer werden übersprungen (ein leerer String ist keine
 * gültige Payload, siehe LexofficeContactSync-Vorfall).
 */
class SecurityRekeyEncryptedCommand extends Command {
    protected $signature = 'security:rekey-encrypted {--dry-run : Nur zählen/prüfen, nichts schreiben}';

    protected $description = 'Schlüsselt alle verschlüsselten DB-Felder auf den aktuellen APP_KEY um (APP_PREVIOUS_KEYS muss den alten Key enthalten).';

    /**
     * Direktnutzer von Crypt::encryptString OHNE encrypted-Cast — die Ableitung
     * aus den Casts sieht sie nicht. Neue Fundstellen erzwingt das Gate
     * RekeyEncryptedCoverageRuleTest. Wert: Feld → zusätzliche Where-Bedingung.
     *
     * @var array<class-string<\Illuminate\Database\Eloquent\Model>, array<string, array<string, mixed>>>
     */
    private const DIRECT_USERS = [
        \App\Models\SystemSetting::class => ['value' => ['is_sensitive' => 1]],
    ];

        public function handle(): int {
        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Re-Key verschlüsselter Felder …');

        $sumRe = 0;
        $sumErr = 0;

        foreach (self::encryptedFieldMap() as $class => $fields) {
            $model = new $class;
            $table = $model->getTable();
            $key = $model->getKeyName();

            foreach ($fields as $field => $conditions) {
                $re = 0;
                $err = 0;

                DB::table($table)
                    ->select([$key, $field])
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->where($conditions)
                    ->orderBy($key)
                    ->chunkById(500, function ($rows) use ($table, $field, $key, $dry, &$re, &$err): void {
                        foreach ($rows as $row) {
                            try {
                                $plain = Crypt::decryptString($row->{$field});
                                if (! $dry) {
                                    DB::table($table)
                                        ->where($key, $row->{$key})
                                        ->update([$field => Crypt::encryptString($plain)]);
                                }
                                $re++;
                            } catch (\Throwable $e) {
                                $err++;
                                $this->warn("  {$table}.{$field} #{$row->{$key}}: {$e->getMessage()}");
                            }
                        }
                    }, $key);

                $this->line(sprintf('  %-24s %-22s umgeschlüsselt=%d fehler=%d', $table, $field, $re, $err));
                $sumRe += $re;
                $sumErr += $err;
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Fertig — umgeschlüsselt: {$sumRe}, Fehler: {$sumErr}");

        return $sumErr === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Model → verschlüsselte Felder, ABGELEITET aus den encrypted-Casts aller
     * Modelle (Vollscan 2026-08-23, D1) statt hartkodiert: eine gepflegte
     * 11er-Liste stand 47 Modellen mit encrypted-Casts gegenüber — jede neue
     * Spalte wäre bei einer Key-Rotation stehen geblieben. Dazu die manuell
     * registrierten Crypt::encryptString-Direktnutzer (DIRECT_USERS).
     *
     * @return array<class-string<\Illuminate\Database\Eloquent\Model>, array<string, array<string, mixed>>> Model → (Feld → Where-Bedingungen)
     */
    public static function encryptedFieldMap(): array {
        $map = [];
        foreach (self::modelClasses() as $class) {
            $fields = [];
            foreach ((new $class)->getCasts() as $field => $cast) {
                if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                    $fields[$field] = [];
                }
            }
            if ($fields !== []) {
                $map[$class] = $fields;
            }
        }
        foreach (self::DIRECT_USERS as $class => $fields) {
            $map[$class] = ($map[$class] ?? []) + $fields;
        }
        ksort($map);

        return $map;
    }

    /** @return list<class-string<\Illuminate\Database\Eloquent\Model>> */
    private static function modelClasses(): array {
        $root = dirname(__DIR__, 3);
        $files = glob($root . '/app/Models/{,*/,*/*/}*.php', GLOB_BRACE) ?: [];
        foreach (glob($root . '/app/Plugins/*/Models/*.php') ?: [] as $file) {
            $files[] = $file;
        }

        $classes = [];
        foreach ($files as $file) {
            /** @var class-string $class */
            $class = 'App\\' . str_replace('/', '\\', substr($file, strlen($root . '/app/'), -strlen('.php')));
            if (class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)
                && (new \ReflectionClass($class))->isInstantiable()) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }
}
