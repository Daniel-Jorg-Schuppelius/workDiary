<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100300_add_round_to_approvals_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freigaberunden: eine Kette kann für dasselbe Objekt neu gestartet werden,
 * die höchste Runde gilt, frühere bleiben als Historie stehen. Der Bestand
 * ist Runde 1. Kein eigener Index — gelesen wird je Objekt über
 * `apv_subject_idx`, eine Kette hat nur wenige Zeilen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->unsignedSmallInteger('round')->default(1)->after('step');
        });
    }

    public function down(): void {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropColumn('round');
        });
    }
};
