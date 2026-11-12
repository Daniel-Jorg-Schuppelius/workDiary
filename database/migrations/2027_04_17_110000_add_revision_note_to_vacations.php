<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_110000_add_revision_note_to_vacations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1554: Genehmigende geben einen Urlaubsantrag mit Hinweis zur Korrektur
 * zurück (Status `revision`); der Hinweis steht getrennt vom Ablehnungsgrund.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vacations', function (Blueprint $table): void {
            $table->string('revision_note', 500)->nullable()->after('reject_reason');
        });
    }

    public function down(): void {
        Schema::table('vacations', function (Blueprint $table): void {
            $table->dropColumn('revision_note');
        });
    }
};
