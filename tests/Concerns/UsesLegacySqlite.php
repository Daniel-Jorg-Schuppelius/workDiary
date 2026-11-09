<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UsesLegacySqlite.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

use Illuminate\Support\Facades\{Config, DB};

trait UsesLegacySqlite {
    protected function useLegacySqlite(): void {
        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            // SQLite liefert UTF-8; ohne Angabe nähmen Importe die Vorgabe latin1 an.
            'charset' => 'utf8',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('legacy');
        $schema = DB::connection('legacy')->getSchemaBuilder();

        $schema->create('user', function ($table): void {
            $table->increments('id');
            $table->string('uname')->unique();
            $table->string('userpw')->nullable();
            $table->string('email')->nullable();
        });

        $schema->create('calluser', function ($table): void {
            $table->increments('id');
            $table->string('uname')->unique();
            $table->string('userpw');
        });

        // Laufende Tabellen und ihr Archiv (a_*) haben dieselben Spalten.
        foreach (['bereit', 'notdnst', 'a_bereit', 'a_notdnst'] as $dutyTable) {
            $schema->create($dutyTable, function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('user');
                $table->date('von');
                $table->date('bis');
            });
        }

        foreach (['tagebuch', 'a_tagebuch'] as $diaryTable) {
            $schema->create($diaryTable, function ($table): void {
                $table->increments('id');
                $table->unsignedInteger('user')->nullable();
                $table->text('inhalt')->nullable();
                $table->text('antwort')->nullable();
                $table->dateTime('von')->nullable();
                $table->dateTime('bis')->nullable();
                $table->dateTime('aktuell')->nullable();
                $table->integer('gelesen')->nullable();
                $table->string('sms')->nullable();
            });
        }

        // Support-Portal: Spalten und Vorgaben wie im Altsystem (NOT NULL mit Leerwert).
        $schema->create('kp_user', function ($table): void {
            $table->increments('id');
            $table->string('kunde')->default('');
            $table->string('usern', 10)->default('');
            $table->string('passw', 10)->default('');
        });

        $supportOrderColumns = function ($table): void {
            $table->unsignedInteger('kunde')->default(0);
            $table->dateTime('datum')->default('0000-00-00 00:00:00');
            $table->string('kname', 30)->default('');
            $table->string('telefon', 30)->default('');
            $table->string('objekt')->default('');
            $table->text('auftrag')->default('');
            $table->dateTime('termin')->default('2200-01-11 20:00:22');
            $table->string('wer', 30)->default('');
            $table->text('antwort')->default('');
            $table->text('intern')->default('');
            $table->string('dauer')->default('');
            $table->integer('status')->default(0);
        };

        $schema->create('kp_auftrag', function ($table) use ($supportOrderColumns): void {
            $table->increments('id');
            $supportOrderColumns($table);
        });

        $schema->create('kp_archiv', function ($table) use ($supportOrderColumns): void {
            $table->unsignedInteger('id')->default(0);
            $supportOrderColumns($table);
        });

        $schema->create('email', function ($table): void {
            $table->increments('id');
            $table->string('email', 25)->default('');
            $table->integer('schalt')->default(0);
        });
    }
}
