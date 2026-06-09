<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditDbHardeningSql.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gibt das DB-seitige Härtungs-SQL aus, das dem Applikations-DB-User UPDATE und
 * DELETE auf den Audit-Tabellen entzieht (Defense-in-Depth zum append-only
 * Guard auf Applikationsebene). Das Kommando FÜHRT NICHTS AUS – es druckt nur
 * die Anweisungen, die ein DBA mit ausreichenden Rechten anwenden muss.
 *
 * Wichtig: `audit_chain_heads` bleibt beschreibbar (der Insert-Pfad schreibt
 * dort den Kettenkopf fort) – daher nur die eigentlichen Audit-Tabellen sperren.
 */
class AuditDbHardeningSql extends Command {
    protected $signature = 'audit:db-hardening-sql {--user= : App-DB-User (sonst aus der Connection-Config)}';

    protected $description = 'Gibt REVOKE-SQL aus, um UPDATE/DELETE auf den Audit-Tabellen zu entziehen.';

    public function handle(): int {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();
        $user = (string) ($this->option('user') ?: config("database.connections.{$connection->getName()}.username", 'app_user'));

        $tables = array_keys((array) config('audit.chains', []));

        $this->warn('// Nicht automatisch ausgeführt – durch einen DBA anzuwenden.');
        $this->warn('// Entzieht dem App-User UPDATE/DELETE auf den Audit-Tabellen (append-only).');
        $this->newLine();

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                foreach ($tables as $table) {
                    $this->line(sprintf('REVOKE UPDATE, DELETE ON `%s`.`%s` FROM %s;', $database, $table, $this->quoteMysqlUser($user)));
                }
                $this->line('FLUSH PRIVILEGES;');
                break;

            case 'pgsql':
                foreach ($tables as $table) {
                    $this->line(sprintf('REVOKE UPDATE, DELETE ON TABLE %s FROM %s;', $table, $this->quoteIdent($user)));
                }
                break;

            case 'sqlite':
                $this->warn('// SQLite kennt keine GRANT/REVOKE-Rechte. Schutz erfolgt über');
                $this->warn('// Dateisystem-Rechte auf die DB-Datei und den App-seitigen append-only Guard.');
                break;

            default:
                $this->error("Treiber '{$driver}' wird nicht unterstützt. Tabellen: " . implode(', ', $tables));

                return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function quoteMysqlUser(string $user): string {
        // Falls "name@host" angegeben wurde, beide Teile quoten, sonst Wildcard-Host.
        if (str_contains($user, '@')) {
            [$name, $host] = explode('@', $user, 2);

            return sprintf("'%s'@'%s'", $name, $host);
        }

        return sprintf("'%s'@'%%'", $user);
    }

    private function quoteIdent(string $ident): string {
        return '"' . str_replace('"', '""', $ident) . '"';
    }
}
