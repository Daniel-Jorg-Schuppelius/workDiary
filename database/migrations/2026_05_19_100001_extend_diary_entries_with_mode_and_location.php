<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_19_100001_extend_diary_entries_with_mode_and_location.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            // Termin-Modus: fixed (Termin steht), deadline (bis X erledigen),
            // window (Korridor von Tag A bis Tag B), recurring (vom Generator
            // erzeugt — Generator-Tabelle folgt in einer späteren Stufe),
            // backlog (irgendwann, kein Datum).
            $table->string('mode', 16)->default('fixed')->after('priority');

            $table->date('due_date')->nullable()->after('mode');
            $table->date('window_start_date')->nullable()->after('due_date');
            $table->date('window_end_date')->nullable()->after('window_start_date');

            // onsite | remote | hybrid — bewusste Auswahl pro Auftrag, NICHT
            // aus der Kundenadresse abgeleitet.
            $table->string('location_mode', 16)->default('onsite')->after('window_end_date');

            $table->index(['organization_id', 'mode'], 'de_org_mode_idx');
            $table->index('due_date', 'de_due_date_idx');
            $table->index('location_mode', 'de_location_mode_idx');
        });

        // Bestand: alle vorhandenen Aufträge gelten als 'fixed' und 'onsite'
        // (Defaults greifen bereits beim Anlegen der Spalten, aber wir setzen
        // es explizit, falls die DB den Default nicht rückwirkend füllt).
        DB::table('diary_entries')->update([
            'mode' => 'fixed',
            'location_mode' => 'onsite',
        ]);
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('de_org_mode_idx');
            $table->dropIndex('de_due_date_idx');
            $table->dropIndex('de_location_mode_idx');

            $table->dropColumn([
                'mode',
                'due_date',
                'window_start_date',
                'window_end_date',
                'location_mode',
            ]);
        });
    }
};
