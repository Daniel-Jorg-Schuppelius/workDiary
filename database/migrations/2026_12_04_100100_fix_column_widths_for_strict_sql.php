<?php
/*
 * Created on   : Sun Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_04_100100_fix_column_widths_for_strict_sql.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // encrypted-Casts speichern Ciphertext (~190+ Zeichen, wächst mit dem
        // Klartext) — wie bank_accounts.iban gehören sie auf text. Auf
        // MySQL/MariaDB im strict mode crashte der Insert (1406), SQLite
        // prüfte nie. Einziger weiterer Befund des app-weiten Sweeps über
        // alle 42 Modelle mit encrypted-Casts.
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->text('bic')->nullable()->change();
            $table->text('account_holder')->nullable()->change();
        });

        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->text('webhook_secret')->nullable()->change();
        });

        // ClaimCaseService schreibt Stages wie "overdue:2026-08-10" (18 Z.).
        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->string('stage', 40)->change();
        });
    }

    public function down(): void {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->string('bic', 32)->nullable()->change();
            $table->string('account_holder', 200)->nullable()->change();
        });

        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->string('webhook_secret', 190)->nullable()->change();
        });

        Schema::table('notification_dispatch_log', function (Blueprint $table): void {
            $table->string('stage', 16)->change();
        });
    }
};
