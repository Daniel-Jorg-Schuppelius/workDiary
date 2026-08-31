<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110700_hash_public_link_tokens.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Öffentliche Link-Token nur noch als Hash speichern (Sicherheitsscan
 * 2026-08-23, S-44).
 *
 * Die meisten öffentlichen Token dieser Anwendung liegen längst als SHA-256
 * ab — Umfrage-Einladung, Angebotsannahme, Terminal, CTI. Drei fielen aus der
 * Reihe und standen im Klartext in der Datenbank:
 *
 *  - `timesheets.magic_token` — Signaturlink eines Stundenzettels. Wer ihn
 *    liest, unterschreibt fremde Arbeitszeit; das ist revisionsrelevant.
 *  - `users.calendar_feed_token` — persönlicher ICS-Feed mit Urlaub und
 *    Schichten einer namentlich benannten Person.
 *
 * Ein Blick in einen Datenbank-Dump oder ein Backup genügte, um beide Links
 * direkt zu benutzen. Der Hash ändert daran das Entscheidende: aus dem
 * gespeicherten Wert lässt sich der Link nicht mehr herstellen.
 *
 * **Bestehende Token werden gehasht, nicht verworfen** — die verschickten
 * Signaturlinks funktionieren weiter. Wer einen Link neu braucht, bekommt ihn
 * über die Oberfläche.
 */
return new class extends Migration {
    public function up(): void {
        $this->migrateColumn('timesheets', 'magic_token', 'magic_token_hash', 'timesheets_magic_token_unique');
        $this->migrateColumn('users', 'calendar_feed_token', 'calendar_feed_token_hash', 'users_calendar_feed_token_unique');
    }

    /**
     * Kein `down()`, das die Klartextwerte zurückholt — sie sind aus dem Hash
     * nicht zu gewinnen. Zurückgerollt wird die Spalte, nicht der Inhalt: die
     * betroffenen Links müssten dann neu erzeugt werden.
     */
    public function down(): void {
        foreach ([['timesheets', 'magic_token_hash'], ['users', 'calendar_feed_token_hash']] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }

    private function migrateColumn(string $table, string $plain, string $hashed, string $uniqueIndex): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $plain)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $hashed, $uniqueIndex): void {
            if (! Schema::hasColumn($table, $hashed)) {
                $blueprint->string($hashed, 64)->nullable()->after('id');
                $blueprint->unique($hashed, mb_substr($uniqueIndex . '_h', 0, 64));
            }
        });

        DB::table($table)
            ->whereNotNull($plain)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $plain, $hashed): void {
                foreach ($rows as $row) {
                    $value = (string) ($row->{$plain} ?? '');
                    if ($value === '') {
                        continue;
                    }
                    DB::table($table)->where('id', $row->id)->update([$hashed => CryptoHelper::hash($value)]);
                }
            });

        Schema::table($table, function (Blueprint $blueprint) use ($plain, $uniqueIndex): void {
            $blueprint->dropUnique($uniqueIndex);
            $blueprint->dropColumn($plain);
        });
    }
};
