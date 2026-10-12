<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_18_100000_number_formats_source_plugin_id.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nummernvergabe je Kreis anbieterneutral (MVP-1130): `number_formats.source`
 * trägt statt `external` die Plugin-ID des vergebenden Programms. Bisher
 * konnte nur Lexoffice die Vergabe übernehmen.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('number_formats')->where('source', 'external')->update(['source' => 'lexoffice']);
    }

    public function down(): void {
        DB::table('number_formats')->whereNotIn('source', ['local', ''])->update(['source' => 'external']);
    }
};
