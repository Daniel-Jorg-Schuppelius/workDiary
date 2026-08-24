<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Vollscan 2026-08-23, F7: audit_logs ist eine Hash-Kette (GoBD) —
 * hashPayload enthält organization_id und user_id. Die FKs mit ON DELETE
 * SET NULL hätten beim Org-Purge/User-Delete die Kette rückwirkend
 * gebrochen (audit-hashkette-cast-regression-2026-07.md §Offen). Wie bei
 * den übrigen Ketten: KEINE FKs — die Spalten bleiben, die Werte bleiben
 * stehen, auch wenn Org/User längst gelöscht sind. Nur MySQL.
 */
return new class extends Migration {
    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign('audit_logs_organization_id_foreign');
            $table->dropForeign('audit_logs_user_id_foreign');
        });
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
