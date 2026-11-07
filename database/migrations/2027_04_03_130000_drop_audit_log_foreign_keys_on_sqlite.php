<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_03_130000_drop_audit_log_foreign_keys_on_sqlite.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachtrag zu 2027_02_19_100600: Die Hash-Kette von audit_logs verträgt kein
 * ON DELETE SET NULL. Die Vorgängerin übersprang SQLite, dort setzte der
 * Org-Purge organization_id auf null. Laravel baut die Tabelle zum Entfernen
 * neu auf; audit_logs hat weder partielle Indizes noch Trigger.
 */
return new class extends Migration {
    public function up(): void {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['user_id']);
        });
    }

    public function down(): void {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
