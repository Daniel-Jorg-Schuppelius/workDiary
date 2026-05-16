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

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

trait UsesLegacySqlite {
    protected function useLegacySqlite(): void {
        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
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

        $schema->create('bereit', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('user');
            $table->date('von');
            $table->date('bis');
        });

        $schema->create('notdnst', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('user');
            $table->date('von');
            $table->date('bis');
        });

        $schema->create('tagebuch', function ($table): void {
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
}
