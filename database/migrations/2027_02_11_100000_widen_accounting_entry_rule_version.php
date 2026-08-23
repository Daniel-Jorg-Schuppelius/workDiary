<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_11_100000_widen_accounting_entry_rule_version.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-674: `rule_version` fasst die Regelfassungen ALLER Zeilen
 * einer Buchung zusammen (`rule:103@v1,rule:104@v1,…`). Mit drei Zeilen und
 * vierstelligen IDs sprengt das die ursprünglichen 32 Zeichen — in MariaDB
 * ein harter Insert-Fehler, in SQLite still. 191 Zeichen decken auch Belege
 * mit mehreren Steuersätzen ab; der vollständige Nachweis je Zeile steht
 * ohnehin im Snapshot.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->string('rule_version', 191)->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->string('rule_version', 32)->nullable()->change();
        });
    }
};
